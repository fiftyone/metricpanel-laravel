# metricpanel/laravel

Laravel 11–13 service provider and asynchronous iGaming delivery, using `metricpanel/php`.

```sh
composer require metricpanel/laravel:^0.1
```

For local development, configure both packages as Composer path repositories with version
`0.1.0`; see [the PHP package README](https://github.com/fiftyone/metricpanel-php).

Publish `metricpanel-config`, configure the server token and durable queue, then inject
`MetricPanel\Laravel\MetricPanel` and call `igaming($immutableEvent)`. Jobs dispatch after
commit, with eight attempts, exponential-style backoff and Retry-After support. Configure
queue retry_after above the 20-second job timeout, run a worker on the configured queue,
and configure Laravel failed-job storage. Inspect `DeliveryResult` events for diagnostics.
Never use a sync, deferred or background queue for payments.

For authoritative financial activity, an application transaction must also persist an outbox
record, and a periodic worker must recover pending records. After-commit queue dispatch alone
cannot cover a process crash immediately after commit. Existing outbox workers should inject
`MetricPanel\Client` directly and own acknowledgement, leases and retries.

The test script uses actual Laravel queue classes and only `sqlite::memory:`. It never reads
application environment settings. Install `illuminate/config`, `illuminate/database`,
`illuminate/events` and this package in an isolated Composer harness, then run:

```sh
php tests/run.php /path/to/harness/vendor/autoload.php
```

Validated against Laravel 12.69.3 on PHP 8.4.25. The script covers commit/rollback, credential-free
serialization, immutable retries, Retry-After, permanent failure and diagnostic events.

## User identification (0.2.0)

Requires the identity-enabled MetricPanel API and website cookieless/GDPR modes disabled.
Use the visitor and session IDs supplied by your frontend SDK; do not substitute the user ID
for either. Persist the event ID and timestamp with the payload and reuse them on retries.

```php
$identity = [
    'eventId' => 'stable-identify-event-123',
    'websiteId' => 'your-public-website-id',
    'visitorId' => $browserVisitorId,
    'sessionId' => $browserSessionId,
    'timestamp' => '2026-10-27T10:00:00Z',
    'userId' => 'user_123',
    'name' => 'Jane Smith',
    'email' => 'jane@example.com',
];
app(\MetricPanel\Laravel\MetricPanel::class)->identify($identity);
```

Name and email are optional; `null` clears a trait. Identification records no conversion or
revenue. It links existing visitor activity to a website-scoped profile. A different user cannot
reuse the same visitor ID (HTTP 409); reset the frontend SDK on logout/account switching first.
This is not an authentication mechanism. The API token requires `events:write` access.

Laravel queues identification after commit on the configured durable connection, retries
transient failures and emits the existing DeliveryResult diagnostics. Profile traits are in
the queued payload; API credentials and response bodies are excluded from diagnostics.
