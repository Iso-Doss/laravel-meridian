<?php

namespace IsoDoss\Meridian\Support;

use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

/**
 * Determine the tags of a queued job.
 *
 * Adapted from laravel/horizon: a job may define a "tags()" method, otherwise
 * every Eloquent model held by the job is turned into a "Class:key" tag.
 */
class Tags
{
    /**
     * @return array<int, string>
     */
    public static function for(mixed $job): array
    {
        if (! is_object($job)) {
            return [];
        }

        if ($tags = static::explicitTags($job)) {
            return array_values(array_map('strval', $tags));
        }

        return static::modelsFor(static::targetsFor($job))
            ->map(fn (Model $model) => get_class($model).':'.$model->getKey())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    protected static function explicitTags(object $job): array
    {
        if ($job instanceof CallQueuedListener) {
            $event = static::extractEvent($job);

            $listener = class_exists($job->class)
                ? (new ReflectionClass($job->class))->newInstanceWithoutConstructor()
                : null;

            return collect([$listener, $event])
                ->filter(fn ($target) => is_object($target) && method_exists($target, 'tags'))
                ->map(fn ($target) => $target === $listener ? $target->tags($event) : $target->tags())
                ->collapse()
                ->unique()
                ->values()
                ->all();
        }

        return collect(static::targetsFor($job))
            ->filter(fn ($target) => method_exists($target, 'tags'))
            ->map(fn ($target) => (array) $target->tags())
            ->collapse()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, object>
     */
    public static function targetsFor(object $job): array
    {
        return match (true) {
            $job instanceof BroadcastEvent => [$job->event],
            $job instanceof CallQueuedListener => [static::extractEvent($job)],
            $job instanceof SendQueuedMailable => [$job->mailable],
            $job instanceof SendQueuedNotifications => [$job->notification],
            default => [$job],
        };
    }

    /**
     * @param  array<int, object>  $targets
     * @return Collection<int, Model>
     */
    public static function modelsFor(array $targets)
    {
        return collect($targets)
            ->map(function (object $target) {
                return collect((new ReflectionClass($target))->getProperties())
                    ->map(function (ReflectionProperty $property) use ($target) {
                        if ($property->isStatic() || ! $property->isInitialized($target)) {
                            return [];
                        }

                        $value = $property->getValue($target);

                        return match (true) {
                            $value instanceof Model => [$value],
                            $value instanceof EloquentCollection => $value->all(),
                            default => [],
                        };
                    })
                    ->collapse();
            })
            ->collapse()
            ->filter(fn ($model) => $model instanceof Model && $model->getKey() !== null);
    }

    protected static function extractEvent(CallQueuedListener $job): object
    {
        return isset($job->data[0]) && is_object($job->data[0])
            ? $job->data[0]
            : new stdClass;
    }
}
