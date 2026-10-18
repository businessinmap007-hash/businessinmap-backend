<?php

namespace App\Services\Hotels;

use App\Models\BookableItem;
use App\Services\BookableAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * «تصنيف الإقامة» of a hotel — the stars it ticked about itself — and the one question the hotel list asks about a stay.
 *
 * A hotel that ticked nothing is «غير مصنّف»: the list still says so, so a customer can tell «not rated» from «not shown».
 */
final class StayClass
{
    public const GROUP = 'تصنيف الإقامة';

    public const UNRATED = 'غير مصنّف';

    public function __construct(private readonly BookableAvailabilityService $availability) {}

    /**
     * Only for businesses that let a room (a hotel, a resort, a guest house): the rest are not ranked in stars.
     *
     * @param  list<int>  $businessIds
     * @return array<int,array{id:int,name:string}> business id → its class
     */
    public function forBusinesses(array $businessIds): array
    {
        if ($businessIds === []) {
            return [];
        }

        $english = app()->getLocale() === 'en';
        $options = DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', self::GROUP)->orderBy('o.id')->get(['o.id', 'o.name_ar', 'o.name_en']);

        if ($options->isEmpty()) {
            return [];
        }

        $order = $options->pluck('id')->flip();
        $name = fn ($o) => (string) ($english ? ($o->name_en ?: $o->name_ar) : ($o->name_ar ?: $o->name_en));
        $byId = $options->keyBy('id');
        $unrated = $options->firstWhere('name_ar', self::UNRATED);

        $lets = DB::table('bookable_items')->whereIn('business_id', $businessIds)->where('item_type', 'booking_stay')->where('is_active', 1)
            ->distinct()->pluck('business_id')->map(fn ($id) => (int) $id)->flip();

        $ticked = DB::table('option_user')->whereIn('user_id', $businessIds)->whereIn('option_id', $order->keys())
            ->get(['user_id', 'option_id'])->groupBy('user_id')
            ->map(fn ($rows) => (int) $rows->sortBy(fn ($r) => $order[$r->option_id])->first()->option_id);

        $out = [];

        foreach ($businessIds as $id) {
            if (! isset($lets[$id])) {
                continue;
            }

            $optionId = $ticked[$id] ?? ($unrated->id ?? null);

            if ($optionId !== null) {
                $out[$id] = ['id' => (int) $optionId, 'name' => $name($byId[$optionId])];
            }
        }

        return $out;
    }

    /**
     * Of these businesses, the ones with at least one room FREE for the stay.
     *
     * @param  list<int>  $businessIds
     * @return list<int>
     */
    public function freeFor(array $businessIds, Carbon $checkIn, Carbon $checkOut): array
    {
        $units = BookableItem::query()->whereIn('business_id', $businessIds)->where('item_type', 'booking_stay')->where('is_active', 1)
            ->where('status', '!=', BookableItem::STATUS_MAINTENANCE)->get();

        $free = [];

        foreach ($units->groupBy('business_id') as $businessId => $rooms) {
            if ($rooms->contains(fn (BookableItem $u) => (bool) $this->availability->check($u, $checkIn, $checkOut)['available'])) {
                $free[] = (int) $businessId;
            }
        }

        return $free;
    }
}
