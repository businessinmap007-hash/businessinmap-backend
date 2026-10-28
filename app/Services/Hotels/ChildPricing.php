<?php

namespace App\Services\Hotels;

use App\Models\BookableItem;
use App\Models\ChildPolicy;
use App\Models\OptionGroup;
use App\Support\RequestMemo;

/**
 * What the children of a stay add to its price, by the hotel's «سياسة الأطفال»: each child pays the percentage of its age band of one
 * person's night (the room as booked, with its view — not the meals — over the room's capacity), and, when the hotel says so, the same
 * percentage of the meal plan one adult pays. A child the policy calls free adds nothing; a guest of adult age is inside the room's own
 * price. The adults over the capacity sleep in extra beds.
 *
 * The hotel is always the policy's owner, also when a partner sells the room: the policy is the hotel's.
 */
final class ChildPricing
{
    /**
     * @param  array<string,mixed>  $breakdown  the stay's price breakdown (ServiceExecutionEngine)
     * @param  list<int>  $ages
     * @return array<string,mixed> the breakdown with `children`, `children_total` and the totals raised by it
     */
    public function apply(array $breakdown, ?BookableItem $bookable, array $ages, int $adults = 1): array
    {
        $ages = array_values(array_filter(array_map('intval', $ages), fn ($a) => $a >= 0));

        if (! $bookable || $bookable->item_type !== 'booking_stay') {
            return $breakdown;
        }

        $policy = ChildPolicy::forBusiness((int) $bookable->business_id);

        if (! $policy) {
            return $breakdown; // a hotel with no policy: children stay as before — free, not counted
        }

        // «حصة الفرد»: a room for two at 1300 is 650 a person; a child's share is of that, not of the whole night
        $occupancy = max((int) ($bookable->capacity ?? 0), 1);

        $firstMeals = $this->meals((array) ($breakdown['modifiers'] ?? []));
        $periods = (array) ($breakdown['periods'] ?? []);
        $children = [];
        $total = 0.0;

        $rooms = max((int) ($breakdown['units'] ?? 1), 1);
        $kids = array_values(array_filter(array_keys($ages), fn ($i) => ! $policy->isAdult($ages[$i])));

        // «بالغ واحد مع طفل»: a room with ONE adult has the second adult's place paid for in the room's price; the hotel's single-parent
        // percentage goes to the child in it — the oldest, the one who would pay the most
        $grown = max($adults, 1);
        $singleRooms = $policy->single_parent_percent === null || $occupancy < 2 ? 0 : max(min($rooms * 2 - $grown, $rooms, $grown), 0);
        $byShare = $kids;
        usort($byShare, fn ($a, $b) => ($policy->percentFor($ages[$b], true) <=> $policy->percentFor($ages[$a], true)) ?: ($ages[$b] <=> $ages[$a]));
        $singles = array_flip(array_slice($byShare, 0, $singleRooms));

        // «طفل واحد مجانًا لكل غرفة»: each room has ONE first child; the slot goes to the child it saves the most for
        $order = array_values(array_filter($kids, fn ($i) => ! isset($singles[$i])));
        usort($order, fn ($a, $b) => ($policy->percentFor($ages[$b], false) - $policy->percentFor($ages[$b], true))
            <=> ($policy->percentFor($ages[$a], false) - $policy->percentFor($ages[$a], true)));
        $firsts = array_flip(array_slice($order, 0, $rooms));

        foreach ($ages as $i => $age) {
            $single = isset($singles[$i]);
            $first = ! $single && isset($firsts[$i]);
            $percent = $single ? max(min((float) $policy->single_parent_percent, 100), 0) : $policy->percentFor($age, $first);
            $line = ['age' => $age, 'percent' => $percent, 'first' => $first, 'single_parent' => $single, 'adult' => $policy->isAdult($age), 'per_night' => 0.0, 'total' => 0.0];

            if ($percent > 0) {
                foreach ($periods as $p) {
                    [$mealsPerRoom, $mealsPerPerson] = $this->periodMeals($p, $firstMeals);
                    $amount = $this->personNight($p, $mealsPerRoom, $occupancy) * $percent / 100
                        + ($policy->meals_follow ? $mealsPerPerson * $percent / 100 : 0.0);
                    $line['total'] += $amount;
                }

                $line['total'] = round($line['total'], 2);
                $line['per_night'] = $periods === [] ? 0.0 : round($line['total'] / count($periods), 2);
            }

            $total += $line['total'];
            $children[] = $line;
        }

        // «سرير إضافي»: an ADULT the room does not take sleeps in an extra bed. A child over the capacity is the one in the extra bed,
        // and its child's share is its price for it — the trade's «sharing rate» rule — so it is never charged twice
        $beds = min(max($adults - $occupancy * $rooms, 0), $policy->extraBedsAllowed($rooms));
        $bedsTotal = 0.0;

        foreach ($beds > 0 ? $periods : [] as $p) {
            // «تخفيض الشخص الثالث»: one person's night less the hotel's reduction when it writes one; its fixed bed price otherwise
            $bedsTotal += $policy->third_adult_reduction !== null
                ? $beds * $this->personNight($p, $this->periodMeals($p, $firstMeals)[0], $occupancy) * (100 - (float) $policy->third_adult_reduction) / 100
                : $beds * (float) $policy->extra_bed_price;
        }

        $bedsTotal = round($bedsTotal, 2);

        if ($children === [] && $beds === 0) {
            return $breakdown;
        }

        $total = round($total, 2);
        $breakdown['children'] = $children;
        $breakdown['children_total'] = $total;
        $breakdown['extra_beds'] = $beds === 0 ? null : [
            'count' => $beds,
            'per_night' => $periods === [] ? 0.0 : round($bedsTotal / count($periods), 2),
            'total' => $bedsTotal,
            'third_adult_reduction' => $policy->third_adult_reduction === null ? null : (float) $policy->third_adult_reduction,
        ];
        $total = round($total + $bedsTotal, 2);
        $breakdown['original_price'] = round((float) ($breakdown['original_price'] ?? 0) + $total, 2);
        $breakdown['final_price'] = round((float) ($breakdown['final_price'] ?? 0) + $total, 2);

        return $breakdown;
    }

