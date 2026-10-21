<?php

namespace IsoDoss\Meridian\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Formatting helpers of the Blade interface, in the visitor's language.
 */
class Format
{
    /**
     * English labels of the statuses (translated by the views).
     */
    public const STATUSES = [
        'pending' => 'Pending',
        'delayed' => 'Delayed',
        'processing' => 'Processing',
        'working' => 'Working',
        'paused' => 'Paused',
        'completed' => 'Completed',
        'running' => 'Running',
        'finished' => 'Finished',
        'idle' => 'Idle',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'silenced' => 'Silenced',
        'inactive' => 'Inactive',
        'success' => 'Succeeded',
        'skipped' => 'Skipped',
        'missed' => 'Missed',
        'never' => 'Never ran',
        'cancelling' => 'Cancelling…',
    ];

    public static function status(string $status): string
    {
        return self::STATUSES[$status] ?? ucfirst($status);
    }

    public static function integer(int|float $value, string $locale = 'en'): string
    {
        return number_format($value, 0, static::decimalSeparator($locale), static::thousandsSeparator($locale));
    }

    public static function decimal(int|float $value, int $decimals, string $locale = 'en'): string
    {
        $formatted = number_format($value, $decimals, static::decimalSeparator($locale), static::thousandsSeparator($locale));

        return str_contains($formatted, static::decimalSeparator($locale))
            ? rtrim(rtrim($formatted, '0'), static::decimalSeparator($locale))
            : $formatted;
    }

    /**
     * Short form of big numbers: "12.3k".
     */
    public static function number(int|float $value, string $locale = 'en'): string
    {
        return $value >= 10000
            ? static::decimal($value / 1000, 1, $locale).'k'
            : static::decimal($value, 2, $locale);
    }

    /**
     * Milliseconds: "120 ms", "1.25 s", "2 min 5 s".
     */
    public static function runtime(?int $milliseconds, string $locale = 'en'): string
    {
        if ($milliseconds === null) {
            return '—';
        }

        if ($milliseconds < 1000) {
            return static::integer($milliseconds, $locale).' ms';
        }

        if ($milliseconds < 60_000) {
            return static::decimal($milliseconds / 1000, 2, $locale).' s';
        }

        return static::seconds((int) round($milliseconds / 1000));
    }

    /**
     * Seconds: "45 s", "2 min 5 s", "1 h 4 min".
     */
    public static function seconds(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        if ($seconds < 60) {
            return $seconds.' s';
        }

        if ($seconds < 3600) {
            return intdiv($seconds, 60).' min '.($seconds % 60).' s';
        }

        return intdiv($seconds, 3600).' h '.intdiv($seconds % 3600, 60).' min';
    }

    public static function date(?DateTimeInterface $date, string $locale = 'en'): string
    {
        return $date ? Carbon::instance($date)->format(in_array($locale, ['fr', 'es', 'pt'], true) ? 'd/m/Y H:i:s' : ($locale === 'de' ? 'd.m.Y H:i:s' : 'Y-m-d H:i:s')) : '—';
    }

    public static function time(int $timestamp): string
    {
        return date('H:i', $timestamp);
    }

    /**
     * "3 minutes ago" / "il y a 3 minutes".
     */
    public static function ago(?DateTimeInterface $date, string $locale = 'en'): string
    {
        return $date ? Carbon::instance($date)->locale($locale)->diffForHumans() : '—';
    }

    public static function bytes(int $bytes, string $locale = 'en'): string
    {
        return $bytes >= 1048576
            ? static::decimal($bytes / 1048576, 1, $locale).' MB'
            : static::integer(round($bytes / 1024), $locale).' KB';
    }

    protected static function decimalSeparator(string $locale): string
    {
        return in_array($locale, ['fr', 'es', 'pt', 'de'], true) ? ',' : '.';
    }

    protected static function thousandsSeparator(string $locale): string
    {
        return match ($locale) {
            'fr' => "\u{202F}",
            'es', 'pt', 'de' => '.',
            default => ',',
        };
    }
}
