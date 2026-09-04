<?php

namespace App\Services;

use App\Models\BookableAllocation;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * الجسرُ بين حجزٍ حقيقى و«تخصيصِ» شريكٍ من مخزون شريكه.
 *
 * `bookable_allocations` كانت تُدار بالكامل يدويًا من AdminV2: صاحبُ المخزون
 * يفرز كمّيةً لشريكٍ، والعرضُ التجارى يُنشأ تلقائيًا — لكن محرّكَ الحجزِ نفسه
 * لا يعرف عن هذا شيئًا. فحجزُ العميل من الشريك لا يخصم من الكمّية المتاحة،
 * ولا يُسعَّر بسعر العقد. هذه الخدمةُ تُبقى `quantity_sold`/`quantity_reserved`
 * صادقةً مع كل حجزٍ حقيقى، لا مع ما يكتبه المسؤول وحده.
 *
 * سطرٌ واحد فى meta الحجز (`_allocation`) يحمل التخصيصَ والكمّية والطَّور
 * الحالى (`reserved`/`sold`/`released`)، فلا يضيع العدُّ عند الرفض أو الإلغاء —
 * ولا يُخصم مرّتين لو تكرّر استدعاء انتقال الحالة.
 */
class AllocationConsumptionService
{
    public function findActiveAllocation(int $partnerBusinessId, int $bookableItemId): ?BookableAllocation
    {
        return BookableAllocation::query()
            ->active()
            ->where('partner_business_id', $partnerBusinessId)
            ->where('bookable_item_id', $bookableItemId)
            ->first();
    }

    /**
     * تحجز الكمّيةَ من التخصيص — مقفولةً بصفٍّ لمنع بيع آخر وحدةٍ مرّتين فى
     * سباقٍ بين طلبين متزامنين. تُستدعى داخل نفس معاملة إنشاء الحجز.
     */
    public function reserve(BookableAllocation $allocation, int $quantity): void
    {
        DB::transaction(function () use ($allocation, $quantity) {
            $locked = BookableAllocation::query()->lockForUpdate()->find($allocation->id);

            if (! $locked || $locked->availableQuantity() < $quantity) {
                throw ValidationException::withMessages([
                    'bookable_id' => __('الكمية المتاحة من هذا التخصيص غير كافية.'),
                ]);
            }

            $locked->increment('quantity_reserved', $quantity);
        });
    }

    public function tagBooking(Booking $booking, BookableAllocation $allocation, int $quantity): void
    {
        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $meta['_allocation'] = [
            'id' => (int) $allocation->id,
            'partnership_id' => (int) $allocation->partnership_id,
            'quantity' => $quantity,
            'phase' => 'reserved',
        ];
        $booking->meta = $meta;
        $booking->save();
    }

    /**
     * يُستدعى من كل انتقالِ حالةٍ على الحجز. القبولُ يحوّل المحجوزَ إلى
     * مبيوع؛ الرفضُ أو الإلغاء يُعيد الكمّيةَ — من المبيوع أو المحجوز، حسب
     * الطَّور الذى كانت عليه — إلى المسبح المتاح لأى بيعٍ قادم.
     */
    public function onStatusChanged(Booking $booking, string $newStatus): void
    {
        $meta = is_array($booking->meta ?? null) ? $booking->meta : [];
        $entry = $meta['_allocation'] ?? null;

        if (! is_array($entry) || ($entry['phase'] ?? null) === 'released') {
            return;
        }

        $quantity = max((int) ($entry['quantity'] ?? 0), 0);

        if ($quantity <= 0) {
            return;
        }

        $allocation = BookableAllocation::query()->find((int) ($entry['id'] ?? 0));

        if (! $allocation) {
            return;
        }

        if ($newStatus === Booking::STATUS_ACCEPTED && ($entry['phase'] ?? null) === 'reserved') {
            DB::transaction(function () use ($allocation, $quantity) {
                $locked = BookableAllocation::query()->lockForUpdate()->find($allocation->id);

                if (! $locked) {
                    return;
                }

                $locked->decrement('quantity_reserved', min($quantity, (int) $locked->quantity_reserved));
                $locked->increment('quantity_sold', $quantity);
            });

            $entry['phase'] = 'sold';
            $meta['_allocation'] = $entry;
            $booking->meta = $meta;
            $booking->save();

            return;
        }

        if (in_array($newStatus, [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED], true)) {
            $wasSold = ($entry['phase'] ?? null) === 'sold';

            DB::transaction(function () use ($allocation, $quantity, $wasSold) {
                $locked = BookableAllocation::query()->lockForUpdate()->find($allocation->id);

                if (! $locked) {
                    return;
                }

                if ($wasSold) {
                    $locked->decrement('quantity_sold', min($quantity, (int) $locked->quantity_sold));
                } else {
                    $locked->decrement('quantity_reserved', min($quantity, (int) $locked->quantity_reserved));
                }
            });

            $entry['phase'] = 'released';
            $meta['_allocation'] = $entry;
            $booking->meta = $meta;
            $booking->save();
        }
    }
}
