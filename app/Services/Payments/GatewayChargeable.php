<?php

namespace App\Services\Payments;

/**
 * Anything a payment gateway can build a charge for. Both a wallet top-up
 * (customer funds own points) and a merchant payment (customer pays a merchant)
 * implement this, so FawryGateway::createCharge is decoupled from either concrete
 * model — it reads only what a charge needs.
 */
interface GatewayChargeable
{
    /** Our unique reference sent to the gateway (the row id as a string). */
    public function chargeRef(): string;

    /** Amount to charge. */
    public function chargeAmount(): float;

    /** The gateway "customer profile" id — the paying customer. */
    public function chargeCustomerRef(): string;

    /** A line-item id, e.g. "WT-123" / "MP-123". */
    public function chargeItemId(): string;

    /** A human line-item description. */
    public function chargeDescription(): string;
}
