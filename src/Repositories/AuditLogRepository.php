<?php

namespace IsoDoss\Meridian\Repositories;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Support\Migrations;
use Throwable;

/**
 * Who retried, deleted, paused or cleared what, and when (meridian_audit_logs).
 */
class AuditLogRepository
{
    /**
     * The recorded actions and their label (English, translated by the interface).
     */
    public const ACTIONS = [
        'job.retried' => 'Job retried',
        'job.edited' => 'Job edited and retried',
        'jobs.retried' => 'Failed jobs retried',
        'job.cancelled' => 'Job cancelled',
        'job.deleted' => 'Job deleted',
        'batch.retried' => 'Batch retried',
        'tag.monitored' => 'Tag monitored',
        'tag.unmonitored' => 'Tag no longer monitored',
        'meridian.paused' => 'Meridian paused',
        'meridian.continued' => 'Meridian resumed',
        'meridian.terminated' => 'Meridian terminated',
        'supervisor.paused' => 'Supervisor paused',
        'supervisor.continued' => 'Supervisor resumed',
        'jobs.forgotten' => 'Failed jobs deleted',
        'queue.cleared' => 'Queue cleared',
        'metrics.cleared' => 'Metrics cleared',
    ];

    /**
     * Record an action. Recording never breaks the action itself.
     *
     * @param  array<string, scalar|null>  $details
     */
    public function record(string $action, ?string $subject = null, array $details = []): void
    {
        if (! Migrations::hasAuditLog()) {
            return;
        }

        try {
            $request = app()->bound('request') ? request() : null;
            $http = $request?->route() !== null;

            Meridian::table('audit_logs')->insert([
                'action' => $action,
                'subject' => $subject === null ? null : mb_substr($subject, 0, 255),
                'details' => ($details = array_filter($details, fn ($value) => $value !== null && $value !== '')) === [] ? null : json_encode($details),
                'user' => $http ? static::identify($request->user()) : 'console',
                'ip' => $http ? $request->ip() : null,
                'created_at' => time(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The entries, the latest first, as arrays (action, label, subject,
     * details, user, ip, created_at).
     */
    public function paginate(?string $action = null, int $perPage = 50): LengthAwarePaginator
    {
        $paginator = Meridian::table('audit_logs')
            ->when($action, fn ($query) => $query->where('action', $action))
            ->orderByDesc('id')
            ->paginate($perPage);

        $paginator->setCollection($paginator->getCollection()->map(fn ($row) => $this->present($row)));

        return $paginator;
    }

    /**
     * @return array{action: string, label: string, subject: string|null, details: array<string, mixed>, user: string|null, ip: string|null, created_at: int}
     */
    protected function present(object $row): array
    {
        $action = (string) $row->action;

        return [
            'action' => $action,
            'label' => self::ACTIONS[$action] ?? $action,
            'subject' => $row->subject,
            'details' => (array) json_decode((string) $row->details, true),
            'user' => $row->user,
            'ip' => $row->ip,
            'created_at' => (int) $row->created_at,
        ];
    }

    public function prune(int $before): int
    {
        return Migrations::hasAuditLog() ? Meridian::table('audit_logs')->where('created_at', '<', $before)->delete() : 0;
    }

    /**
     * The name shown for a user: its e-mail, else its name, else its identifier.
     */
    public static function identify(?Authenticatable $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $name = $user->email ?? $user->name ?? $user->getAuthIdentifier();

        return $name === null ? null : (string) $name;
    }
}
