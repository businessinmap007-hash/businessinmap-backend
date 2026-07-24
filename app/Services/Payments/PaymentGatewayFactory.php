<?php

namespace App\Services\Payments;

use InvalidArgumentException;

/**
 * Resolves a payment gateway by name from config. One place to add Paymob etc.
 * later without touching the top-up controller.
 */
final class PaymentGatewayFactory
{
    public function __construct(
        private readonly PaymentSettingsService $settings,
        private readonly MerchantPaymentAccountService $merchants,
    ) {
    }

    public function make(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?: (string) config('services.payments.default_gateway', 'fawry');

        return match ($name) {
            // Env baseline overlaid with any admin-pasted credentials from the DB.
            'fawry' => new FawryGateway($this->settings->fawryConfig()),
            default => throw new InvalidArgumentException("Unknown payment gateway [{$name}]."),
        };
    }

    /**
     * A gateway that bills a specific merchant's sub-account, or null when the
     * sub-account feature is off / the merchant is not configured (the caller
     * then falls back to make() = the platform account). The gateway carries the
     * merchant's own merchant_code + security_key, so every charge it builds is
     * signed for and settled to the merchant.
     */
    public function makeForMerchant(int $businessId): ?PaymentGatewayInterface
    {
        $config = $this->merchants->configFor($businessId);

        return $config === null ? null : new FawryGateway($config);
    }
}
