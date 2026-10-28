<?php

namespace App\Services\Hotels;

use App\Models\BookableItem;
use App\Models\ChildPolicy;
use App\Models\GalaDinner;

/**
 * «العشاء الإلزامي»: every night of a stay that is one of the hotel's gala nights adds its dinner — each adult the adult price, each child
 * the hotel's percentage of it, a child under the free age nothing. Compulsory: it is added whatever meal plan the guest chose, and on a
 * partner's sale too (the dinner is the hotel's).
 */
final class GalaDinners
{
    /** @return list<array<string,mixed>> the hotel's gala nights from today on */
    public function upcoming(int $businessId): array
    {
        return GalaDinner::query()->where('business_id', $businessId)->whereDate('date', '>=', now()->toDateString())
            ->orderBy('date')->get()->map(fn (GalaDinner $g) => $g->payload())->all();
    }

    /**
     * @param  array<string,mixed>  $breakdown  the stay's price breakdown
     * @param  list<int>  $ages  the children's ages (an age of an adult pays as one)
     * @return array<string,mixed> the breakdown with `gala_dinners`, `gala_total` and the totals raised by it
     */
    public function apply(array $breakdown, ?BookableItem $bookable, array $ages, int $adults): array
    {
        if (! $bookable || $bookable->item_type !== 'booking_stay') {
            return $breakdown;
        }

        $nights = array_values(array_filter(array_map(fn ($p) => (string) ($p['date'] ?? ''), (array) ($breakdown['periods'] ?? []))));

        if ($nights === []) {
            return $breakdown;
        }

        $galas = GalaDinner::query()->where('business_id', (int) $bookable->business_id)->whereIn('date', $nights)->orderBy('date')->get();

        if ($galas->isEmpty()) {
            return $breakdown;
        }

        $adultAge = (int) (ChildPolicy::forBusiness((int) $bookable->business_id)?->adult_from_age ?? 12);
        $ages = array_values(array_filter(array_map('intval', $ages), fn ($a) => $a >= 0));
        $adults = max($adults, 1) + count(array_filter($ages, fn ($a) => $a >= $adultAge));
        $children = array_values(array_filter($ages, fn ($a) => $a < $adultAge));
        $lines = [];
        $total = 0.0;

        foreach ($galas as $g) {
            $price = (float) $g->adult_price;
            $paying = array_values(array_filter($children, fn ($a) => $a >= (int) $g->child_free_under));
            $childPrice = round($price * (float) $g->child_percent / 100, 2);
            $amount = round($adults * $price + count($paying) * $childPrice, 2);

            $lines[] = [
                'date' => $g->date->toDateString(), 'name' => (string) $g->name, 'adults' => $adults, 'adult_price' => $price,
                'children' => count($paying), 'child_price' => $childPrice, 'free_children' => count($children) - count($paying), 'total' => $amount,
            ];
            $total += $amount;
        }

        $total = round($total, 2);
        $breakdown['gala_dinners'] = $lines;
        $breakdown['gala_total'] = $total;
        $breakdown['original_price'] = round((float) ($breakdown['original_price'] ?? 0) + $total, 2);
        $breakdown['final_price'] = round((float) ($breakdown['final_price'] ?? 0) + $total, 2);

        return $breakdown;
    }
}
