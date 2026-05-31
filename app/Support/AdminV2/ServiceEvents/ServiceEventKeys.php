<?php

namespace App\Support\AdminV2\ServiceEvents;

final class ServiceEventKeys
{
    /*
    |--------------------------------------------------------------------------
    | Booking Events
    |--------------------------------------------------------------------------
    */
    public const BOOKING_REQUESTED = 'booking.requested';
    public const BOOKING_ACCEPTED = 'booking.accepted';
    public const BOOKING_REJECTED = 'booking.rejected';
    public const BOOKING_CANCELLED = 'booking.cancelled';
    public const BOOKING_RESCHEDULED = 'booking.rescheduled';
    public const BOOKING_STARTED = 'booking.started';
    public const BOOKING_COMPLETED = 'booking.completed';

    public const BOOKING_CLIENT_CONFIRMED = 'booking.client_confirmed';
    public const BOOKING_BUSINESS_CONFIRMED = 'booking.business_confirmed';

    public const BOOKING_REMINDER_24H = 'booking.reminder_24h';
    public const BOOKING_REMINDER_1H = 'booking.reminder_1h';

    public const BOOKING_DEPOSIT_FROZEN = 'booking.deposit_frozen';
    public const BOOKING_DEPOSIT_RELEASED = 'booking.deposit_released';
    public const BOOKING_DEPOSIT_REFUNDED = 'booking.deposit_refunded';

    public const BOOKING_DISPUTE_OPENED = 'booking.dispute_opened';

    /*
    |--------------------------------------------------------------------------
    | Menu Events - future ready
    |--------------------------------------------------------------------------
    */
    public const MENU_ORDER_CREATED = 'menu.order_created';
    public const MENU_ORDER_ACCEPTED = 'menu.order_accepted';
    public const MENU_ORDER_REJECTED = 'menu.order_rejected';
    public const MENU_ORDER_PREPARING = 'menu.order_preparing';
    public const MENU_ORDER_READY = 'menu.order_ready';
    public const MENU_ORDER_COMPLETED = 'menu.order_completed';
    public const MENU_ORDER_CANCELLED = 'menu.order_cancelled';

    /*
    |--------------------------------------------------------------------------
    | Delivery Events - future ready
    |--------------------------------------------------------------------------
    */
    public const DELIVERY_REQUESTED = 'delivery.requested';
    public const DELIVERY_ACCEPTED = 'delivery.accepted';
    public const DELIVERY_ASSIGNED = 'delivery.assigned';
    public const DELIVERY_PICKED_UP = 'delivery.picked_up';
    public const DELIVERY_DELIVERED = 'delivery.delivered';
    public const DELIVERY_CANCELLED = 'delivery.cancelled';

    /*
    |--------------------------------------------------------------------------
    | Wallet Events
    |--------------------------------------------------------------------------
    */
    public const WALLET_HOLD_CREATED = 'wallet.hold_created';
    public const WALLET_HOLD_RELEASED = 'wallet.hold_released';
    public const WALLET_REFUND_CREATED = 'wallet.refund_created';
    public const WALLET_FEE_CHARGED = 'wallet.fee_charged';
    public const WALLET_TRANSACTION_CREATED = 'wallet.transaction_created';

    /*
    |--------------------------------------------------------------------------
    | Dispute Events
    |--------------------------------------------------------------------------
    */
    public const DISPUTE_OPENED = 'dispute.opened';
    public const DISPUTE_UNDER_REVIEW = 'dispute.under_review';
    public const DISPUTE_RESOLVED = 'dispute.resolved';
    public const DISPUTE_CANCELLED = 'dispute.cancelled';

    /*
    |--------------------------------------------------------------------------
    | Registry
    |--------------------------------------------------------------------------
    */
    public static function all(): array
    {
        return [
            self::BOOKING_REQUESTED,
            self::BOOKING_ACCEPTED,
            self::BOOKING_REJECTED,
            self::BOOKING_CANCELLED,
            self::BOOKING_RESCHEDULED,
            self::BOOKING_STARTED,
            self::BOOKING_COMPLETED,
            self::BOOKING_CLIENT_CONFIRMED,
            self::BOOKING_BUSINESS_CONFIRMED,
            self::BOOKING_REMINDER_24H,
            self::BOOKING_REMINDER_1H,
            self::BOOKING_DEPOSIT_FROZEN,
            self::BOOKING_DEPOSIT_RELEASED,
            self::BOOKING_DEPOSIT_REFUNDED,
            self::BOOKING_DISPUTE_OPENED,

            self::MENU_ORDER_CREATED,
            self::MENU_ORDER_ACCEPTED,
            self::MENU_ORDER_REJECTED,
            self::MENU_ORDER_PREPARING,
            self::MENU_ORDER_READY,
            self::MENU_ORDER_COMPLETED,
            self::MENU_ORDER_CANCELLED,

            self::DELIVERY_REQUESTED,
            self::DELIVERY_ACCEPTED,
            self::DELIVERY_ASSIGNED,
            self::DELIVERY_PICKED_UP,
            self::DELIVERY_DELIVERED,
            self::DELIVERY_CANCELLED,

            self::WALLET_HOLD_CREATED,
            self::WALLET_HOLD_RELEASED,
            self::WALLET_REFUND_CREATED,
            self::WALLET_FEE_CHARGED,
            self::WALLET_TRANSACTION_CREATED,

            self::DISPUTE_OPENED,
            self::DISPUTE_UNDER_REVIEW,
            self::DISPUTE_RESOLVED,
            self::DISPUTE_CANCELLED,
        ];
    }

    public static function isAllowed(string $eventKey): bool
    {
        return in_array($eventKey, self::all(), true);
    }

    public static function split(string $eventKey): array
    {
        $eventKey = trim($eventKey);

        if (! preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', $eventKey)) {
            throw new \InvalidArgumentException("Invalid service event key format: {$eventKey}");
        }

        [$serviceKey, $actionKey] = explode('.', $eventKey, 2);

        return [$serviceKey, $actionKey];
    }
}