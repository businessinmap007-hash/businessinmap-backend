<?php

namespace App\Services;

use App\Models\BusinessCatalogListing;
use App\Models\BusinessFinancialLedger;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RatingOutcomeEvent;
use App\Services\Ratings\RatingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Order-handover confirmation (BIM-13.5). A ready order (status = pending) issues
 * a one-time handover token; the other party scans it to confirm the handover,
 * which flips the order to completed and consumes the token. Works for any order
 * shape (personal / shared / table) — they all reach `pending` at checkout.
 */
class OrderHandoverService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';

    public function __construct(
        protected RatingService $ratingService,
        protected FinancialLedgerService $ledger,
    ) {
    }

    /**
     * Issue (or return the existing) one-time handover token for a ready order.
     * Only a party of the order (its business or its customer) may issue it, and
     * only while the order is pending and not yet handed over.
     */
    public function issueFor(Order $order, int $byUserId): string
    {
        $this->assertParty($order, $byUserId);

        if ((string) $order->status !== self::STATUS_PENDING) {
            throw ValidationException::withMessages(['order' => __('الطلب غير جاهز للتسليم.')]);
        }

        if (! $order->handover_token) {
            $order->handover_token = Str::random(48);
            $order->save();
        }

        return (string) $order->handover_token;
    }

    /**
     * Confirm a handover by its token: flip the order to completed and consume
     * the token (one-use). The scanner must be a party of the order.
     */
    public function confirm(string $token, int $byUserId): Order
    {
        $order = DB::transaction(function () use ($token, $byUserId) {
            $order = Order::query()
                ->where('handover_token', $token)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                abort(404, __('رمز التسليم غير صالح أو تم استخدامه.'));
            }

            $this->assertParty($order, $byUserId);

            if ((string) $order->status !== self::STATUS_PENDING) {
                abort(409, __('لا يمكن تأكيد تسليم هذا الطلب.'));
            }

            $order->status = self::STATUS_COMPLETED;
            $order->handover_confirmed_at = now();
            $order->handover_token = null; // consume — one-use
            $order->save();

            $this->recordSaleToLedger($order);

            return $order;
        });

        // Operation rating: a handed-over order is a success for both parties.
        $this->ratingService->recordForBothParties(
            businessUserId: (int) $order->business_id,
            clientUserId: (int) $order->user_id,
            outcome: RatingOutcomeEvent::OUTCOME_SUCCESS,
            operationType: RatingOutcomeEvent::OP_ORDER,
            operationId: (int) $order->id,
        );

        return $order;
    }

    /** The order's parties are its business and its customer. */
    private function assertParty(Order $order, int $userId): void
    {
        if ((int) $order->business_id !== $userId && (int) $order->user_id !== $userId) {
            abort(403, __('لست طرفاً في هذا الطلب.'));
        }
    }

    /**
     * الوارد = ما دفعه العميل، الصادر (تكلفة البضاعة) = سعر توريد كل سطر ×
     * كميته — من `MenuItem.supply_price` أو `BusinessCatalogListing.cost_price`،
     * أيهما ما ينطبق على السطر. صفٌّ بلا سعر توريد يُحسب تكلفته صفرًا، لا يمنع
     * تسجيل البيع.
     */
    private function recordSaleToLedger(Order $order): void
    {
        $businessId = (int) $order->business_id;

        if ($businessId <= 0) {
            return;
        }

        $costOfGoods = 0.0;
        $source = $order->ledgerSource();

        foreach ($order->items()->get() as $item) {
            $qty = (int) ($item->qty ?: 1);

            if ((string) $item->offering_type === BusinessCatalogListing::class) {
                $listing = BusinessCatalogListing::find($item->offering_id);
                if ($listing && $listing->cost_price !== null) {
                    $costOfGoods += (float) $listing->cost_price * $qty;
                }
                continue;
            }

            $menuItem = MenuItem::find($item->offering_id ?: $item->menu_id);
            if ($menuItem && $menuItem->supply_price !== null) {
                $costOfGoods += (float) $menuItem->supply_price * $qty;
            }
        }

        $this->ledger->recordSale($businessId, $source, $order->foodTotal(), $costOfGoods);
    }
}
