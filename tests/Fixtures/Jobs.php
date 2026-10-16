<?php

namespace Kakodjenou\Meridian\Tests\Fixtures;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Kakodjenou\Meridian\Contracts\Silenced;
use RuntimeException;

class SuccessfulJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?User $user = null) {}

    public function handle(): void
    {
        usleep(1000);
    }
}

class TaggedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void {}

    public function tags(): array
    {
        return ['invoices', 'customer:42'];
    }
}

class FailingJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 1;

    public function handle(): void
    {
        throw new RuntimeException('Boom from FailingJob');
    }
}

class RetriedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 3;

    public $backoff = 0;

    public function handle(): void
    {
        if ($this->attempts() < 2) {
            throw new RuntimeException('First attempt fails');
        }
    }
}

class SilencedJob implements ShouldQueue, Silenced
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void {}
}
