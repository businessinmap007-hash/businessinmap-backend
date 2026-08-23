<?php

namespace App\Services\Offers;

use Illuminate\Database\Eloquent\Model;

/**
 * The priced row an offer is about, once it has been found and vouched for.
 *
 * An offer used to carry a `base_price` the API took from the request. This is
 * what replaces it: the actual row, the actual number, and the business that
 * actually owns it.
 */
final class OfferableRow
{
    public function __construct(
        public readonly Model $model,
        public readonly int $businessId,
        public readonly float $price,
        public readonly string $currency,
        public readonly string $label,
    ) {
    }
}
