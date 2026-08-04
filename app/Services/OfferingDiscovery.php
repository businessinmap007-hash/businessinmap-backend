<?php

namespace App\Services;

use App\Models\BusinessServicePrice;
use App\Models\MenuItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finds the priced OFFERINGS behind a search, not just the businesses.
 *
 * Filtering by «عظام» used to answer with a list of hospitals and stop there:
 * the customer arrived at one and met a price list that never mentioned عظام
 * again. Now that a priced row names what it sells
 * ([[offering-vocabulary]]), the search can reach the row itself — «كشف عظام
 * 300» — and say which business it belongs to.
 *
 * Both priced surfaces are read at once through their common table:
 * `business_service_prices` (item type × line) and `menu_items` (a listing with
 * specs, images and a price). `offering_options` is joined FIRST so the query
 * stays a single paginable statement rather than two lists merged in memory.
 */
class OfferingDiscovery
{
    private const PRICE_TYPE = BusinessServicePrice::class;

    private const MENU_TYPE = MenuItem::class;

    /**
     * @param  array<int,int>  $optionIds  every one must be carried — a filter narrows
     * @param  array<int,string>  $itemTypes
     */
    public function search(
        int $childId,
        array $optionIds = [],
        int $serviceId = 0,
        array $itemTypes = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = $this->base($childId, $serviceId, $itemTypes);

        // Narrowing, not widening: the offering must carry EVERY option asked
        // for, whether as the thing sold or as something qualifying it.
        foreach ($optionIds as $optionId) {
            $query->whereExists(function ($sub) use ($optionId) {
                $sub->from('offering_options as f')
                    ->whereColumn('f.offering_type', 'oo.offering_type')
                    ->whereColumn('f.offering_id', 'oo.offering_id')
                    ->where('f.option_id', $optionId);
            });
        }

        $rows = $query
            // Price first, and deliberately so: a merchant's own sequence must
            // not lift him above a cheaper competitor, or «مميّز» gets ticked
            // on everything and stops meaning anything. It only decides among
            // rows that are otherwise equal.
            ->orderByRaw('COALESCE(p.price, m.base_price) ASC')
            ->orderByRaw('COALESCE(p.is_featured, m.is_featured, 0) DESC')
            ->orderByRaw('COALESCE(p.sort_order, m.sort_order, 0) ASC')
            ->orderBy('oo.id')
            ->paginate($perPage, ['*'], 'page');

        $rows->setCollection($this->decorate(collect($rows->items())));

        return $rows;
    }

    /** The option ids these offerings carry, with how many offerings each has. */
    public function facets(int $childId, int $serviceId = 0, array $itemTypes = []): Collection
    {
        return $this->base($childId, $serviceId, $itemTypes)
            ->reorder()
            ->select('all_options.option_id', DB::raw('COUNT(DISTINCT all_options.id) AS offerings'))
            ->joinSub(
                DB::table('offering_options')->select('id', 'offering_type', 'offering_id', 'option_id'),
                'all_options',
                function ($join) {
                    $join->on('all_options.offering_type', '=', 'oo.offering_type')
                        ->on('all_options.offering_id', '=', 'oo.offering_id');
                }
            )
            ->groupBy('all_options.option_id')
            ->pluck('offerings', 'option_id');
    }

    /**
     * Every offering whose line is named, joined to whichever table owns it.
     *
     * The line row is the anchor — one per offering — so an offering appears
     * once however many modifiers it carries.
     */
    private function base(int $childId, int $serviceId, array $itemTypes): Builder
    {
        return DB::table('offering_options as oo')
            ->where('oo.role', 'line')
            ->leftJoin('business_service_prices as p', function ($join) {
                $join->on('p.id', '=', 'oo.offering_id')
                    ->where('oo.offering_type', '=', self::PRICE_TYPE);
            })
            ->leftJoin('menu_items as m', function ($join) {
                $join->on('m.id', '=', 'oo.offering_id')
                    ->where('oo.offering_type', '=', self::MENU_TYPE);
            })
            ->join('users as u', function ($join) {
                $join->on('u.id', '=', DB::raw('COALESCE(p.business_id, m.business_id)'));
            })
            ->where('u.type', 'business')
            ->where('u.category_child_id', $childId)
            // an offering nobody switched on is not for sale
            ->whereRaw('COALESCE(p.is_active, m.is_active, 0) = 1')
            ->when($serviceId > 0, fn ($q) => $q->where('p.service_id', $serviceId))
            ->when($itemTypes !== [], fn ($q) => $q->whereIn('p.bookable_item_type', $itemTypes))
            ->select([
                'oo.id as link_id',
                'oo.offering_type',
                'oo.offering_id',
                'oo.option_id as line_option_id',
                'u.id as business_id',
                'u.name as business_name',
                'u.logo as business_logo',
                'p.service_id',
                'p.bookable_item_type',
                'p.currency',
                DB::raw('COALESCE(p.price, m.base_price) as price'),
                DB::raw('COALESCE(m.name_ar, "") as menu_name_ar'),
                DB::raw('COALESCE(m.name_en, "") as menu_name_en'),
                'm.image as image',
            ]);
    }

    /** Attach each offering's option names, so the row can say what it is. */
    private function decorate(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $keys = $rows->map(fn ($r) => $r->offering_type . ':' . $r->offering_id);

        $links = DB::table('offering_options as oo')
            ->join('options as o', 'o.id', '=', 'oo.option_id')
            ->whereIn(DB::raw("CONCAT(oo.offering_type, ':', oo.offering_id)"), $keys)
            ->orderBy('oo.sort_order')
            ->get(['oo.offering_type', 'oo.offering_id', 'oo.role', 'o.id', 'o.name_ar', 'o.name_en'])
            ->groupBy(fn ($l) => $l->offering_type . ':' . $l->offering_id);

        return $rows->map(function ($row) use ($links) {
            $own = $links->get($row->offering_type . ':' . $row->offering_id, collect());

            $row->line = $own->firstWhere('role', 'line');
            $row->modifiers = $own->where('role', 'modifier')->values();
            $row->source = $row->offering_type === self::MENU_TYPE ? 'menu' : 'price';

            return $row;
        });
    }
}
