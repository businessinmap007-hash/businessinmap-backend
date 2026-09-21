<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /** How the order is fulfilled. dine_in links to a table booking. */
    public const FULFILLMENT_DELIVERY = 'delivery';
    public const FULFILLMENT_DINE_IN = 'dine_in';
    public const FULFILLMENT_PICKUP = 'pickup';

    public const FULFILLMENT_TYPES = [
        self::FULFILLMENT_DELIVERY,
        self::FULFILLMENT_DINE_IN,
        self::FULFILLMENT_PICKUP,
    ];

    /**
     * Preparation sub-status: the business-driven progress of a `pending` order
     * from acceptance to ready-for-fulfilment. null = just placed (awaiting the
     * business). See the prep_status column (kept separate from `status`).
     */
    public const PREP_ACCEPTED = 'accepted';
    public const PREP_PREPARING = 'preparing';
    public const PREP_READY = 'ready';

    public const PREP_STATUSES = [
        self::PREP_ACCEPTED,
        self::PREP_PREPARING,
        self::PREP_READY,
    ];

    /**
     * What the customer asked for if a menu line turns out unavailable while
     * the business is preparing it — asked once at checkout, applied by
     * OrderController::businessMarkItemUnavailable whenever a specific line
     * actually goes missing.
     */
    public const OUT_OF_STOCK_SUBSTITUTE = 'substitute';
    public const OUT_OF_STOCK_REMOVE = 'remove';
    public const OUT_OF_STOCK_CANCEL = 'cancel';

    public const OUT_OF_STOCK_POLICIES = [
        self::OUT_OF_STOCK_SUBSTITUTE,
        self::OUT_OF_STOCK_REMOVE,
        self::OUT_OF_STOCK_CANCEL,
    ];

    // Columns match the orders table: total / delivery_fee / discount /
    // final_total (there is no `subtotal` column).
    protected $fillable = [

        'user_id',
        'business_id',
        'fulfillment_type',
        'pickup_at',
        'booking_id',
        'business_table_id',
        'delivery_driver_id',
        'delivery_stage',
        'pickup_token',
        'delivery_token',
        'total',
        'delivery_fee',
        'discount',
        'service_fee',
        'tax',
        'final_total',
        'requires_deposit',
        'deposit_amount',
        'deposit_covered',
        'deposit_covered_by',
        'deposit_accepted_without_cover',
        'payment_method',
        'payment_status',
        'paid_at',
        'address',
        'delivery_address_id',
        'delivery_lat',
        'delivery_lng',
        'notes',
        'out_of_stock_policy',
        'status',
        'prep_status',
        'share_token',
        'is_shared',
        'handover_token',
        'handover_confirmed_at',

    ];

    protected $casts = [
        'booking_id' => 'integer',
        'business_table_id' => 'integer',
        'delivery_driver_id' => 'integer',
        'delivery_address_id' => 'integer',
        'delivery_lat' => 'float',
        'delivery_lng' => 'float',
        'is_shared' => 'boolean',
        'handover_confirmed_at' => 'datetime',
        'paid_at' => 'datetime',
        'pickup_at' => 'datetime',
        'delivery_eta_at' => 'datetime',
        'customer_payment_confirmed_at' => 'datetime',
        'merchant_payment_confirmed_at' => 'datetime',
        'driver_payment_confirmed_at' => 'datetime',
        'payment_settled_at' => 'datetime',
        'delivery_fee_proposed' => 'float',
        'delivery_fee_decided_at' => 'datetime',
        'shipping_fee' => 'float',
        'shipping_appointment_at' => 'datetime',
        'shipping_appointment_confirmed_at' => 'datetime',
        'requires_deposit' => 'boolean',
        'deposit_amount' => 'float',
        'deposit_covered' => 'boolean',
        'deposit_accepted_without_cover' => 'boolean',
    ];

    /** The business must explicitly choose to accept this — see businessAccept(). */
    public function needsExplicitDepositDecision(): bool
    {
        return (bool) $this->requires_deposit && ! $this->deposit_covered;
    }

    /**
     * True once every party to the cash has attested: customer + merchant
     * always, plus the driver when a delivery fee was actually collected by
     * an assigned driver. Nothing is held for an order (the deposit flag is
     * advisory - see CustomerCartService::assessDeposit), so "settled" is the
     * point where that advisory deposit is considered released.
     */
    public function paymentsFullyConfirmed(): bool
    {
        if (! $this->customer_payment_confirmed_at || ! $this->merchant_payment_confirmed_at) {
            return false;
        }

        $driverOwed = (string) $this->fulfillment_type === self::FULFILLMENT_DELIVERY
            && $this->delivery_driver_id
            && (float) $this->delivery_fee > 0;

        return ! $driverOwed || (bool) $this->driver_payment_confirmed_at;
    }

    /** Orders placed before cash confirmation existed are never held to it. */
    public const PAYMENT_CONFIRMATION_SINCE = '2026-09-20 00:00:00';

    /**
     * Whether this order is held to the cash-confirmation rules: a cash /
     * cash-on-delivery order placed after the feature shipped. Older orders
     * and anything paid another way are unaffected.
     */
    public function requiresPaymentConfirmation(): bool
    {
        return $this->booking_id === null
            && in_array((string) $this->payment_method, ['cash', 'cash_on_delivery'], true)
            && $this->created_at !== null
            && $this->created_at->gte(\Illuminate\Support\Carbon::parse(self::PAYMENT_CONFIRMATION_SINCE));
    }

    /** Stamps payment_settled_at once, the first time every required party has confirmed. */
    public function settlePaymentsIfComplete(): void
    {
        if ($this->payment_settled_at === null && $this->paymentsFullyConfirmed()) {
            $this->payment_settled_at = now();
            $this->save();
        }
    }

    /**
     * Per-order delivery pricing for out-of-city orders: awaiting_quote (no
     * courier price yet) -> proposed (a courier wrote an amount) -> accepted
     * (the customer agreed; it is on the invoice). A declined proposal goes
     * back to awaiting_quote and the courier is released.
     */
    public const FEE_AWAITING_QUOTE = 'awaiting_quote';
    public const FEE_PROPOSED = 'proposed';
    public const FEE_ACCEPTED = 'accepted';

    public const FEE_RECOMMENDATIONS = ['suitable', 'not_suitable'];

    /**
     * Governorate shipping (a different governorate than the business's): the
     * merchant picks a shipping company (awaiting_company), the company sets the
     * appointment (awaiting_appointment -> scheduled), then ships and delivers.
     * A shipping order is never offered to couriers.
     */
    public const SHIP_AWAITING_COMPANY = 'awaiting_company';
    public const SHIP_AWAITING_APPOINTMENT = 'awaiting_appointment';
    public const SHIP_SCHEDULED = 'scheduled';
    public const SHIP_SHIPPED = 'shipped';
    public const SHIP_DELIVERED = 'delivered';

    public function isShipping(): bool
    {
        return $this->shipping_status !== null;
    }

    public function shippingCompany()
    {
        return $this->belongsTo(User::class, 'shipping_company_id');
    }

    /** The delivery loop must not start while the fee is still being agreed. */
    public function deliveryFeeUnsettled(): bool
    {
        return in_array((string) $this->delivery_fee_status, [self::FEE_AWAITING_QUOTE, self::FEE_PROPOSED], true);
    }

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PAID = 'paid';

    public function businessTable()
    {
        return $this->belongsTo(BusinessTable::class, 'business_table_id');
    }

    public function deliveryDriver()
    {
        return $this->belongsTo(DeliveryDriver::class, 'delivery_driver_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function business()
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /** The saved address book entry this delivery order was placed against. */
    public function deliveryAddress()
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }

    /**
     * Where the customer actually is, for a distance calculation — a one-off
     * GPS pin wins (it is exactly where they stood at checkout), then the
     * saved address's own coordinates. A free-text address has neither, and
     * that is a real "unknown", not a bug to paper over with a guess.
     *
     * @return array{0:float,1:float}|null [lat, lng]
     */
    public function customerLatLng(): ?array
    {
        if ($this->delivery_lat !== null && $this->delivery_lng !== null) {
            return [(float) $this->delivery_lat, (float) $this->delivery_lng];
        }

        $address = $this->relationLoaded('deliveryAddress') ? $this->deliveryAddress : $this->deliveryAddress()->first();

        if ($address && $address->lat !== null && $address->lng !== null) {
            return [(float) $address->lat, (float) $address->lng];
        }

        return null;
    }

    public function isDineIn(): bool
    {
        return (string) $this->fulfillment_type === self::FULFILLMENT_DINE_IN;
    }

    public function foodTotal(): float
    {
        return round((float) ($this->final_total ?? $this->total ?? 0), 2);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function escrow()
    {
        return $this->hasOne(Escrow::class);
    }

    /**
     * The build/manufacturing timeline the business linked to this order, if
     * any — Project uses its own operation_type/operation_id columns rather
     * than Laravel's default morph naming, so this isn't a plain morphOne.
     */
    public function project()
    {
        return $this->hasOne(Project::class, 'operation_id')->where('operation_type', self::class);
    }

    public function participants()
    {
        return $this->hasMany(OrderParticipant::class);
    }

    public function isSharedCart(): bool
    {
        return (bool) $this->is_shared;
    }

    /**
     * الكشف المالى يحتاج مصدرًا واحدًا للطلب — منيو أو تجزئة — لا خلطًا.
     * أول سطرٍ يقرّر: عربات المنيو والتجزئة منفصلتان أصلًا فى بنية السلة.
     */
    public function ledgerSource(): string
    {
        $item = $this->items()->first();

        return $item && (string) $item->offering_type === BusinessCatalogListing::class
            ? BusinessFinancialLedger::SOURCE_RETAIL
            : BusinessFinancialLedger::SOURCE_MENU;
    }

}
