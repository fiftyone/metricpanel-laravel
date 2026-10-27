<?php

declare(strict_types=1);
namespace MetricPanel\Laravel;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use MetricPanel\Client;
use MetricPanel\DeliveryException;

final class DeliverIdentity implements ShouldQueue
{
    use Queueable, InteractsWithQueue;
    public int $tries = 8;
    public int $timeout = 20;

    public function __construct(public readonly array $event) {}
    public function backoff(): array { return [10, 30, 120, 300, 900, 1800, 3600]; }

    public function handle(Client $client): void
    {
        try {
            $client->identify($this->event);
            event(new DeliveryResult($this->event['eventId'], true, null));
        } catch (DeliveryException $exception) {
            if (!$exception->retryable) {
                $this->fail($exception);
                return;
            }
            if ($exception->retryAfterSeconds !== null) {
                $this->release($exception->retryAfterSeconds);
                return;
            }
            throw $exception;
        } catch (\InvalidArgumentException $exception) {
            $this->fail($exception);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        event(new DeliveryResult((string) ($this->event['eventId'] ?? ''), false, null,
            $exception instanceof DeliveryException ? $exception->errorCode : 'delivery_failed'));
    }
}
