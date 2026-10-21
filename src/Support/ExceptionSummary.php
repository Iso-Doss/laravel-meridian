<?php

namespace IsoDoss\Meridian\Support;

use Illuminate\Support\Str;

/**
 * Reads the exception recorded for a failed job ((string) $exception) and
 * gives its class, message and location, and the fingerprint grouping the
 * failures with the same cause: same exception class thrown at the same line.
 */
class ExceptionSummary
{
    /**
     * @return array{class: string, message: string, file: string|null, line: int|null, fingerprint: string}|null
     */
    public static function parse(?string $exception): ?array
    {
        if ($exception === null || trim($exception) === '') {
            return null;
        }

        // With chained exceptions, PHP prints the first cause first and the
        // exception actually thrown last ("Next ...").
        $head = trim(Str::before(Str::afterLast($exception, "\n\nNext "), "\nStack trace:"));

        if (! preg_match('/^([A-Za-z_\\\\][\w\\\\]*)(?::\s?(.*))?$/s', $head, $match)) {
            return null;
        }

        $class = $match[1];
        $message = $match[2] ?? '';
        $file = null;
        $line = null;

        if (preg_match('/^(.*) in (.+):(\d+)$/s', $message, $location)) {
            [, $message, $file, $line] = $location;
            $line = (int) $line;
        }

        return [
            'class' => $class,
            'message' => trim($message),
            'file' => $file,
            'line' => $line,
            'fingerprint' => sha1($class.'|'.$file.':'.$line),
        ];
    }

    public static function fingerprint(?string $exception): ?string
    {
        return static::parse($exception)['fingerprint'] ?? null;
    }
}
