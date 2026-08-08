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
        int $perPage = 20,
        int $businessId = 0
    ): LengthAwarePaginator {
        $query = $this->base($childId, $serviceId, $itemTypes, $businessId);

        // Narrowing, not widening: the offering must satisfy EVERY axis asked
        // about — the thing sold AND what qualifies it.
        //
        // Within ONE axis the options are alternatives, and the customer means
        // «BMW or مرسيدس», not a car that is both. Requiring every id outright
        // answered such a tap with an empty list, which is why two brands could
        // never be compared. With one option per axis — the ordinary case — this
        // is exactly the old behaviour.
        foreach ($this->byGroup($optionIds) as $inGroup) {
            $query->whereExists(function ($sub) use ($inGroup) {
                $sub->from('offering_options as f')
                    ->whereColumn('f.offering_type', 'oo.offering_type')
                    ->whereColumn('f.offering_id', 'oo.offering_id')
                    ->whereIn('f.option_id', $inGroup);
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
     * The COMBINATIONS on offer, each with how many offerings carry it.
     *
     *     غرفة نوم — مودرن        12
     *     غرفة نوم — كلاسيك        8
     *     ركنة — ألترا مودرن       7
     *
     * `facets()` above answers "which options exist", which makes the customer
     * assemble the combination himself and then narrow again by service —
     * محافظة، تصنيف، ابن، خيارات، خدمات, five steps to a bedroom. This answers
     * "which things are actually sold", so one tap does the whole job: the
     * returned `option_ids` are exactly what `search()` wants back.
     *
     * @return Collection<int,array{key:string,label:string,option_ids:array<int,int>,offerings:int}>
     */
    public function combinations(int $childId, int $serviceId = 0, array $itemTypes = [], int $limit = 60): Collection
    {
        $anchors = $this->base($childId, $serviceId, $itemTypes)
            ->reorder()
            ->select(['oo.offering_type', 'oo.offering_id'])
            ->get();

        if ($anchors->isEmpty()) {
            return collect();
        }

        $links = DB::table('offering_options as oo')
            ->join('options as o', 'o.id', '=', 'oo.option_id')
            ->whereIn(
                DB::raw("CONCAT(oo.offering_type, ':', oo.offering_id)"),
                $anchors->map(fn ($a) => $a->offering_type . ':' . $a->offering_id)
            )
            ->orderBy('oo.sort_order')
            ->orderBy('oo.id')
            ->get(['oo.offering_type', 'oo.offering_id', 'oo.role', 'o.id', 'o.name_ar', 'o.name_en'])
            ->groupBy(fn ($l) => $l->offering_type . ':' . $l->offering_id);

        $out = [];

        foreach ($anchors as $anchor) {
            $own = $links->get($anchor->offering_type . ':' . $anchor->offering_id, collect());
            $line = $own->firstWhere('role', 'line');

            if (! $line) {
                continue;
            }

            // Sorted, so the same combination entered in a different order is
            // one heading and not two identical ones.
            $modifiers = $own->where('role', 'modifier')->sortBy('id')->values();
            $ids = $modifiers->pluck('id')->map(fn ($id) => (int) $id);
            $key = (int) $line->id . ($ids->isEmpty() ? '' : ':' . $ids->implode(','));

            if (! isset($out[$key])) {
                $out[$key] = [
                    'key' => $key,
                    'label' => collect([$line])->merge($modifiers)
                        ->map(fn ($o) => app()->getLocale() === 'en' ? ($o->name_en ?: $o->name_ar) : ($o->name_ar ?: $o->name_en))
                        ->implode(' — '),
                    'option_ids' => $ids->prepend((int) $line->id)->all(),
                    'offerings' => 0,
                ];
            }

            $out[$key]['offerings']++;
        }

        return collect($out)
            ->sortByDesc('offerings')
            ->values()
            ->take($limit);
    }

    /**
     * The AXES a customer chooses along, drawn from what this set actually
     * offers: «نوع المركبة: SUV ٤ · سيدان ٦», «الماركة: BMW ٣», «نوع التعامل:
     * بيع ٧ · إيجار ٣». One tap per axis and the row is found.
     *
     * Counts are computed per axis with the OTHER axes' choices applied but not
     * this axis's own. A customer who picked BMW must still see مرسيدس beside
     * it — count everything under the full selection and every sibling of the
     * chosen option reads 0, so switching brand means clearing the filter first.
     *
     * The whole set for one business is small enough to fold in PHP; doing it
     * here rather than in N queries is what makes the leave-one-out counting
     * affordable at all.
     *
     * @param  array<int,int>  $selected  option ids the customer has already tapped
     * @return Collection<int,array{group_id:int,name:string,role:string,options:array<int,array{id:int,name:string,offerings:int,selected:bool}>}>
     */
    public function axes(
        int $childId,
        int $serviceId = 0,
        array $itemTypes = [],
        int $businessId = 0,
        array $selected = []
    ): Collection {
        $anchors = $this->base($childId, $serviceId, $itemTypes, $businessId)
            ->reorder()
            ->select(['oo.offering_type', 'oo.offering_id'])
            ->get();

        if ($anchors->isEmpty()) {
            return collect();
        }

        $links = DB::table('offering_options as oo')
            ->join('options as o', 'o.id', '=', 'oo.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->whereIn(
                DB::raw("CONCAT(oo.offering_type, ':', oo.offering_id)"),
                $anchors->map(fn ($a) => $a->offering_type . ':' . $a->offering_id)
            )
            ->where('g.is_active', 1)
            ->orderByRaw(\App\Models\OptionGroup::displayOrderSql('g'))
            ->orderByRaw('COALESCE(g.reorder, 999999) ASC')
            ->orderBy('o.id')
            ->get(['oo.offering_type', 'oo.offering_id', 'o.id', 'o.name_ar', 'o.name_en',
                'g.id as group_id', 'g.name_ar as group_name_ar', 'g.name_en as group_name_en', 'g.price_role']);

        if ($links->isEmpty()) {
            return collect();
        }

        // group_id => the options of it the customer has tapped
        $groupOf = $links->pluck('group_id', 'id');
        $chosen = [];

        foreach ($this->cleanIds($selected) as $optionId) {
            if (isset($groupOf[$optionId])) {
                $chosen[(int) $groupOf[$optionId]][] = $optionId;
            }
        }

        $carried = $links->groupBy(fn ($l) => $l->offering_type . ':' . $l->offering_id)
            ->map(fn ($own) => $own->pluck('id')->map(fn ($id) => (int) $id)->all());

        $axes = [];

        foreach ($links as $link) {
            $groupId = (int) $link->group_id;

            $axes[$groupId] ??= [
                'group_id' => $groupId,
                'name' => $this->label($link->group_name_ar, $link->group_name_en),
                'role' => (string) $link->price_role,
                'options' => [],
            ];

            $axes[$groupId]['options'][(int) $link->id] ??= [
                'id' => (int) $link->id,
                'name' => $this->label($link->name_ar, $link->name_en),
                'offerings' => 0,
                'selected' => in_array((int) $link->id, $chosen[$groupId] ?? [], true),
            ];
        }

        foreach ($axes as $groupId => &$axis) {
            foreach ($carried as $own) {
                if (! $this->satisfies($own, $chosen, $groupId)) {
                    continue;
                }

                foreach ($own as $optionId) {
                    if (isset($axis['options'][$optionId])) {
                        $axis['options'][$optionId]['offerings']++;
                    }
                }
            }

            $axis['options'] = array_values($axis['options']);
        }

        unset($axis);

        return collect(array_values($axes));
    }

    /**
     * Does this offering meet every choice EXCEPT the axis being counted?
     *
     * @param  array<int,int>  $own
     * @param  array<int,array<int,int>>  $chosen  group id => option ids
     */
    private function satisfies(array $own, array $chosen, int $ignoreGroup): bool
    {
        foreach ($chosen as $groupId => $optionIds) {
            if ($groupId === $ignoreGroup) {
                continue;
            }

            // Within one axis the choices are alternatives — SUV or سيدان — so
            // any of them will do. Across axes they narrow, and each must hold.
            if (! array_intersect($own, $optionIds)) {
                return false;
            }
        }

        return true;
    }

    private function label(?string $ar, ?string $en): string
    {
        $primary = app()->getLocale() === 'en' ? $en : $ar;

        return trim((string) ($primary ?: ($ar ?: $en) ?: ''));
    }

    /**
     * The asked-for options, bucketed by the group each belongs to.
     *
     * An option with no group is its own bucket: it still has to be carried,
     * and it must not join another option's alternatives by accident.
     *
     * @param  array<int,int>  $optionIds
     * @return array<int|string,array<int,int>>
     */
    private function byGroup(array $optionIds): array
    {
        $optionIds = $this->cleanIds($optionIds);

        if ($optionIds === []) {
            return [];
        }

        $groups = DB::table('options')->whereIn('id', $optionIds)->pluck('group_id', 'id');
        $buckets = [];

        foreach ($optionIds as $optionId) {
            $groupId = (int) ($groups[$optionId] ?? 0);
            $buckets[$groupId > 0 ? 'g' . $groupId : 'o' . $optionId][] = $optionId;
        }

        return $buckets;
    }

    /** @return array<int,int> */
    private function cleanIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn ($id) => $id > 0
        )));
    }

    /**
     * Every offering whose line is named, joined to whichever table owns it.
     *
     * The line row is the anchor — one per offering — so an offering appears
     * once however many modifiers it carries.
     *
     * `$childId` narrows to a SPECIALTY and `$businessId` to one shop; either
     * may stand alone. A search across a child is the first; opening one shop's
     * window is the second, and it must not also demand the child, because a
     * business is reached by id long before anyone knows what child it sits on.
     */
    private function base(int $childId, int $serviceId, array $itemTypes, int $businessId = 0): Builder
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
            ->when($childId > 0, fn ($q) => $q->where('u.category_child_id', $childId))
            ->when($businessId > 0, fn ($q) => $q->where('u.id', $businessId))
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
