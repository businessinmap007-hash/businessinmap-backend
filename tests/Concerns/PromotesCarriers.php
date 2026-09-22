<?php

namespace Tests\Concerns;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A freelance driver is a Shipping & Delivery business account, not an
 * ordinary customer (Api\V2\DeliveryController::register enforces it) - so
 * tests turn the account they want registered into one first.
 */
trait PromotesCarriers
{
    private const SHIPPING_ROOT_SLUG = 'shipping-delivery';

    protected function promoteToCarrier(User $user): User
    {
        $user->type = User::TYPE_BUSINESS;
        $user->category_id = (int) \App\Models\Category::query()->where('slug', self::SHIPPING_ROOT_SLUG)->value('id');
        $user->save();

        return $user;
    }

    /** Turns the token's owner into a carrier account, then acts as them. */
    protected function carrierActing(string $token): self
    {
        $owner = PersonalAccessToken::findToken($token)?->tokenable;
        $this->promoteToCarrier($owner);

        return $this->actingWithToken($token);
    }
}
