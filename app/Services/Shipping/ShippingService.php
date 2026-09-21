<?php

namespace App\Services\Shipping;

use App\Models\AppNotification;
use App\Models\Order;
use App\Models\RatingOutcomeEvent;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use App\Services\Ratings\RatingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shipping between governorates, run by Shipping & Delivery companies: the
 * company keeps a fixed price from its own governorate to each other one, the
 * merchant picks a company for a product order going to another governorate
 * (that price lands on the invoice), and the company only sets the appointment
 * and moves the order through shipped -> delivered.
 */
class ShippingService
{
    public function __construct(
        private readonly NotificationDispatcherService $notifications,
        private readonly RatingService $ratings,
    ) {
    }

    // ───────────────────────── The company's price list ─────────────────────────

    /** @return array<int,array{price:float,days:?array<int,int>}> governorate id => price and weekdays */
    public function ratesFor(int $companyId): array
    {
        return ShippingRate::query()->where('company_id', $companyId)->get()
            ->mapWithKeys(fn ($r) => [(int) $r->to_governorate_id => ['price' => (float) $r->price, 'days' => $r->days]])
            ->all();
    }

    /**
     * Replace the company's whole price list. A governorate left out (or priced
     * blank) simply isn't served. The company's own governorate can't be a
     * destination.
     *
     * @param  array<int,array{governorate_id:int,price:float|int|string|null,days?:?array<int,int>}>  $rates
     */
    public function replaceRates(User $company, array $rates): void
    {
        $this->assertCarrier($company);

        DB::transaction(function () use ($company, $rates) {
            ShippingRate::query()->where('company_id', $company->id)->delete();

            foreach ($rates as $rate) {
                $gov = (int) ($rate['governorate_id'] ?? 0);
                $price = $rate['price'] ?? null;
                if ($gov <= 0 || $price === null || $price === '' || $gov === (int) $company->governorate_id) {
                    continue;
                }
                $days = isset($rate['days']) && is_array($rate['days'])
                    ? array_values(array_unique(array_map('intval', $rate['days'])))
                    : null;
                if (is_array($days)) {
                    sort($days);
                }

                ShippingRate::query()->create([
                    'company_id' => $company->id,
                    'to_governorate_id' => $gov,
                    'price' => round((float) $price, 2),
                    'days' => $days === [] ? null : $days,
                ]);
            }
        });
    }

    // ───────────────────────── The merchant picks a company ─────────────────────────

    /** Companies that can ship this order to its governorate, with their price. */
    public function companiesFor(int $businessId, int $orderId)
    {
        $order = $this->ownedShippingOrder($businessId, $orderId);
        $fromGovernorate = (int) User::query()->whereKey($businessId)->value('governorate_id');

        $today = now();
        $tomorrow = now()->addDay();

        return User::query()
            ->where('users.type', User::TYPE_BUSINESS)
            // Ships FROM the business's governorate...
            ->where('users.governorate_id', $fromGovernorate)
            ->join('categories as c', 'c.id', '=', 'users.category_id')
            ->where('c.slug', 'shipping-delivery')
            // ...TO the customer's governorate...
            ->join('shipping_rates as r', function ($j) use ($order) {
                $j->on('r.company_id', '=', 'users.id')->where('r.to_governorate_id', '=', (int) $order->shipping_to_governorate_id);
            })
            ->orderBy('r.price')
            ->get(['users.id', 'users.name', 'users.logo', 'users.governorate_id', 'r.price', 'r.days'])
            // ...on a day it actually runs that route: today or tomorrow.
            ->map(function ($u) use ($today, $tomorrow) {
                $days = is_string($u->days) ? json_decode($u->days, true) : $u->days;
                $runsToday = $days === null || in_array($today->dayOfWeek, $days, true);
                $runsTomorrow = $days === null || in_array($tomorrow->dayOfWeek, $days, true);

                return [
                    'id' => (int) $u->id,
                    'name' => (string) $u->name,
                    'logo' => $u->logo ?: null,
                    'price' => (float) $u->price,
                    'days' => $days,
                    'runs_today' => $runsToday,
                    'runs_tomorrow' => $runsTomorrow,
                    'next_date' => $runsToday ? $today->toDateString() : ($runsTomorrow ? $tomorrow->toDateString() : null),
                ];
            })
            ->filter(fn ($c) => $c['runs_today'] || $c['runs_tomorrow'])
            ->values();
    }

