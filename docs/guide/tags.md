# Tags

Meridian tags jobs, mailables, broadcast events, notifications and queued event listeners like Horizon does. The Eloquent models held by a job are tagged automatically: a `RenderVideo` job holding the `App\Models\Video` with the id `1` gets the `App\Models\Video:1` tag. To tag a job yourself, give it a `tags` method:

```php
class RenderVideo implements ShouldQueue
{
    public function tags(): array
    {
        return ['render', 'video:'.$this->video->id];
    }
}
```

Click a tag anywhere in the dashboard to list every job carrying it. The page of a retried job lists every retry with its outcome, runtime and exception. On the failed jobs page, « Retry all » pushes back every failed job matching the current filters (queue, search): retry all the failed jobs of a queue, or of a customer by searching its tag.

## Monitored tags

Like Horizon, the « Monitoring » page lets you watch tags: add `App\Models\User:42` or `customer:42` and its jobs are listed together, with their number of pending and failed jobs. The jobs carrying a monitored tag are kept `trim.monitored` minutes (7 days by default) instead of `trim.completed`.

Monitored tags are stored in the `meridian_monitored_tags` table. When upgrading from a version older than 1.4.0 (monitored tags), 1.5.0 (retry history), 1.7.0 (errors, job progress, audit log) or 1.8.0 (scheduled tasks), publish and run the new migrations:

```bash
php artisan vendor:publish --tag=meridian-migrations
php artisan migrate
```
