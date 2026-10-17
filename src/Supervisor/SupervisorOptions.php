<?php

namespace IsoDoss\Meridian\Supervisor;

class SupervisorOptions
{
    /**
     * @param  array<int, string>  $queues
     */
    public function __construct(
        public string $name,
        public string $connection = 'database',
        public array $queues = ['default'],
        public string|false $balance = 'auto',
        public string $autoScalingStrategy = 'time',
        public int $minProcesses = 1,
        public int $maxProcesses = 1,
        public int $balanceCooldown = 3,
        public int $balanceMaxShift = 1,
        public int $tries = 1,
        public int $timeout = 60,
        public int $memory = 128,
        public int $sleep = 3,
        public int $backoff = 0,
        public int $maxTime = 0,
        public int $maxJobs = 0,
        public int $rest = 0,
        public int $nice = 0,
        public bool $force = false,
    ) {
        $this->minProcesses = max(0, $this->minProcesses);
        $this->maxProcesses = max(1, $this->maxProcesses, $this->minProcesses);
        $this->balanceMaxShift = max(1, $this->balanceMaxShift);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(string $name, array $config): self
    {
        $queue = $config['queue'] ?? ['default'];

        $balance = $config['balance'] ?? 'auto';

        return new self(
            name: $name,
            connection: (string) ($config['connection'] ?? 'database'),
            queues: array_values(array_filter(is_array($queue) ? $queue : explode(',', (string) $queue))),
            balance: in_array($balance, ['auto', 'simple'], true) ? $balance : false,
            autoScalingStrategy: (string) ($config['autoScalingStrategy'] ?? 'time'),
            minProcesses: (int) ($config['minProcesses'] ?? 1),
            maxProcesses: (int) ($config['maxProcesses'] ?? 1),
            balanceCooldown: (int) ($config['balanceCooldown'] ?? 3),
            balanceMaxShift: (int) ($config['balanceMaxShift'] ?? 1),
            tries: (int) ($config['tries'] ?? 1),
            timeout: (int) ($config['timeout'] ?? 60),
            memory: (int) ($config['memory'] ?? 128),
            sleep: (int) ($config['sleep'] ?? 3),
            backoff: (int) ($config['backoff'] ?? 0),
            maxTime: (int) ($config['maxTime'] ?? 0),
            maxJobs: (int) ($config['maxJobs'] ?? 0),
            rest: (int) ($config['rest'] ?? 0),
            nice: (int) ($config['nice'] ?? 0),
            force: (bool) ($config['force'] ?? false),
        );
    }

    /**
     * Build the options of every supervisor configured for an environment.
     *
     * @return array<int, self>
     */
    public static function forEnvironment(string $environment): array
    {
        $defaults = config('meridian.defaults', []);
        $supervisors = config("meridian.environments.{$environment}")
            ?? config('meridian.environments.*', []);

        return collect($supervisors)
            ->map(fn ($config, $name) => self::fromArray(
                (string) $name,
                array_merge($defaults[$name] ?? [], (array) $config)
            ))
            ->values()
            ->all();
    }

    /**
     * Queue strings handled by separate process pools.
     *
     * @return array<int, string>
     */
    public function pools(): array
    {
        return $this->balance === false
            ? [implode(',', $this->queues)]
            : $this->queues;
    }

    public function autoScaling(): bool
    {
        return $this->balance === 'auto';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
