<?php

namespace App\Services\Hotels;

use App\Models\BookableItem;
use App\Models\HotelSeason;
use App\Support\RequestMemo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * A hotel's «المواسم»: the night price of its room types in a season, the price of its meal plans in it, and the shortest stay that may
 * arrive in it (MinLOS on arrival — the way channel managers and rate sheets write it). A room type's own price rule, written for it alone,
 * still wins over the hotel's season.
 */
final class HotelSeasons
{
    /** @return Collection<int,HotelSeason> the hotel's seasons, oldest first — read once a request */
    public function of(int $businessId): Collection
    {
        return RequestMemo::remember('seasons', (string) $businessId, fn () => HotelSeason::query()
            ->where('business_id', $businessId)->orderBy('start_date')->get());
    }

    public function on(int $businessId, string $date): ?HotelSeason
    {
        return $businessId > 0 ? $this->of($businessId)->first(fn (HotelSeason $s) => $s->covers($date)) : null;
    }

    /**
     * A night of a room type in the season of its date.
     *
     * @return array{price:float,rule:array<string,mixed>|null}|null null when no season covers the night (or it is not a hotel room)
     */
    public function priceNight(BookableItem $item, float $base, string $date): ?array
    {
        if ($item->item_type !== 'booking_stay') {
            return null;
        }

        $season = $this->on((int) $item->business_id, $date);

        if (! $season) {
            return null;
        }

        return [
            'price' => $season->roomPrice((int) $item->id, $base),
            'rule' => ['id' => 0, 'season_id' => (int) $season->id, 'title' => (string) $season->name, 'rule_type' => 'season'],
        ];
    }

    /**
     * The meal-plan rows of a night at the season's meal prices: a copy of each row the season prices, the rest as they are.
     *
     * @param  Collection<int,\App\Models\OfferingOption>  $rows
     */
    public function mealRows(Collection $rows, ?BookableItem $item, string $date): Collection
    {
        $season = $item && $item->item_type === 'booking_stay' ? $this->on((int) $item->business_id, $date) : null;

        if (! $season || empty($season->meal_prices)) {
            return $rows;
        }

        return $rows->map(function ($row) use ($season) {
            $price = $season->mealPrice((int) $row->option_id);

            if ($price === null) {
                return $row;
            }

            $copy = clone $row;
            $copy->adjust_type = \App\Models\OfferingOption::ADJUST_AMOUNT;
            $copy->adjust_value = $price;

            return $copy;
        });
    }

    /** «أقل إقامة»: a stay arriving in a season with a shortest stay must be at least that long. */
    public function assertMinStay(?BookableItem $item, mixed $start, int $nights): void
    {
        if (! $item || $item->item_type !== 'booking_stay' || ! $start) {
            return;
        }

        $season = $this->on((int) $item->business_id, Carbon::parse($start)->toDateString());

        if ($season && $season->min_nights && $nights < (int) $season->min_nights) {
            throw ValidationException::withMessages([
                'ends_at' => __('أقل إقامة في موسم :name :n ليالٍ.', ['name' => $season->name, 'n' => $season->min_nights]),
            ]);
        }
    }
}
