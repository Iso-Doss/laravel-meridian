<?php

namespace IsoDoss\Meridian\Console;

use Illuminate\Console\Command;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Repositories\AuditLogRepository;

class ClearMetricsCommand extends Command
{
    protected $signature = 'meridian:clear-metrics';

    protected $description = 'Delete every metrics snapshot';

    public function handle(): int
    {
        $count = Meridian::table('metrics')->delete();

        app(AuditLogRepository::class)->record('metrics.cleared', null, ['count' => $count]);

        $this->components->info("{$count} metrics snapshot(s) deleted.");

        return self::SUCCESS;
    }
}
