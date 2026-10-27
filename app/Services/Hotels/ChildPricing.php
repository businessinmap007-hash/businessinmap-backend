<?php

namespace App\Services\Hotels;

use App\Models\BookableItem;
use App\Models\ChildPolicy;
use App\Models\OptionGroup;
use App\Support\RequestMemo;

/**
 * What the children of a stay add to its price, by the hotel's «سياسة الأطفال»: each child pays the percentage of its age band of every
 * night's price (the room as booked, with its view — not the meals), and, when the hotel says so, the same percentage of the meal plan
 * one adult pays. A child the policy calls free adds nothing; a guest of adult age is inside the room's own price.
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
    public function apply(array $breakdown, ?BookableItem $bookable, array $ages): array
    {
        $ages = array_values(array_filter(array_map('intval', $ages), fn ($a) => $a >= 0));

        if ($ages === [] || ! $bookable || $bookable->item_type !== 'booking_stay') {
            return $breakdown;
        }

        $policy = ChildPolicy::forBusiness((int) $bookable->business_id);

        if (! $policy) {
            return $breakdown; // a hotel with no policy: children stay as before — free, not counted
        }

        [$mealsPerRoom, $mealsPerPerson] = $this->meals((array) ($breakdown['modifiers'] ?? []));
        $periods = (array) ($breakdown['periods'] ?? []);
        $children = [];
        $total = 0.0;

        foreach ($ages as $age) {
            $percent = $policy->percentFor($age);
            $line = ['age' => $age, 'percent' => $percent, 'adult' => $policy->isAdult($age), 'per_night' => 0.0, 'total' => 0.0];

            if ($percent > 0) {
                foreach ($periods as $p) {
                    $night = max((float) ($p['price'] ?? 0) - $mealsPerRoom, 0);
                    $amount = $night * $percent / 100 + ($policy->meals_follow ? $mealsPerPerson * $percent / 100 : 0.0);
                    $line['total'] += $amount;
                }

                $line['total'] = round($line['total'], 2);
                $line['per_night'] = $periods === [] ? 0.0 : round($line['total'] / count($periods), 2);
            }

            $total += $line['total'];
            $children[] = $line;
        }

        $total = round($total, 2);
        $breakdown['children'] = $children;
        $breakdown['children_total'] = $total;
        $breakdown['original_price'] = round((float) ($breakdown['original_price'] ?? 0) + $total, 2);
        $breakdown['final_price'] = round((float) ($breakdown['final_price'] ?? 0) + $total, 2);

        return $breakdown;
    }

    /**
     * The meal plan inside one night: what it adds to the room's price, and what ONE adult's share of it is.
     *
     * @param  array<int,array<string,mixed>>  $lines  the breakdown's modifier lines
     * @return array{0:float,1:float}
     */
    private function meals(array $lines): array
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
