# The dashboard

The dashboard is available at `/meridian`. Its pages refresh themselves every `meridian.poll` seconds (switch « Live » in the header).

## Dashboard authorization

In the `local` environment everybody can open the dashboard. In the other environments, access is granted by the `viewMeridian` gate of `app/Providers/MeridianServiceProvider.php`:

```php
protected function gate(): void
{
    Gate::define('viewMeridian', function ($user = null) {
        return in_array(optional($user)->email, [
            'taylor@example.com',
        ]);
    });
}
```

You may also call `Meridian::auth(fn (Request $request) => ...)` directly. The prefix, domain and middleware of the dashboard and of its API are configured in the `route` option (`MERIDIAN_PREFIX`, `MERIDIAN_API_PREFIX`, `MERIDIAN_DOMAIN`); the authorization middleware is always added on top of them.

## Read-only access

By default, everybody who can open the dashboard can also retry, delete and cancel jobs, and monitor tags. To give some people a read-only access, define the `manageMeridian` gate in `app/Providers/MeridianServiceProvider.php`:

```php
protected function gate(): void
{
    Gate::define('viewMeridian', fn ($user = null) => in_array(optional($user)->email, [
        'taylor@example.com',
        'support@example.com',
    ]));

    // Only Taylor can act on the jobs, the support team can only look.
    Gate::define('manageMeridian', fn ($user = null) => in_array(optional($user)->email, [
        'taylor@example.com',
    ]));
}
```

or call `Meridian::manage(fn (Request $request) => ...)`. The read-only users browse every page, but the buttons that act on the jobs are hidden and their requests are refused (403). The page header shows « Read only ».

## Audit log

The « Audit log » page lists who retried, deleted or cancelled a job, retried a batch or a group of failed jobs, monitored a tag, and who paused, resumed, terminated or cleared Meridian from the command line, with the date, the IP address and the details of each action. Users are named by their `email`, else their `name`, else their identifier; Artisan commands appear as « Console ». The entries are kept `trim.audit` minutes (30 days by default) and are also available as JSON (`GET /meridian/api/audit`).

## Job origin

Each job records who dispatched it: the HTTP request (method, path and route name, never the query string, which may carry tokens) and the signed-in user, the artisan command (its name only, not its options), the scheduled task, or the job that dispatched it. The job page shows it under « Dispatched by », with a link to the parent job and its status, and lists the jobs a job dispatched (« Dispatched jobs »), to follow a chain of jobs back to the request that started it. Nothing to change in your code; the jobs recorded before the migration of v1.12.0 have no origin.

## Sensitive data

Before storing a payload, Meridian replaces with `[redacted]` the values of the keys and job properties whose name matches the `redact` option: passwords, secrets, tokens, API keys, `authorization`, card numbers and CVV by default. Case, `_` and `-` are ignored (`api_key` matches `apiKey`) and `*` is a wildcard.

```php
'redact' => ['*password*', '*secret*', '*token*', '*api_key*', 'authorization', 'card_number', 'cvv', 'cvc', 'iban'],
```

The serialized job is rewritten without being unserialized: no code of your application runs. Encrypted jobs (`ShouldBeEncrypted`) are stored as they are, since they are already unreadable. The jobs keep their real values: retrying a job or editing its data starts from the original payload of the failed jobs table, and the redacted fields are never shown nor editable. A job whose payload was redacted and which is no longer in the failed jobs table cannot be retried from Meridian. Set `'redact' => []` to store the payloads untouched, or `store_payload` to `false` to store none.

## Interfaces, themes and languages

Two interfaces ship with Meridian:

- **Inertia** (default): React, Inertia and shadcn/ui. It has its own root view, Inertia middleware and asset version: your application's Inertia setup (shared props, SSR, root view) is never involved, even when it uses Inertia too.
- **Blade**: a lightweight server-rendered interface, with the same pages.

Both offer a light, dark or system theme (kept in the browser) and are translated in English, French, Spanish, Portuguese, German and Arabic (written from right to left): the language follows the application locale and can be changed from the interface (kept in a cookie). Restrict the offered languages with the `locales` option.

To use the Blade interface, set in your `.env`:

```dotenv
MERIDIAN_UI=blade
```

or `'ui' => 'blade'` in a published `config/meridian.php` (republish it with `php artisan vendor:publish --tag=meridian-config --force` to get the keys added since your version), then `php artisan config:clear` if the configuration is cached. Both interfaces share the same URLs, route names, actions and JSON API.

## Export

The lists of jobs and the errors can be downloaded as **CSV** (for a spreadsheet) or **JSON**, with the filters of the list (status, queue, search, tag, error): « Export » at the top of the page, or `GET /meridian/jobs/export?status=failed&format=csv` and `GET /meridian/errors/export?format=json`. The exports are streamed, whatever their size, and the CSV cells that a spreadsheet would run as formulas are neutralized.

## Content-Security-Policy

Meridian works under a strict, nonce-based CSP such as `script-src 'nonce-…' 'strict-dynamic' 'self'`: when your application sets a nonce with `Vite::useCspNonce()`, Meridian puts it on its scripts and styles and passes it to Inertia. There is nothing to configure, and no need to exempt Meridian's routes from your policy.

## JSON API

The dashboard data is also available as JSON, behind the same authorization: `GET /meridian/api/stats`, `/meridian/api/workload`, `/meridian/api/jobs/{status?}?search=&queue=` and `/meridian/api/jobs/{uuid}`.
