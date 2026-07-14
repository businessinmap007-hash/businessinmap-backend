<?php

namespace App\Services\Payments;

use InvalidArgumentException;

/**
 * Resolves a payment gateway by name from config. One place to add Paymob etc.
 * later without touching the top-up controller.
 */
final class PaymentGatewayFactory
{
    public function make(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?: (string) config('services.payments.default_gateway', 'fawry');

        return match ($name) {
            'fawry' => new FawryGateway((array) config('services.fawry', [])),
            default => throw new InvalidArgumentException("Unknown payment gateway [{$name}]."),
        };
    }
}
