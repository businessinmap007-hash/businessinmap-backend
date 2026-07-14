<?php

namespace App\Services\Payments\Dtos;

/**
 * Normalized view of a gateway server-to-server callback, gateway-agnostic.
 */
final class CallbackResult
{
    public const STATUS_PAID    = 'paid';
    public const STATUS_FAILED  = 'failed';
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNKNOWN = 'unknown';

    /** @param array<string,mixed> $raw */
    public function __construct(
        public readonly string $merchantRef,
        public readonly ?string $gatewayRef,
        public readonly string $status,
        public readonly ?float $amount,
        public readonly ?string $method,
        public readonly array $raw = [],
    ) {
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