    /** One person's share of a night's room (as booked, with its view) — without the meals. */
    public function personNight(array $period, float $mealsPerRoom, int $occupancy): float
    {
        return max((float) ($period['price'] ?? 0) - $mealsPerRoom, 0) / max($occupancy, 1);
    }

    /**
     * The meal plan of ONE night (a season may price it differently from the next): what it adds to the room, and one adult's share.
     *
     * @param  array<string,mixed>  $period
     * @param  array{0:float,1:float}  $fallback  the first night's, for a breakdown that carries no lines per night
     * @return array{0:float,1:float}
     */
    public function periodMeals(array $period, array $fallback): array
    {
        return array_key_exists('modifiers', $period) ? $this->meals((array) $period['modifiers']) : $fallback;
    }

    /**
     * The meal plan inside one night: what it adds to the room's price, and what ONE adult's share of it is.
     *
     * @param  array<int,array<string,mixed>>  $lines  the breakdown's modifier lines
     * @return array{0:float,1:float}
     */
    public function meals(array $lines): array
    {
        $mealOptions = $this->mealOptionIds();
        $room = 0.0;
        $person = 0.0;

        foreach ($lines as $l) {
            if (! in_array((int) ($l['option_id'] ?? 0), $mealOptions, true)) {
                continue;
            }

            $amount = (float) ($l['amount'] ?? 0);
            $room += $amount;
            $person += ! empty($l['per_person']) ? $amount / max((int) ($l['people'] ?? 1), 1) : $amount;
        }

        return [$room, $person];
    }

    /** @return list<int> the options of «نظام الوجبات» */
    private function mealOptionIds(): array
    {
        return RequestMemo::remember('item-types', 'meal-plan-options', function () {
            $group = OptionGroup::query()->where('name_ar', 'نظام الوجبات')->value('id');

            return $group ? \App\Models\Option::query()->where('group_id', $group)->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
        });
    }
}
