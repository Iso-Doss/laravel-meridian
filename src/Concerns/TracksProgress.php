<?php

namespace IsoDoss\Meridian\Concerns;

use Illuminate\Support\Str;
use IsoDoss\Meridian\Listeners\RecordJobLifecycle;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Support\Migrations;
use Throwable;

/**
 * Lets a job report its progress, shown with an estimated remaining time in
 * the dashboard while it runs, and stop when it is cancelled from the
 * dashboard. The job must use InteractsWithQueue.
 *
 *     $this->progressOf($imported, $total, "{$imported} rows imported");
 *
 *     if ($this->cancelled()) {
 *         return;
 *     }
 */
trait TracksProgress
{
    /**
     * The progress last written, to skip the writes that change nothing.
     *
     * @var array{0: int, 1: string|null}|null
     */
    private ?array $meridianReportedProgress = null;

    /**
     * When the cancellation was last checked, to query at most once per second.
     */
    private float $meridianCancellationCheckedAt = 0;

    /**
     * Report the progress of the job, in percent (0 to 100).
     */
    public function progress(int|float $percent, ?string $message = null): void
    {
        $percent = (int) max(0, min(100, floor($percent)));
        $message = $message === null ? null : Str::limit($message, 250);

        if ([$percent, $message] === $this->meridianReportedProgress) {
            return;
        }

        $uuid = isset($this->job) && method_exists($this->job, 'uuid') ? $this->job->uuid() : null;

        if (! $uuid || ! Migrations::hasJobInsights()) {
            return;
        }

        $this->meridianReportedProgress = [$percent, $message];

        try {
            MonitoredJob::query()->where('uuid', $uuid)->update([
                'progress' => $percent,
                'progress_message' => $message,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Whether the job was cancelled from the dashboard: return from handle()
     * when it is, Meridian then forgets the job. Checked at most once per second.
     */
    public function cancelled(): bool
    {
        $uuid = isset($this->job) && method_exists($this->job, 'uuid') ? $this->job->uuid() : null;

        if (! $uuid || ! Migrations::hasCancellation() || microtime(true) - $this->meridianCancellationCheckedAt < 1) {
            return false;
        }

        $this->meridianCancellationCheckedAt = microtime(true);

        try {
            $cancelled = MonitoredJob::query()->where('uuid', $uuid)->whereNotNull('cancel_requested_at')->exists();
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        if ($cancelled) {
            app(RecordJobLifecycle::class)->acknowledgeCancellation($uuid);
        }

        return $cancelled;
    }

    /**
     * Report the progress of the job as a number of items done out of a total.
     */
    public function progressOf(int $done, int $total, ?string $message = null): void
    {
        $this->progress($total > 0 ? $done / $total * 100 : 100, $message);
    }
}
