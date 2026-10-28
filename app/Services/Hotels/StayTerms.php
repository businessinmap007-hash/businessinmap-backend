<?php

namespace App\Services\Hotels;

use App\Models\BookableItem;
use App\Models\BusinessBookingSetting;
use App\Models\ChildPolicy;
use Illuminate\Validation\ValidationException;

/**
 * The rest of a hotel's rate sheet on a stay:
 *
 *  - «إقامة فردية»: one adult alone in a room for two pays the room type's single percentage of the night (a baby under two does not
 *    make it shared);
 *  - «سرير أطفال»: cots on request, per room, at the hotel's price a night;
 *  - «غير قابل للاسترداد»: the cheaper rate — the hotel's percentage off the stay (without the gala dinners), sold by the hotel itself.
 *    Payments are cash outside the app: the app records the rate and refuses moving its dates; it confiscates nothing.
 */
final class StayTerms
{
    public const FLEXIBLE = 'flexible';
    public const NON_REFUNDABLE = 'non_refundable';

    /** Under this age a child is a baby: it does not share the room, and a cot is for it. */
    private const BABY_UNDER = 2;

    /** The hotel's non-refundable discount, or null when it sells only the flexible rate. */
    public function nonRefundablePercent(int $businessId): ?float
    {
        $p = BusinessBookingSetting::query()->where('business_id', $businessId)->value('non_refundable_percent');

        return $p === null ? null : (float) $p;
    }

    /**
     * @param  array<string,mixed>  $breakdown
     * @param  list<int>  $ages
     * @param  array{cots?:int,rate_plan?:string}  $stay
     */
    public function apply(array $breakdown, ?BookableItem $bookable, array $ages, int $adults, array $stay, bool $partnerSale = false): array
    {
        if (! $bookable || $bookable->item_type !== 'booking_stay') {
            return $breakdown;
        }

        $periods = (array) ($breakdown['periods'] ?? []);
        $rooms = max((int) ($breakdown['units'] ?? 1), 1);
        $pricing = app(ChildPricing::class);
        $firstMeals = $pricing->meals((array) ($breakdown['modifiers'] ?? []));
        $base = 0.0; // what changes the stay's own price (single occupancy, cots)
        $nr = 0.0;   // the non-refundable rate's discount on it

        // «إقامة فردية»
        $percent = $bookable->single_occupancy_percent;
        $shared = array_filter(array_map('intval', $ages), fn ($a) => $a >= self::BABY_UNDER) !== [];

        if ($percent !== null && (int) $bookable->capacity >= 2 && ! $shared && $periods !== []) {
            $grown = max($adults, 1);
            $singleRooms = max(min($rooms * 2 - $grown, $rooms, $grown), 0);

            if ($singleRooms > 0) {
                $off = 0.0;

                foreach ($periods as $p) {
                    $room = max((float) ($p['price'] ?? 0) - $pricing->periodMeals($p, $firstMeals)[0], 0);
                    $off += $room * (100 - min(max((float) $percent, 0), 100)) / 100 * $singleRooms;
                }

                $off = round($off, 2);
                $breakdown['single_occupancy'] = ['rooms' => $singleRooms, 'percent' => (float) $percent, 'per_night' => round($off / count($periods), 2), 'total' => $off];
                $base -= $off;
            }
        }

        // «سرير أطفال»
        $cots = max((int) ($stay['cots'] ?? 0), 0);

        if ($cots > 0) {
            $policy = ChildPolicy::forBusiness((int) $bookable->business_id);
            $max = $policy ? (int) $policy->cots_max * $rooms : 0;

            if ($max === 0) {
                throw ValidationException::withMessages(['cots' => __('الفندق لا يوفّر سرير أطفال.')]);
            }

            if ($cots > $max) {
                throw ValidationException::withMessages(['cots' => __('أقصى عدد لأسرّة الأطفال :n.', ['n' => $max])]);
            }

            $perNight = round($cots * (float) $policy->cot_price, 2);
            $breakdown['cots'] = ['count' => $cots, 'per_night' => $perNight, 'total' => round($perNight * count($periods), 2)];
            $base += $breakdown['cots']['total'];
        }

        // «غير قابل للاسترداد»
        $plan = (string) ($stay['rate_plan'] ?? self::FLEXIBLE);

        if ($plan === self::NON_REFUNDABLE) {
            $off = $partnerSale ? null : $this->nonRefundablePercent((int) $bookable->business_id);

            if ($off === null) {
                throw ValidationException::withMessages(['rate_plan' => __('هذا الفندق لا يبيع سعرًا غير قابل للاسترداد.')]);
            }

            $nr = round(max((float) ($breakdown['final_price'] ?? 0) + $base, 0) * $off / 100, 2);
            $breakdown['non_refundable'] = ['percent' => $off, 'amount' => $nr];
        }

        $breakdown['rate_plan'] = $plan === self::NON_REFUNDABLE ? self::NON_REFUNDABLE : self::FLEXIBLE;

        if ($base != 0.0 || $nr != 0.0) {
            $breakdown['original_price'] = round(max((float) ($breakdown['original_price'] ?? 0) + $base, 0), 2);
            $breakdown['final_price'] = round(max((float) ($breakdown['final_price'] ?? 0) + $base - $nr, 0), 2);
        }

        return $breakdown;
    }
}
