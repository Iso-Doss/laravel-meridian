<?php

namespace IsoDoss\Meridian\Support;

use Illuminate\Support\Str;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Repositories\AuditLogRepository;
use Throwable;

/**
 * Who dispatched a job: an HTTP request, an artisan command, a scheduled
 * task or another job (its parent).
 */
class JobOrigin
{
    public const HTTP = 'http';

    public const CONSOLE = 'console';

    public const SCHEDULE = 'schedule';

    public const JOB = 'job';

    /**
     * English labels of the types (translated by the views).
     */
    public const TYPES = [
        self::HTTP => 'HTTP request',
        self::CONSOLE => 'Command',
        self::SCHEDULE => 'Scheduled task',
        self::JOB => 'Job',
    ];

    /**
     * The job being processed by this worker.
     *
     * @var array{uuid: string, name: string}|null
     */
    protected static ?array $job = null;

    /**
     * The scheduled task running in this process.
     */
    protected static ?string $task = null;

    /**
     * The artisan commands running in this process (Artisan::call() nests them).
     *
     * @var array<int, string>
     */
    protected static array $commands = [];

    public static function enterJob(string $uuid, string $name): void
    {
        static::$job = ['uuid' => $uuid, 'name' => $name];
    }

    public static function leaveJob(): void
    {
        static::$job = null;
    }

    public static function flush(): void
    {
        static::$job = null;
        static::$task = null;
        static::$commands = [];
    }

    public static function enterCommand(string $name): void
    {
        static::$commands[] = $name;
    }

    public static function leaveCommand(): void
    {
        array_pop(static::$commands);
    }

    public static function enterTask(string $name): void
    {
        static::$task = $name;
    }

    public static function leaveTask(): void
    {
        static::$task = null;
    }

    /**
     * The columns describing the origin of a job dispatched now.
     *
     * @return array{origin_type: string|null, origin: string|null, origin_user: string|null, parent_uuid: string|null}
     */
    public static function current(): array
    {
        $origin = ['origin_type' => null, 'origin' => null, 'origin_user' => null, 'parent_uuid' => null];

        try {
            if (static::$job !== null) {
                return array_merge($origin, ['origin_type' => self::JOB, 'origin' => static::$job['name'], 'parent_uuid' => static::$job['uuid']]);
            }

            if (static::$task !== null) {
                return array_merge($origin, ['origin_type' => self::SCHEDULE, 'origin' => static::limit(static::$task)]);
            }

            // The name of the command only: its options may carry secrets.
            if (static::$commands !== []) {
                return array_merge($origin, ['origin_type' => self::CONSOLE, 'origin' => static::limit('artisan '.end(static::$commands))]);
            }

            $request = app()->bound('request') ? request() : null;
            $route = $request?->route();

            if ($request === null || ($route === null && app()->runningInConsole())) {
                return array_merge($origin, ['origin_type' => self::CONSOLE, 'origin' => static::limit(basename((string) ($_SERVER['argv'][0] ?? 'php')))]);
            }

            // The path only: the query string may carry tokens.
            return array_merge($origin, [
                'origin_type' => self::HTTP,
                'origin' => static::limit($request->method().' /'.ltrim($request->path(), '/').(is_object($route) && $route->getName() ? ' ('.$route->getName().')' : '')),
                'origin_user' => AuditLogRepository::identify($request->user()),
            ]);
        } catch (Throwable) {
            return $origin;
        }
    }

    /**
     * The origin of a recorded job, for the views.
     *
     * @return array{type: string, label: string, user: string|null, parent: array{uuid: string, name: string, status: string|null, url: string}|null}|null
     */
    public static function describe(MonitoredJob $job): ?array
    {
        if (! Migrations::hasOrigin() || $job->origin_type === null) {
            return null;
        }

        $parent = null;

        if ($job->parent_uuid) {
            $status = MonitoredJob::query()->where('uuid', $job->parent_uuid)->value('status');
            $parent = [
                'uuid' => $job->parent_uuid,
                'name' => (string) $job->origin,
                'status' => $status,
                'url' => route('meridian.jobs.show', $job->parent_uuid),
            ];
        }

        return [
            'type' => $job->origin_type,
            'label' => (string) $job->origin,
            'user' => $job->origin_user,
            'parent' => $parent,
        ];
    }

    /**
     * The jobs a job dispatched, the latest first.
     *
     * @return array{count: int, jobs: array<int, array{uuid: string, name: string, status: string, queued_at: int|null, url: string}>}
     */
    public static function children(MonitoredJob $job, int $limit = 20): array
    {
        if (! Migrations::hasOrigin()) {
            return ['count' => 0, 'jobs' => []];
        }

        $query = MonitoredJob::query()->where('parent_uuid', $job->uuid);

        return [
            'count' => (clone $query)->count(),
            'jobs' => $query->orderByDesc('id')->limit($limit)->get(['uuid', 'name', 'status', 'queued_at'])
                ->map(fn (MonitoredJob $child) => [
                    'uuid' => $child->uuid,
                    'name' => $child->name,
                    'status' => $child->status,
                    'queued_at' => $child->queued_at->getTimestamp(),
                    'url' => route('meridian.jobs.show', $child->uuid),
                ])
                ->all(),
        ];
    }

    protected static function limit(string $value): string
    {
        return Str::limit($value, 250, '…');
    }
}
