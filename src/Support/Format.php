<?php

namespace IsoDoss\Meridian\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Small formatting helpers used by the dashboard views.
 */
class Format
{
    public static function runtime(?int $milliseconds): string
    {
        if ($milliseconds === null) {
            return '—';
        }

        if ($milliseconds < 1000) {
            return $milliseconds.' ms';
        }

        return static::seconds((int) round($milliseconds / 1000), $milliseconds < 60_000 ? $milliseconds / 1000 : null);
    }

    public static function seconds(?int $seconds, ?float $precise = null): string
    {
        if ($seconds === null) {
            return '—';
        }

        if ($precise !== null) {
            return number_format($precise, 2).' s';
        }

        if ($seconds < 60) {
            return $seconds.' s';
        }

        if ($seconds < 3600) {
            return intdiv($seconds, 60).'m '.($seconds % 60).'s';
        }

        return intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m';
    }

    public static function date(?DateTimeInterface $date): string
    {
        return $date ? Carbon::instance($date)->format('Y-m-d H:i:s') : '—';
    }

    public static function ago(?DateTimeInterface $date): string
    {
        return $date ? Carbon::instance($date)->diffForHumans(short: true) : '—';
    }

    public static function bytes(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : round($bytes / 1024).' KB';
    }

    public static function number(int|float $value): string
    {
        return $value >= 10000 ? round($value / 1000, 1).'k' : (string) $value;
    }
}
