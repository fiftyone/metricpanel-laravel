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