    public function assignCompany(int $businessId, int $orderId, int $companyId): Order
    {
        $order = DB::transaction(function () use ($businessId, $orderId, $companyId) {
            $order = $this->ownedShippingOrder($businessId, $orderId, true);

            if (! in_array((string) $order->shipping_status, [Order::SHIP_AWAITING_COMPANY, Order::SHIP_AWAITING_APPOINTMENT], true)) {
                abort(409, __('لا يمكن تغيير شركة الشحن بعد تحديد الموعد.'));
            }

            $company = User::query()->find($companyId);
            if (! $company || ! $company->isShippingCarrier()) {
                throw ValidationException::withMessages(['company_id' => __('شركة الشحن غير صالحة.')]);
            }

            $offered = $this->companiesFor($businessId, $orderId)->firstWhere('id', $companyId);
            if (! $offered) {
                throw ValidationException::withMessages(['company_id' => __('هذه الشركة لا تشحن إلى محافظة العميل.')]);
            }
            $price = $offered['price'];

            // Swap the fee on the invoice if a company had already been picked.
            $old = (float) $order->shipping_fee;
            $fee = round((float) $price, 2);
            $order->final_total = round((float) $order->final_total - $old + $fee, 2);
            $order->shipping_fee = $fee;
            $order->delivery_fee = $fee;
            $order->shipping_company_id = $companyId;
            $order->shipping_status = Order::SHIP_AWAITING_APPOINTMENT;
            $order->shipping_appointment_at = null;
            $order->shipping_appointment_note = null;
            $order->save();

            return $order;
        });

        $this->ping($order, (int) $order->shipping_company_id, 'shipping_company_assigned', $businessId, 'open_shipping_order', [
            'body_ar' => 'طلب شحن جديد رقم #' . $order->id . ' — حدد موعد الشحن.',
            'body_en' => 'New shipping request #' . $order->id . ' - set the shipping appointment.',
        ]);

        return $order;
    }

    // ───────────────────────── The company runs it ─────────────────────────

    public function ordersOf(int $companyId)
    {
        return Order::query()
            ->where('shipping_company_id', $companyId)
            ->with(['user:id,name,phone', 'business:id,name,logo', 'items.menuItem:id,name_ar,name_en'])
            ->latest('id')
            ->limit(100)
            ->get();
    }

    public function setAppointment(int $companyId, int $orderId, Carbon $at, ?string $note): Order
    {
        $order = DB::transaction(function () use ($companyId, $orderId, $at, $note) {
            $order = $this->companyOrder($companyId, $orderId);

            if (! in_array((string) $order->shipping_status, [Order::SHIP_AWAITING_APPOINTMENT, Order::SHIP_SCHEDULED], true)) {
                abort(409, __('لا يمكن تعديل الموعد في هذه المرحلة.'));
            }
            if ($at->isPast()) {
                throw ValidationException::withMessages(['appointment_at' => __('اختر موعدًا في المستقبل.')]);
            }

            $order->shipping_appointment_at = $at;
            $order->shipping_appointment_confirmed_at = null; // a new time needs a fresh yes
            $order->shipping_appointment_note = $note !== null && trim($note) !== '' ? trim($note) : null;
            $order->shipping_status = Order::SHIP_SCHEDULED;
            $order->save();

            return $order;
        });

        $when = $at->translatedFormat('j F Y - h:i A');
        $bodies = [
            'body_ar' => 'موعد شحن الطلب رقم #' . $order->id . ': ' . $when . '.',
            'body_en' => 'Shipping appointment for order #' . $order->id . ': ' . $when . '.',
        ];
        $this->pingCustomer($order, $companyId, 'shipping_update', $bodies);
        $this->ping($order, (int) $order->business_id, 'shipping_update', $companyId, 'open_business_order', $bodies);

        return $order;
    }

