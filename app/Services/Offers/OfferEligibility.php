<?php

namespace App\Services\Offers;

use App\Models\CommercialOffer;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The four things that make a discount a discount.
 *
 * «يجب مرور شهر كامل دون رفع السعر قبل تسجيل عرض خصم. الهدف منع العروض الوهمية
 *  التي يرفع فيها التاجر السعر ثم يعلن خصمًا ثم يعيد المنتج لسعره السابق»
 *  — المالك، 2026-08-23.
 *
 * The fraud has three steps and the platform could not see any of them: raise
 * the price, advertise a cut from the raised number, put it back. Every part of
 * that was legal, because an offer carried a `base_price` the API accepted from
 * the request and never compared to anything.
 *
 * So four checks, in the order a merchant meets them:
 *
 *   1. THE ROW. The offer names a priced row he owns — OfferableResolver.
 *   2. THE PREVIOUS PRICE IS READ, NOT TYPED. `base_price` comes off the row.
 *      This is the one that ends the whole class of trick: there is no field
 *      left to inflate.
 *   3. IT IS ACTUALLY LESS. `final_price` below `base_price`, or it is not an
 *      offer.
 *   4. THE MONTH. The row's last price INCREASE is at least thirty days old.
 *      A cut is never what an offer hides, so only rises count.
 *
 * ── And a validity condition, because «إلى متى» has to have an answer ───────
 *
 * «كل عرض له شرط انتهاء: مدة، أو حتى نفاد الكمية، أو حتى بيع عدد معيّن». An
 * offer with no end is a price change wearing an offer's clothes — it never
 * expires, so the «before» price never comes back and the comparison the whole
 * feature rests on stops meaning anything.
 *
 * ── Who this applies to ─────────────────────────────────────────────────────
 *
 * The merchant API. The admin panel is staff acting deliberately and keeps its
 * freedom — a support agent fixing a merchant's offer is not the fraud this
 * guards against — but it calls the same resolver, so an admin cannot point an
 * offer at a row that does not exist either.
 */
class OfferEligibility
{
    /** «شهر كامل» — thirty days, counted from the last rise. */
    public const QUIET_DAYS = 30;

    public function __construct(private readonly OfferableResolver $resolver)
    {
    }

    /**
     * Check everything, and hand back what the offer must be saved with.
     *
     * @param  array<string,mixed>  $data  the validated request
     * @return array<string,mixed>  the fields this service owns
     */
    public function vet(array $data, int $ownerBusinessId): array
    {
        $row = $this->resolver->resolve(
            (string) $data['offerable_type'],
            (int) ($data['offerable_id'] ?? 0),
            $ownerBusinessId
        );

        $this->assertQuietMonth($row);

        $final = (float) $data['final_price'];

        if ($final >= $row->price) {
            throw ValidationException::withMessages([
                'final_price' => __('سعر العرض لا بد أن يقل عن السعر الحالي (:price).', [
                    'price' => $this->money($row->price, $row->currency),
                ]),
            ]);
        }

        $this->assertHasAnEnd($data);

        return [
            // Read, never taken. There is no «previous price» field to inflate.
            'base_price' => round($row->price, 2),
            'currency' => $row->currency,
            'discount_type' => 'fixed',
            'discount_value' => round($row->price - $final, 2),

            /*
             * The audit trail. `base_price` alone says what the offer claims;
             * this says what it was checked against and when — which is what
             * anyone reviewing a suspicious offer six weeks later actually
             * needs, and what makes the delete-and-recreate hole visible after
             * the fact even though it is not closed.
             */
            'checked_against' => [
                'row' => $row->model->getMorphClass() . '#' . $row->model->getKey(),
                'label' => $row->label,
                'price' => round($row->price, 2),
                'last_increase_at' => optional($row->model->lastPriceIncreaseAt())->toDateTimeString(),
                'checked_at' => now()->toDateTimeString(),
            ],
        ];
    }

    /** How long until this row may be discounted, or null if it may be now. */
    public function eligibleFrom(OfferableRow $row): ?Carbon
    {
        $lastRise = $row->model->lastPriceIncreaseAt();

        if (! $lastRise) {
            return null;
        }

        $eligible = $lastRise->copy()->addDays(self::QUIET_DAYS);

        return $eligible->isFuture() ? $eligible : null;
    }

    private function assertQuietMonth(OfferableRow $row): void
    {
        $eligible = $this->eligibleFrom($row);

        if (! $eligible) {
            return;
        }

        throw ValidationException::withMessages([
            'offerable_id' => __('سعر هذا الصنف ارتفع مؤخرًا. يمكن تسجيل خصم عليه ابتداءً من :date.', [
                'date' => $eligible->toDateString(),
            ]),
        ]);
    }

    /**
     * A period, a stock-out, or a number of units. One of the three.
     */
    private function assertHasAnEnd(array $data): void
    {
        $mode = (string) ($data['availability_mode'] ?? '');

        if (! empty($data['ends_at'])) {
            return;
        }

        if ($mode === CommercialOffer::AVAILABILITY_WHILE_STOCK) {
            return;
        }

        if ($mode === CommercialOffer::AVAILABILITY_LIMITED && (int) ($data['available_quantity'] ?? 0) > 0) {
            return;
        }

        throw ValidationException::withMessages([
            'ends_at' => __('لكل عرض شرط انتهاء: تاريخ، أو حتى نفاد الكمية، أو عدد وحدات محدَّد.'),
        ]);
    }

    private function money(float $amount, string $currency): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.') . ' ' . $currency;
    }
}
