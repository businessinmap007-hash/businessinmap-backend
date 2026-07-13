<?php

namespace App\Services;

use App\Models\Order;
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

    /**
     * Issue (or return the existing) one-time handover token for a ready order.
     * Only a party of the order (its business or its customer) may issue it, and
     * only while the order is pending and not yet handed over.
     */
    public function issueFor(Order $order, int $byUserId): string
    {
        $this->assertParty($order, $byUserId);

        if ((string) $order->status !== self::STATUS_PENDING) {
            throw ValidationException::withMessages(['order' => 'الطلب غير جاهز للتسليم.']);
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
        return DB::transaction(function () use ($token, $byUserId) {
            $order = Order::query()
                ->where('handover_token', $token)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                abort(404, 'رمز التسليم غير صالح أو تم استخدامه.');
            }

            $this->assertParty($order, $byUserId);

            if ((string) $order->status !== self::STATUS_PENDING) {
                abort(409, 'لا يمكن تأكيد تسليم هذا الطلب.');
            }

            $order->status = self::STATUS_COMPLETED;
            $order->handover_confirmed_at = now();
            $order->handover_token = null; // consume — one-use
            $order->save();

            return $order;
        });
    }

    /** The order's parties are its business and its customer. */
    private function assertParty(Order $order, int $userId): void
    {
        if ((int) $order->business_id !== $userId && (int) $order->user_id !== $userId) {
            abort(403, 'لست طرفاً في هذا الطلب.');
        }
    }
}
