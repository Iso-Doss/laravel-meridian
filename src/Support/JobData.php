<?php

namespace IsoDoss\Meridian\Support;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Database\ModelIdentifier;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use ReflectionClass;
use ReflectionProperty;
use Throwable;
use UnitEnum;

/**
 * The data of a job (the properties of its command), readable, like the
 * "Data" panel of Horizon.
 */
class JobData
{
    /**
     * @return array{properties: array<int, array{name: string, value: string}>, error: string|null}|null
     */
    public static function describe(?string $payload): ?array
    {
        $command = static::command($payload);

        if ($command === null) {
            return null;
        }

        if (is_string($command)) {
            return ['properties' => [], 'error' => $command];
        }

        return ['properties' => static::properties($command[0]), 'error' => null];
    }

    /**
     * Unserialize the command of a payload, decrypting it when needed.
     *
     * @return array{0: object, 1: bool}|string The command and whether it was encrypted, or an error.
     */
    public static function command(?string $payload): array|string|null
    {
        $decoded = $payload ? json_decode($payload, true) : null;
        $command = $decoded['data']['command'] ?? null;

        if (! is_string($command)) {
            return null;
        }

        $encrypted = ! str_starts_with($command, 'O:');

        try {
            $object = unserialize($encrypted ? (string) Crypt::decrypt($command) : $command);
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        if (! is_object($object) || $object instanceof \__PHP_Incomplete_Class) {
            return 'The job class cannot be loaded.';
        }

        return [$object, $encrypted];
    }

    /**
     * The properties declared by the job itself (not by the queue traits).
     *
     * @return array<string, ReflectionProperty>
     */
    public static function ownProperties(object $object): array
    {
        $ignored = collect(class_uses_recursive($object))
            ->flatMap(fn (string $trait) => array_map(
                fn (ReflectionProperty $property) => $property->getName(),
                (new ReflectionClass($trait))->getProperties()
            ))
            ->merge(['job', 'connection', 'queue', 'delay', 'afterCommit', 'middleware', 'chained', 'chainConnection', 'chainQueue', 'chainCatchCallbacks', 'batchId'])
            ->unique()
            ->flip();

        return collect((new ReflectionClass($object))->getProperties())
            ->reject(fn (ReflectionProperty $property) => $property->isStatic() || $ignored->has($property->getName()))
            ->filter(fn (ReflectionProperty $property) => $property->isInitialized($object))
            ->keyBy(fn (ReflectionProperty $property) => $property->getName())
            ->all();
    }

    /**
     * @return array<int, array{name: string, value: string}>
     */
    protected static function properties(object $object): array
    {
        return collect(static::ownProperties($object))
            ->map(fn (ReflectionProperty $property) => [
                'name' => $property->getName(),
                'value' => static::format($property->getValue($object)),
            ])
            ->values()
            ->all();
    }

    public static function format(mixed $value, int $depth = 0): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            is_string($value) => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $value instanceof Model => get_class($value).':'.$value->getKey(),
            $value instanceof ModelIdentifier => $value->class.':'.implode(',', (array) $value->id),
            $value instanceof EloquentCollection => '['.$value->map(fn ($model) => static::format($model, $depth + 1))->implode(', ').']',
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof BackedEnum => get_class($value).'::'.$value->name.' ('.json_encode($value->value).')',
            $value instanceof UnitEnum => get_class($value).'::'.$value->name,
            is_array($value) => $depth > 2 ? '[…]' : static::formatArray($value, $depth),
            is_object($value) => get_class($value),
            default => get_debug_type($value),
        };
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    protected static function formatArray(array $value, int $depth): string
    {
        $list = array_is_list($value);
        $items = array_map(
            fn ($key, $item) => ($list ? '' : json_encode($key).': ').static::format($item, $depth + 1),
            array_keys($value),
            $value
        );

        $text = implode(', ', array_slice($items, 0, 20)).(count($items) > 20 ? ', …' : '');

        return $list ? "[{$text}]" : "{{$text}}";
    }
}
