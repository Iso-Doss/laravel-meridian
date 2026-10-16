<?php

namespace Kakodjenou\Meridian\Supervisor;

/**
 * Computes how many processes each pool of a supervisor should have.
 *
 * Same idea as Horizon's AutoScaler, but the workload comes from the
 * "jobs" table instead of Redis.
 */
class Balancer
{
    /**
     * @param  array<string, array{size: int, runtime: float}>  $workload  keyed by pool queue string
     * @param  array<string, int>  $current  current process count per pool
     * @return array<string, int> desired process count per pool
     */
    public function balance(SupervisorOptions $options, array $workload, array $current): array
    {
        $pools = array_keys($current);
        $count = count($pools);

        if ($count === 0) {
            return [];
        }

        if ($options->balance === false) {
            return [$pools[0] => $options->maxProcesses];
        }

        if ($options->balance === 'simple') {
            return $this->distribute($pools, array_fill_keys($pools, 1), $options->maxProcesses, 0);
        }

        $weights = [];

        foreach ($pools as $pool) {
            $size = $workload[$pool]['size'] ?? 0;
            $runtime = max(1.0, $workload[$pool]['runtime'] ?? 1.0);

            $weights[$pool] = $options->autoScalingStrategy === 'size' ? $size : $size * $runtime;
        }

        $desired = $this->distribute($pools, $weights, $options->maxProcesses, $options->minProcesses);

        // Move progressively towards the target (balanceMaxShift per cycle).
        $result = [];
        $total = array_sum($current);

        foreach ($desired as $pool => $target) {
            $now = $current[$pool];

            if ($target > $now) {
                $room = max(0, $options->maxProcesses - $total);
                $step = min($options->balanceMaxShift, $target - $now, $room);
                $result[$pool] = $now + $step;
                $total += $step;
            } else {
                $step = min($options->balanceMaxShift, $now - $target);
                $result[$pool] = $now - $step;
                $total -= $step;
            }
        }

        return $result;
    }

    /**
     * Give every pool $min processes, then share the remaining processes
     * proportionally to the weights (largest remainder method).
     *
     * @param  array<int, string>  $pools
     * @param  array<string, float|int>  $weights
     * @return array<string, int>
     */
    protected function distribute(array $pools, array $weights, int $max, int $min): array
    {
        $count = count($pools);
        $min = min($min, intdiv($max, $count) ?: ($min > 0 ? 1 : 0));
        $result = array_fill_keys($pools, $min);
        $remaining = max(0, $max - $min * $count);
        $totalWeight = array_sum($weights);

        if ($remaining === 0 || $totalWeight <= 0) {
            return $result;
        }

        $fractions = [];

        foreach ($pools as $pool) {
            $exact = $remaining * ($weights[$pool] ?? 0) / $totalWeight;
            $result[$pool] += (int) floor($exact);
            $fractions[$pool] = $exact - floor($exact);
        }

        $left = $max - array_sum($result);
        arsort($fractions);

        foreach (array_keys($fractions) as $pool) {
            if ($left <= 0) {
                break;
            }

            if (($weights[$pool] ?? 0) > 0) {
                $result[$pool]++;
                $left--;
            }
        }

        return $result;
    }
}
