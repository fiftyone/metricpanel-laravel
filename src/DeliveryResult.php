<?php

declare(strict_types=1);
namespace MetricPanel\Laravel;

/** Safe diagnostics: no token, full payload or player identifier. */
final class DeliveryResult
{
    public function __construct(
        public readonly string $eventId,
        public readonly bool $delivered,
        public readonly ?bool $created = null,
        public readonly ?string $errorCode = null,
    ) {}
}
