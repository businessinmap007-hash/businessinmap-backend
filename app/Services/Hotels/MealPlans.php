<?php

namespace App\Services\Hotels;

use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Support\RequestMemo;
use Illuminate\Support\Facades\DB;

/**
 * The meal plans as Egyptian hotels sell them, and the meals each one gives: breakfast (BB) → breakfast; half board (HB) → breakfast and
 * dinner; full board (FB) and all inclusive (AI) → all three. The hotel writes its meal times once; a guest reads, on his booking, the
 * times of the meals his plan gives.
 */
final class MealPlans
{
    public const MEALS = ['breakfast', 'lunch', 'dinner'];

    /** Which meals each plan gives, by the option's English name (the names are the trade's own codes). */
    private const GIVES = [
        'Breakfast Included' => ['breakfast'],
        'Half Board' => ['breakfast', 'dinner'],
        'Full Board' => ['breakfast', 'lunch', 'dinner'],
        'All Inclusive' => ['breakfast', 'lunch', 'dinner'],
    ];

    /** @return array<int,list<string>> option id => the meals it gives */
    public function plans(): array
    {
        return RequestMemo::remember('item-types', 'meal-plans', function () {
            $group = OptionGroup::query()->where('name_ar', 'نظام الوجبات')->value('id');

            return $group ? Option::query()->where('group_id', $group)->get(['id', 'name_en'])
                ->mapWithKeys(fn ($o) => [(int) $o->id => self::GIVES[$o->name_en] ?? []])->all() : [];
        });
    }

    /**
     * The meal plans THIS hotel sells, at its own price (a season may price them otherwise).
     *
     * @return list<array{option_id:int,name:?string,price:float,per_person:bool}>
     */
    public function offered(int $businessId): array
    {
        $ids = array_keys($this->plans());

        if ($ids === [] || $businessId <= 0) {
            return [];
        }

        return \App\Models\OfferingOption::query()->with('option:id,name_ar,name_en')
            ->where('offering_type', (new \App\Models\User)->getMorphClass())->where('offering_id', $businessId)
            ->where('role', \App\Models\OfferingOption::ROLE_MODIFIER)->whereIn('option_id', $ids)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn ($r) => [
                'option_id' => (int) $r->option_id,
                'name' => app()->getLocale() === 'en' ? ($r->option?->name_en ?: $r->option?->name_ar) : ($r->option?->name_ar ?: $r->option?->name_en),
                'price' => (float) $r->adjust_value,
                'per_person' => (bool) $r->per_person,
            ])->values()->all();
    }

    /** @return array<string,array{from:?string,to:?string}>|null the hotel's meal times, null until written */
    public function times(int $businessId): ?array
    {
        $row = DB::table('business_meal_times')->where('business_id', $businessId)->first();

        if (! $row) {
            return null;
        }

        $out = [];

        foreach (self::MEALS as $meal) {
            $out[$meal] = ['from' => $row->{$meal . '_from'}, 'to' => $row->{$meal . '_to'}];
        }

        return $out;
    }

    /** @param array<string,array{from:?string,to:?string}> $times */
    public function saveTimes(int $businessId, array $times): void
    {
        $values = [];

        foreach (self::MEALS as $meal) {
            $values[$meal . '_from'] = $times[$meal]['from'] ?? null;
            $values[$meal . '_to'] = $times[$meal]['to'] ?? null;
        }

        DB::table('business_meal_times')->updateOrInsert(['business_id' => $businessId], $values + ['updated_at' => now(), 'created_at' => now()]);
    }

    /**
     * The meals a stay's plan gives, with the hotel's times — what the guest reads on his booking. Empty for a stay with no meal plan.
     *
     * @return list<array{meal:string,from:?string,to:?string}>
     */
    public function forBooking(Booking $booking): array
    {
        $item = $booking->bookable;

        if (! $item instanceof BookableItem || $item->item_type !== 'booking_stay') {
            return [];
        }

        $chosen = array_map('intval', (array) (($booking->meta ?? [])['requested_option_ids'] ?? []));
        $meals = [];

        foreach ($this->plans() as $optionId => $gives) {
            if (in_array($optionId, $chosen, true)) {
                $meals = array_merge($meals, $gives);
            }
        }

        $times = $this->times((int) $item->business_id) ?? [];

        return array_values(array_map(fn ($m) => ['meal' => $m, 'from' => $times[$m]['from'] ?? null, 'to' => $times[$m]['to'] ?? null], array_values(array_unique($meals))));
    }
}
