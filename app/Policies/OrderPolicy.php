<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Central authorization for placed orders — replaces the inline party checks
 * that lived in OrderController.
 */
class OrderPolicy
{
    /** A user may view an order only if they are a party to it. */
    public function view(User $user, Order $order): bool
    {
        $userId = (int) $user->id;

        return (int) $order->user_id === $userId
            || (int) $order->business_id === $userId
            || $order->participants()->where('user_id', $userId)->exists();
    }
}
