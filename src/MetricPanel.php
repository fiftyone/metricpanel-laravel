<?php

declare(strict_types=1);
namespace MetricPanel\Laravel;

use Illuminate\Contracts\Bus\Dispatcher;

final class MetricPanel
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly string $connection,
        private readonly string $queue,
        private readonly string $driver,
    ) {}

    public function igaming(array $event): void
    {
        // Sync/deferred/background drivers can run in the payment process. Fail closed.
        if (!in_array($this->driver, ['redis', 'database', 'sqs', 'beanstalkd'], true)) {
            throw new \LogicException('MetricPanel requires a durable asynchronous queue connection.');
        }
        $job = (new DeliverIgamingEvent($event))->onConnection($this->connection)->onQueue($this->queue)->afterCommit();
        $this->dispatcher->dispatch($job);
    }

    public function identify(array $event): void
    {
        if (!in_array($this->driver, ['redis', 'database', 'sqs', 'beanstalkd'], true)) {
            throw new \LogicException('MetricPanel requires a durable asynchronous queue connection.');
        }
        $job = (new DeliverIdentity($event))->onConnection($this->connection)->onQueue($this->queue)->afterCommit();
        $this->dispatcher->dispatch($job);
    }
}
