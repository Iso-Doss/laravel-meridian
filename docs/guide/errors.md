# Errors

The « Errors » page groups the failed jobs by cause: the same exception class thrown at the same line, whatever its message (`User 12 not found` and `User 57 not found` are one error). Each group shows the exception, its message and location, the jobs and queues it affects, its number of occurrences and when it was first and last seen. Open a group to list its jobs, or « Retry all » to push them all back onto their queue once the bug is fixed. The page of a failed job links to the other jobs failing with the same error. The groups are also available as JSON (`GET /meridian/api/errors`).

## Retry with corrected data

When a job failed because of an invalid value (a wrong e-mail address, an outdated identifier...), « Edit and retry » on its page lets you change its data and push it back onto its queue. Strings, numbers, booleans and arrays can be changed; models, enums, dates and other objects are kept as they are, and so are the queue settings of the job (`tries`, `timeout`...). Encrypted jobs stay encrypted. Every change is recorded in the audit log.

Changing the data of a job is sensitive, so only the people allowed by the `editMeridianJobs` gate can (and only among those who can act on the jobs). Without the gate, it is only available in the `local` environment:

```php
Gate::define('editMeridianJobs', fn ($user = null) => in_array(optional($user)->email, [
    'taylor@example.com',
]));
```

or `Meridian::editJobs(fn (Request $request) => ...)`.
