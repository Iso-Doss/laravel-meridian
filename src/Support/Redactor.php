<?php

namespace IsoDoss\Meridian\Support;

use Illuminate\Support\Str;
use Throwable;

/**
 * Masks the sensitive values of a job payload before Meridian stores it
 * ("meridian.redact"): the keys of the JSON payload and the properties of
 * the serialized command whose name matches.
 *
 * The command is rewritten without being unserialized (which would run code
 * and query the models). Encrypted commands (ShouldBeEncrypted) are kept
 * as they are: they are already unreadable.
 */
class Redactor
{
    public const MARKER = '[redacted]';

    /**
     * @var array<int, string>|null
     */
    protected ?array $patterns;

    /**
     * Whether a composite value (array, object) was replaced by a string.
     */
    protected bool $shrunk = false;

    /**
     * Whether the serialized command uses references (r: / R:).
     */
    protected bool $references = false;

    /**
     * @param  array<int, string>|null  $keys  Defaults to "meridian.redact".
     */
    public function __construct(?array $keys = null)
    {
        $keys ??= (array) config('meridian.redact', []);

        $this->patterns = array_values(array_filter(array_map(fn ($key) => static::normalize((string) $key), $keys)));
    }

    /**
     * The payload to store, its sensitive values masked.
     */
    public static function payload(?string $raw): ?string
    {
        return $raw === null ? null : (new self)->redact($raw);
    }

    /**
     * The payload Meridian stores for a job: masked, or none ("store_payload").
     */
    public static function store(?string $raw): ?string
    {
        return config('meridian.store_payload') ? static::payload($raw) : null;
    }

    /**
     * Whether a stored payload had values masked (it cannot be pushed back as is).
     */
    public static function redacted(?string $payload): bool
    {
        return $payload !== null && str_contains($payload, self::MARKER);
    }

