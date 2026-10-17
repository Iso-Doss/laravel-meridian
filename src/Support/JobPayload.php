<?php

namespace IsoDoss\Meridian\Support;

use Throwable;

/**
 * Helpers to read the information Meridian needs from a raw queue payload.
 */
class JobPayload
{
    /**
     * Unserialize the job instance from a payload, when possible
     * (not for encrypted jobs or classes unknown to this application).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function command(array $payload): ?object
    {
        $command = $payload['data']['command'] ?? null;

        if (! is_string($command) || ! str_starts_with($command, 'O:')) {
            return null;
        }

        try {
            $object = unserialize($command);

            return is_object($object) && ! $object instanceof \__PHP_Incomplete_Class ? $object : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function batchId(mixed $job): ?string
    {
        return is_object($job) && property_exists($job, 'batchId') ? $job->batchId : null;
    }
}
