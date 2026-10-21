<?php

namespace IsoDoss\Meridian\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Repositories\JobRetryRepository;
use JsonException;
use ReflectionNamedType;
use ReflectionProperty;
use Throwable;

/**
 * Retries a failed job after changing the data it holds, when it failed
 * because of an invalid value. Only plain values (strings, numbers,
 * booleans, arrays) can be changed: models, enums, dates and other objects
 * are kept as they are.
 */
class JobEditor
{
    /**
     * The queue settings of a job, not its data: never changed.
     */
    public const SETTINGS = ['tries', 'timeout', 'maxExceptions', 'backoff', 'failOnTimeout', 'deleteWhenMissingModels', 'uniqueFor', 'shouldBeEncrypted'];

    /**
     * The properties of a failed job, with their editable value.
     *
     * @return array<int, array{name: string, type: string, value: string, editable: bool}>|null Null when the job cannot be edited.
     *                                                                                           The type is string, int, float, bool or json when editable, else setting or object.
     */
    public function fields(MonitoredJob $job): ?array
    {
        if ($job->status !== MonitoredJob::FAILED || ! $job->payload) {
            return null;
        }

        $command = JobData::command($job->payload);

        if (! is_array($command)) {
            return null;
        }

        return collect(JobData::ownProperties($command[0]))
            ->map(function (ReflectionProperty $property) use ($command) {
                $value = $property->getValue($command[0]);
                $setting = in_array($property->getName(), self::SETTINGS, true);
                $type = $setting ? null : static::typeOf($value);

                return [
                    'name' => $property->getName(),
                    'type' => $setting ? 'setting' : ($type ?? 'object'),
                    'value' => $type === null ? JobData::format($value) : static::toText($value, $type),
                    'editable' => $type !== null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Change the data of a failed job and push it back onto its queue.
     *
     * @param  array<string, mixed>  $input  The new values, as text, by property name.
     * @return array<int, string> The names of the properties changed.
     *
     * @throws ValidationException
     */
    public function retry(MonitoredJob $job, array $input): array
    {
        $command = JobData::command($job->payload);

        if ($job->status !== MonitoredJob::FAILED || ! is_array($command)) {
            throw ValidationException::withMessages(['data' => Translator::get('This job cannot be edited.')]);
        }

        [$object, $encrypted] = $command;
        $properties = JobData::ownProperties($object);
        $changed = [];
        $errors = [];

        foreach ($input as $name => $text) {
            $property = $properties[$name] ?? null;
            $current = $property?->getValue($object);
            $type = $property && ! in_array($name, self::SETTINGS, true) ? static::typeOf($current) : null;

            if ($property === null || $type === null || ! is_scalar($text ?? '')) {
                continue;
            }

            try {
                $value = static::fromText((string) $text, $type, $property);
            } catch (Throwable) {
                $errors["data.{$name}"] = Translator::get('Invalid value for :name.', ['name' => $name]);

                continue;
            }

            if ($value === $current) {
                continue;
            }

            try {
                $property->setValue($object, $value);
                $changed[] = $name;
            } catch (Throwable) {
                $errors["data.{$name}"] = Translator::get('Invalid value for :name.', ['name' => $name]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $payload = json_decode((string) $job->payload, true);
        $payload['attempts'] = 0;
        $payload['data']['command'] = $encrypted ? Crypt::encrypt(serialize($object)) : serialize($object);
        $raw = (string) json_encode($payload);

        app('queue.failer')->forget($job->uuid);
        Queue::connection($job->connection)->pushRaw($raw, $job->queue);

        $job->update([
            'status' => MonitoredJob::PENDING,
            'retries' => $job->retries + 1,
            'failed_at' => null,
            'available_at' => now(),
            'payload' => config('meridian.store_payload') ? $raw : $job->payload,
        ]);
        app(JobRetryRepository::class)->retried([$job->uuid]);

        return $changed;
    }

    /**
     * The editable type of a value, null when it cannot be edited.
     */
    protected static function typeOf(mixed $value): ?string
    {
        return match (true) {
            is_string($value) => 'string',
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_bool($value) => 'bool',
            $value === null, is_array($value) && static::isPlain($value) => 'json',
            default => null,
        };
    }

    /**
     * Arrays of plain values only, that survive a JSON round trip.
     *
     * @param  array<array-key, mixed>  $value
     */
    protected static function isPlain(array $value): bool
    {
        foreach ($value as $item) {
            if (! (is_scalar($item) || $item === null || (is_array($item) && static::isPlain($item)))) {
                return false;
            }
        }

        return true;
    }

    protected static function toText(mixed $value, string $type): string
    {
        return match ($type) {
            'bool' => $value ? 'true' : 'false',
            'json' => (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }

    /**
     * @throws JsonException|\InvalidArgumentException
     */
    protected static function fromText(string $text, string $type, ReflectionProperty $property): mixed
    {
        $text = $type === 'string' ? $text : trim($text);

        $value = match ($type) {
            'string' => $text,
            'int' => preg_match('/^-?\d+$/', $text) ? (int) $text : throw new \InvalidArgumentException,
            'float' => is_numeric($text) ? (float) $text : throw new \InvalidArgumentException,
            'bool' => match (strtolower($text)) {
                'true', '1' => true,
                'false', '0' => false,
                default => throw new \InvalidArgumentException,
            },
            default => json_decode($text === '' ? 'null' : $text, true, 64, JSON_THROW_ON_ERROR),
        };

        // A typed property refuses a value of another type.
        $declared = $property->getType();

        if ($declared instanceof ReflectionNamedType && $declared->isBuiltin()) {
            $accepted = match ($declared->getName()) {
                'array' => is_array($value),
                'string' => is_string($value),
                'int' => is_int($value),
                'float' => is_float($value) || is_int($value),
                'bool' => is_bool($value),
                default => true,
            };

            if (! $accepted && ! ($value === null && $declared->allowsNull())) {
                throw new \InvalidArgumentException;
            }
        }

        return $value;
    }
}