    /** The customer says the scheduled appointment suits them; the company and the merchant are told. */
    public function confirmAppointment(int $customerId, int $orderId): Order
    {
        $order = Order::query()->where('user_id', $customerId)->findOrFail($orderId);

        if ((string) $order->shipping_status !== Order::SHIP_SCHEDULED) {
            abort(409, __('لا يوجد موعد شحن للتأكيد.'));
        }

        if ($order->shipping_appointment_confirmed_at === null) {
            $order->shipping_appointment_confirmed_at = now();
            $order->save();

            $bodies = [
                'body_ar' => 'العميل وافق على موعد شحن الطلب رقم #' . $order->id . '.',
                'body_en' => 'The customer accepted the shipping appointment for order #' . $order->id . '.',
            ];
            $this->ping($order, (int) $order->shipping_company_id, 'shipping_update', $customerId, 'open_shipping_order', $bodies);
            $this->ping($order, (int) $order->business_id, 'shipping_update', $customerId, 'open_business_order', $bodies);
        }

        return $order;
    }

    public function markShipped(int $companyId, int $orderId): Order
    {
        $order = $this->advance($companyId, $orderId, Order::SHIP_SCHEDULED, Order::SHIP_SHIPPED, __('حدد موعد الشحن أولًا.'));

        $this->pingCustomer($order, $companyId, 'shipping_update', [
            'body_ar' => 'تم شحن طلبك رقم #' . $order->id . '.',
            'body_en' => 'Your order #' . $order->id . ' has been shipped.',
        ]);

        return $order;
    }

    public function markDelivered(int $companyId, int $orderId): Order
    {
        $order = $this->advance($companyId, $orderId, Order::SHIP_SHIPPED, Order::SHIP_DELIVERED, __('يجب شحن الطلب أولًا.'));

        DB::transaction(function () use ($order) {
            $order->status = 'completed';
            $order->handover_confirmed_at = now();
            $order->save();
        });

        $this->ratings->recordForBothParties(
            businessUserId: (int) $order->business_id,
            clientUserId: (int) $order->user_id,
            outcome: RatingOutcomeEvent::OUTCOME_SUCCESS,
            operationType: RatingOutcomeEvent::OP_ORDER,
            operationId: (int) $order->id,
        );

        $bodies = [
            'body_ar' => 'تم تسليم الطلب رقم #' . $order->id . ' بالشحن.',
            'body_en' => 'Order #' . $order->id . ' was delivered by shipping.',
        ];
        $this->pingCustomer($order, $companyId, 'shipping_update', $bodies);
        $this->ping($order, (int) $order->business_id, 'shipping_update', $companyId, 'open_business_order', $bodies);

        return $order;
    }

    // ───────────────────────── helpers ─────────────────────────

    private function assertCarrier(User $company): void
    {
        if (! $company->isShippingCarrier()) {
            abort(403, __('هذه الخدمة لحسابات «شحن وتوصيل» فقط.'));
        }
    }

    private function ownedShippingOrder(int $businessId, int $orderId, bool $lock = false): Order
    {
        $query = Order::query()->where('business_id', $businessId)->whereNull('booking_id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $order = $query->find($orderId);

        if (! $order) {
            abort(404, __('الطلب غير موجود.'));
        }
        if (! $order->isShipping()) {
            abort(409, __('هذا ليس طلب شحن.'));
        }

        return $order;
    }

    private function companyOrder(int $companyId, int $orderId): Order
    {
        $order = Order::query()->lockForUpdate()->find($orderId);

        if (! $order || (int) $order->shipping_company_id !== $companyId) {
            abort(404, __('الطلب غير موجود.'));
        }

        return $order;
    }

    private function advance(int $companyId, int $orderId, string $from, string $to, string $wrongStage): Order
    {
        return DB::transaction(function () use ($companyId, $orderId, $from, $to, $wrongStage) {
            $order = $this->companyOrder($companyId, $orderId);

            if ((string) $order->shipping_status !== $from) {
                abort(409, $wrongStage);
            }

            $order->shipping_status = $to;
            $order->save();

            return $order;
        });
    }

    private function pingCustomer(Order $order, int $actorId, string $event, array $bodies): void
    {
        $this->ping($order, (int) $order->user_id, $event, $actorId, 'open_customer_order', $bodies);
    }

    private function ping(Order $order, int $userId, string $event, int $actorId, string $actionType, array $bodies): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            $this->notifications->dispatch($event, $userId, array_merge([
                'type' => AppNotification::TYPE_SYSTEM,
                'actor_id' => $actorId,
                'notifiable_type' => Order::class,
                'notifiable_id' => (int) $order->id,
                'source_id' => (int) $order->id,
                'action_type' => $actionType,
                'skip_realtime' => true,
                'meta' => ['order_id' => (int) $order->id, 'business_id' => (int) $order->business_id],
            ], $bodies));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
