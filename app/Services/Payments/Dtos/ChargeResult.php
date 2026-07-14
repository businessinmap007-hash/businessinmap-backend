<?php

namespace App\Services\Payments\Dtos;

/**
 * The output of a gateway charge init: what the mobile app needs to open the
 * hosted checkout (URL + the signed charge payload it POSTs to the gateway).
 */
final class ChargeResult
{
    /** @param array<string,mixed> $chargeRequest */
    public function __construct(
        public readonly string $gateway,
        public readonly string $merchantRef,
        public readonly string $initUrl,
        public readonly array $chargeRequest,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'gateway' => $this->gateway,
            'merchant_ref' => $this->merchantRef,
            'init_url' => $this->initUrl,
            'charge_request' => $this->chargeRequest,
        ];
    }
}
