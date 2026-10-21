<?php

namespace IsoDoss\Meridian\Tests\Fixtures;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use IsoDoss\Meridian\Concerns\TracksProgress;
use IsoDoss\Meridian\Contracts\Silenced;
use IsoDoss\Meridian\Models\MonitoredJob;
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

enum Plan: string
{
    case Pro = 'pro';
}

class DataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public User $user, public string $reason, public array $options = [], public Plan $plan = Plan::Pro, protected int $attemptsLeft = 3) {}

    public function handle(): void {}
}

class EncryptedJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public string $secret) {}

    public function handle(): void {}
}

class FlakyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 1;

    public static bool $fails = true;

    public function handle(): void
    {
        if (static::$fails) {
            throw new RuntimeException('Flaky failure');
        }
    }
}

class ProgressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, TracksProgress;

    /**
     * The progress read from the database while the job runs.
     *
     * @var array<int, array{0: int|null, 1: string|null}>
     */
    public static array $seen = [];

    public function handle(): void
    {
        $this->progress(25.7, 'Importing');
        $this->progress(25.9, 'Importing');
        static::$seen[] = $this->read();

        $this->progressOf(3, 4, '3 / 4 rows');
        static::$seen[] = $this->read();
    }

    /**
     * @return array{0: int|null, 1: string|null}
     */
    protected function read(): array
    {
        $job = MonitoredJob::query()->where('uuid', $this->job->uuid())->first();

        return [$job->progress, $job->progress_message];
    }
}

class Operator extends Authenticatable
{
    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = [];
}

class InvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    /**
     * The e-mail addresses the job was handled with.
     *
     * @var array<int, string>
     */
    public static array $sent = [];

    public function __construct(
        public User $user,
        public string $email,
        public int $invoice = 42,
        public array $options = ['copy' => false],
        public bool $notify = true,
        protected ?float $amount = 19.99,
    ) {}

    public function handle(): void
    {
        if (! str_contains($this->email, '@')) {
            throw new \InvalidArgumentException("Invalid e-mail address [{$this->email}].");
        }

        static::$sent[] = $this->email;
    }
}

class SecretJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 1;

    public static array $handled = [];

    public function __construct(public string $token) {}

    public function handle(): void
    {
        if ($this->token === 'expired') {
            throw new RuntimeException('Expired token');
        }

        static::$handled[] = $this->token;
    }
}

class CancellableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, TracksProgress;

    public static ?int $stoppedAt = null;

    public function handle(): void
    {
        foreach (range(1, 5) as $step) {
            if ($this->cancelled()) {
                static::$stoppedAt = $step;

                return;
            }
        }
    }
}
