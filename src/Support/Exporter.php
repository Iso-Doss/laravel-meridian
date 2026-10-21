<?php

namespace IsoDoss\Meridian\Support;

use Closure;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams rows as a CSV or JSON download, without loading them all in memory.
 */
class Exporter
{
    public const FORMATS = ['csv', 'json'];

    /**
     * @param  iterable<int, array<string, mixed>>|Closure(): iterable<int, array<string, mixed>>  $rows
     */
    public static function download(string $name, string $format, iterable|Closure $rows): StreamedResponse
    {
        $format = in_array($format, self::FORMATS, true) ? $format : 'csv';
        $filename = $name.'-'.now()->format('Y-m-d-His').'.'.$format;

        return response()->streamDownload(function () use ($format, $rows) {
            $rows = $rows instanceof Closure ? $rows() : $rows;

            $format === 'json' ? static::json($rows) : static::csv($rows);
        }, $filename, [
            'Content-Type' => $format === 'json' ? 'application/json' : 'text/csv; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    protected static function csv(iterable $rows): void
    {
        $out = fopen('php://output', 'w');

        if ($out === false) {
            return;
        }

        // UTF-8 byte order mark: Excel then reads accents correctly.
        fwrite($out, "\xEF\xBB\xBF");
        $header = false;

        foreach ($rows as $row) {
            if (! $header) {
                fputcsv($out, array_keys($row), escape: '');
                $header = true;
            }

            fputcsv($out, array_map(fn ($value) => static::cell($value), array_values($row)), escape: '');
        }

        fclose($out);
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    protected static function json(iterable $rows): void
    {
        echo '[';
        $first = true;

        foreach ($rows as $row) {
            echo ($first ? '' : ',')."\n".json_encode(array_map(fn ($value) => $value instanceof DateTimeInterface ? $value->format(DATE_ATOM) : $value, $row), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $first = false;
        }

        echo "\n]\n";
    }

    /**
     * A CSV cell: dates in ISO 8601, lists joined, and no formula
     * (a value starting with =, +, - or @ is run by spreadsheets).
     */
    protected static function cell(mixed $value): string
    {
        $text = match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            is_array($value) => implode(' | ', array_map(fn ($item) => is_scalar($item) ? (string) $item : (string) json_encode($item), $value)),
            default => (string) $value,
        };

        return preg_match('/^[=+\-@\t\r]/', $text) ? "'".$text : $text;
    }
}