    /**
     * Whether a key or property name is sensitive.
     */
    public function sensitive(string $name): bool
    {
        $name = static::normalize($name);

        foreach ($this->patterns as $pattern) {
            if (Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    public function redact(string $raw): string
    {
        if ($this->patterns === []) {
            return $raw;
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            return $raw;
        }

        $command = $payload['data']['command'] ?? null;
        $payload = $this->redactArray($payload);

        if (is_string($command) && str_starts_with($command, 'O:')) {
            $payload['data']['command'] = $this->redactSerialized($command);
        }

        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    protected function redactArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->sensitive($key)) {
                $values[$key] = self::MARKER;
            } elseif (is_array($value)) {
                $values[$key] = $this->redactArray($value);
            }
        }

        return $values;
    }

    /**
     * The serialized command with its sensitive properties masked. When it
     * cannot be rewritten safely, the whole command is masked.
     */
    public function redactSerialized(string $serialized): string
    {
        $this->shrunk = false;
        $this->references = false;

        try {
            $offset = 0;
            $output = $this->value($serialized, $offset);

            if ($offset !== strlen($serialized)) {
                throw new \UnexpectedValueException('Trailing data.');
            }
        } catch (Throwable) {
            return $this->mentionsSensitive($serialized) ? self::MARKER : $serialized;
        }

        // Replacing an array by a string shifts the numbering of the references.
        if ($this->shiftsReferences()) {
            return self::MARKER;
        }

        return $output;
    }

    protected function shiftsReferences(): bool
    {
        return $this->shrunk && $this->references;
    }

    /**
     * Read one value at $offset and return it, rewritten.
     */
    protected function value(string $data, int &$offset): string
    {
        $type = substr($data, $offset, 1);

        switch ($type) {
            case 'N':
                $this->expect($data, $offset, 'N;');

                return 'N;';

            case 'b':
            case 'i':
            case 'd':
            case 'r':
            case 'R':
                $end = strpos($data, ';', $offset);

                if ($end === false || substr($data, $offset + 1, 1) !== ':') {
                    throw new \UnexpectedValueException('Malformed scalar.');
                }

                $this->references = $this->references || $type === 'r' || $type === 'R';
                $token = substr($data, $offset, $end - $offset + 1);
                $offset = $end + 1;

                return $token;

            case 's':
                return $this->serializeString($this->string($data, $offset));

            case 'E':
                $start = $offset;
                $this->string($data, $offset, 'E');

                return substr($data, $start, $offset - $start);

            case 'a':
                $offset += 2;
                $count = $this->integer($data, $offset, ':');
                $this->expect($data, $offset, '{');

                return 'a:'.$count.':{'.$this->members($data, $offset, $count).'}';

            case 'O':
                $offset += 2;
                $length = $this->integer($data, $offset, ':');
                $this->expect($data, $offset, '"');
                $class = substr($data, $offset, $length);
                $offset += $length;
                $this->expect($data, $offset, '":');
                $count = $this->integer($data, $offset, ':');
                $this->expect($data, $offset, '{');

                return 'O:'.strlen($class).':"'.$class.'":'.$count.':{'.$this->members($data, $offset, $count).'}';

            case 'C':
                // Custom serialization (Serializable): opaque, kept as is.
                $start = $offset;
                $offset += 2;
                $length = $this->integer($data, $offset, ':');
                $offset += $length + 2;
                $this->expect($data, $offset, ':');
                $inner = $this->integer($data, $offset, ':');
                $offset += $inner + 2;

                return substr($data, $start, $offset - $start);
        }

        throw new \UnexpectedValueException("Unknown type [{$type}].");
    }

    /**
     * The members of an array or object, the values of the sensitive keys masked.
     */
    protected function members(string $data, int &$offset, int $count): string
    {
        $output = '';

        for ($i = 0; $i < $count; $i++) {
            $keyStart = $offset;
            $key = substr($data, $offset, 1) === 's' ? $this->string($data, $offset) : null;

            if ($key === null) {
                $output .= $this->value($data, $offset);
                $output .= $this->value($data, $offset);

                continue;
            }

            $output .= substr($data, $keyStart, $offset - $keyStart);

            // Protected and private properties are prefixed with "\0*\0" or "\0Class\0".
            $name = str_starts_with($key, "\0") ? substr($key, (int) strrpos($key, "\0") + 1) : $key;
            $valueType = substr($data, $offset, 1);
            $value = $this->value($data, $offset);

            if ($this->sensitive($name) && $valueType !== 'N') {
                $this->shrunk = $this->shrunk || in_array($valueType, ['a', 'O', 'C'], true);
                $output .= $this->serializeString(self::MARKER);
            } else {
                $output .= $value;
            }
        }

        $this->expect($data, $offset, '}');

        return $output;
    }

    /**
     * Read a s:N:"..."; (or E:N:"...";) string and return its content.
     */
    protected function string(string $data, int &$offset, string $type = 's'): string
    {
        $this->expect($data, $offset, $type.':');
        $length = $this->integer($data, $offset, ':');
        $this->expect($data, $offset, '"');
        $value = substr($data, $offset, $length);

        if (strlen($value) !== $length) {
            throw new \UnexpectedValueException('Truncated string.');
        }

        $offset += $length;
        $this->expect($data, $offset, '";');

        return $value;
    }

    protected function integer(string $data, int &$offset, string $terminator): int
    {
        $end = strpos($data, $terminator, $offset);

        if ($end === false) {
            throw new \UnexpectedValueException('Missing length.');
        }

        $number = substr($data, $offset, $end - $offset);

        if (! preg_match('/^-?\d+$/', $number)) {
            throw new \UnexpectedValueException('Invalid length.');
        }

        $offset = $end + strlen($terminator);

        return (int) $number;
    }

    protected function expect(string $data, int &$offset, string $expected): void
    {
        if (substr($data, $offset, strlen($expected)) !== $expected) {
            throw new \UnexpectedValueException("Expected [{$expected}].");
        }

        $offset += strlen($expected);
    }

    protected function serializeString(string $value): string
    {
        return 's:'.strlen($value).':"'.$value.'";';
    }

    /**
     * Whether a serialized string mentions a sensitive name (when it cannot be parsed).
     */
    protected function mentionsSensitive(string $serialized): bool
    {
        preg_match_all('/s:\d+:"(?:\x00[^\x00]*\x00)?([A-Za-z0-9_\-]+)";/', $serialized, $matches);

        foreach ($matches[1] as $name) {
            if ($this->sensitive($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * "API-Key", "api_key" and "apiKey" are the same key.
     */
    protected static function normalize(string $name): string
    {
        return (string) preg_replace('/[^a-z0-9*]/', '', strtolower($name));
    }
}
