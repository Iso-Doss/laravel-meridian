<?php

namespace IsoDoss\Meridian\Filament;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use IsoDoss\Meridian\Http\Controllers\DashboardController;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Support\Format;

/**
 * The key figures of the database queues, on a Filament dashboard.
 */
class MeridianStatsWidget extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return Meridian::check(request());
    }

    protected function getStats(): array
    {
        $stats = app(DashboardController::class)->stats();

        return [
            Stat::make('Queues', ucfirst($stats['status']))
                ->description(Format::number($stats['jobsPerMinute']).' jobs per minute')
                ->color($stats['status'] === 'running' ? 'success' : ($stats['status'] === 'paused' ? 'warning' : 'danger'))
                ->url(route('meridian.dashboard'), shouldOpenInNewTab: true),
            Stat::make('Pending jobs', number_format($stats['pendingJobs']))
                ->description($stats['maxWait'] ? 'longest wait '.Format::seconds($stats['maxWait']['seconds']) : 'no job waiting')
                ->url(route('meridian.jobs.index', MonitoredJob::PENDING), shouldOpenInNewTab: true),
            Stat::make('Processing', number_format($stats['processing']))
                ->description($stats['processes'].' live worker(s)'),
            Stat::make('Failed jobs', number_format($stats['failedJobs']))
                ->description('past 7 days')
                ->color($stats['failedJobs'] > 0 ? 'danger' : 'success')
                ->url(route('meridian.errors.index'), shouldOpenInNewTab: true),
        ];
    }
}
