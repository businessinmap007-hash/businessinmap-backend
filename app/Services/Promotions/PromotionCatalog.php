<?php

namespace App\Services\Promotions;

use App\Models\BookableItem;
use App\Models\BusinessCatalogListing;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Promotion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What a promotion can be pointed at, and how the public reads it. Three readers share one description of a business's sellable
 * rows: the merchant's picker (sections → items), the business page's offers tab, and the badge on an item card.
 */
final class PromotionCatalog
{
    public function __construct(private readonly PromotionPricing $pricing)
    {
    }

    /**
     * The merchant's products by section: «اختيار القسم كله أو بعض منه».
     *
     * @return array{menu: list<array<string,mixed>>, shop: list<array<string,mixed>>, booking: list<array<string,mixed>>}
     */
    public function pickerFor(int $businessId): array
    {
        $items = MenuItem::query()->where('business_id', $businessId)->where('is_active', true)
            ->orderByRaw('COALESCE(sort_order, 999999)')->orderBy('id')->get();
        $sections = MenuSection::query()->where('business_id', $businessId)->get()->keyBy('id');

        $menu = $items->groupBy(fn (MenuItem $i) => (int) ($i->menu_section_id ?? 0))->map(function (Collection $group, $sectionId) use ($sections) {
            $section = $sections->get((int) $sectionId);

            return [
                'type' => $section ? Promotion::TARGET_MENU_SECTION : null,
                'id' => $section ? (int) $section->id : null,
                'name' => $section ? (string) $section->loc('name') : null,
                'items' => $group->map(fn (MenuItem $i) => ['type' => Promotion::TARGET_MENU_ITEM, 'id' => (int) $i->id, 'name' => (string) $i->loc('name'), 'price' => (float) $i->base_price])->values()->all(),
            ];
        })->values()->all();

        $rows = DB::table('business_catalog_listings as l')
            ->join('catalog_products as p', 'p.id', '=', 'l.catalog_product_id')
            ->leftJoin('product_categories as c', 'c.id', '=', 'p.product_category_id')
            ->where('l.business_id', $businessId)->where('l.is_active', 1)
            ->orderBy('l.id')
            ->get(['l.id', 'l.price', 'p.name_ar', 'p.name_en', 'p.product_category_id', 'c.name_ar as category_ar', 'c.name_en as category_en']);

        $shop = $rows->groupBy(fn ($r) => (int) ($r->product_category_id ?? 0))->map(fn (Collection $group, $categoryId) => [
            'type' => $categoryId ? Promotion::TARGET_LISTING_CATEGORY : null,
            'id' => $categoryId ?: null,
            'name' => $group->first()->category_ar ?: $group->first()->category_en,
            'items' => $group->map(fn ($r) => ['type' => Promotion::TARGET_LISTING, 'id' => (int) $r->id, 'name' => (string) ($r->name_ar ?: $r->name_en), 'price' => (float) $r->price])->values()->all(),
        ])->values()->all();

        return ['menu' => $menu, 'shop' => $shop, 'booking' => $this->bookingGroups($businessId)];
    }

    /**
     * The rooms (or pitches, halls…) by kind, each with the night's price: «اختر النوع كله أو بعض غرفه».
     *
     * @return list<array<string,mixed>>
     */
    private function bookingGroups(int $businessId): array
    {
        $units = BookableItem::query()->with('lineOption:id,name_ar,name_en')->where('business_id', $businessId)->where('is_active', 1)->orderBy('line_option_id')->orderBy('code')->orderBy('id')->get();
        $prices = app(\App\Services\BusinessServicePriceResolver::class);
        $out = [];

        foreach ($units->groupBy(fn (BookableItem $u) => (int) ($u->line_option_id ?? 0)) as $kindId => $inKind) {
            $first = $inKind->first();
            $option = $first->lineOption;
            $price = $prices->resolveForBookableItem($first);
            $night = $price ? round((float) $price->baseUnitPrice(), 2) : 0.0;

            $out[] = [
                'type' => $kindId ? Promotion::TARGET_BOOKABLE_KIND : null,
                'id' => $kindId ?: null,
                'name' => $option ? (string) ($option->name_ar ?: $option->name_en) : null,
                'items' => $inKind->map(fn (BookableItem $u) => ['type' => Promotion::TARGET_BOOKABLE_ITEM, 'id' => (int) $u->id, 'name' => (string) $u->displayLabel(), 'price' => $night])->values()->all(),
            ];
        }

        // the hotel rooms this business was given to sell: offered at the company's own price, picked one by one
        $sold = app(\App\Services\Partnerships\AllocationService::class)->soldBy($businessId);

        foreach ($sold->groupBy('owner_business_id') as $hotelId => $allocations) {
            $out[] = [
                'type' => null,
                'id' => null,
                'name' => (string) optional($allocations->first()->ownerBusiness)->name,
                'items' => $allocations->map(fn ($a) => ['type' => Promotion::TARGET_BOOKABLE_ITEM, 'id' => (int) $a->bookable_item_id, 'name' => (string) $a->bookableItem->displayLabel(), 'price' => round($a->finalPrice(), 2)])->values()->all(),
            ];
        }

        return $out;
    }

    /** Names for a promotion's targets, for lists. @return list<array{type:string,id:?int,name:string}> */
    public function targetNames(Promotion $promotion): array
    {
        $out = [];

        foreach ($promotion->targets as $t) {
            $name = match ($t->target_type) {
                Promotion::TARGET_ALL => null,
                Promotion::TARGET_MENU_SECTION => MenuSection::query()->find($t->target_id)?->loc('name'),
                Promotion::TARGET_MENU_ITEM => MenuItem::query()->find($t->target_id)?->loc('name'),
                Promotion::TARGET_LISTING => DB::table('business_catalog_listings as l')->join('catalog_products as p', 'p.id', '=', 'l.catalog_product_id')->where('l.id', $t->target_id)->value('p.name_ar'),
                Promotion::TARGET_LISTING_CATEGORY => DB::table('product_categories')->where('id', $t->target_id)->value('name_ar'),
                Promotion::TARGET_BOOKABLE_ITEM => BookableItem::query()->find($t->target_id)?->displayLabel(),
                Promotion::TARGET_BOOKABLE_KIND => BookableItem::query()->with('lineOption:id,name_ar,name_en')->where('business_id', $promotion->business_id)->where('line_option_id', $t->target_id)->first()?->lineOption?->name_ar,
                default => null,
            };

            $out[] = ['type' => (string) $t->target_type, 'id' => $t->target_id ? (int) $t->target_id : null, 'name' => (string) ($name ?? '')];
        }

        return $out;
    }

    /**
     * The items a promotion covers right now (menu items and listings), with the price a single piece would cost under it when the
     * rule can say that. Used by the offers tab; capped, since a whole-shop promotion covers everything.
     *
     * @return list<array<string,mixed>>
     */
    public function coveredItems(Promotion $promotion, int $limit = 12): array
    {
        $wanted = $promotion->targets->map(fn ($t) => $t->target_type . ':' . ($t->target_id ?? 0))->all();
        $all = in_array('all:0', $wanted, true);
        $picker = $this->pickerFor((int) $promotion->business_id);
        $out = [];

        foreach (['menu', 'shop', 'booking'] as $surface) {
            foreach ($picker[$surface] as $group) {
                $groupKey = $group['type'] ? $group['type'] . ':' . $group['id'] : null;

                foreach ($group['items'] as $item) {
                    if (! $all && ! in_array($item['type'] . ':' . $item['id'], $wanted, true) && ! ($groupKey && in_array($groupKey, $wanted, true))) {
                        continue;
                    }

                    $out[] = $item + ['price_after' => $this->pricing->unitPriceAfter($promotion, (float) $item['price'])];

                    if (count($out) >= $limit) {
                        return $out;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * The best single-piece offer on each item of a list, for the badge on its card: promotion id, kind, parameters and — for a
     * percentage or an amount — the price after it. Nothing for an item no running promotion covers.
     *
     * @param  'menu_item'|'listing'  $type
     * @param  list<int>  $ids
     * @return array<int,array<string,mixed>> item id => badge
     */
    public function badgesFor(int $businessId, string $type, array $ids, ?int $viewerId = null): array
    {
        if ($ids === []) {
            return [];
        }

        $promotions = $this->pricing->running($businessId, (int) $viewerId);

        if ($promotions->isEmpty()) {
            return [];
        }

        if ($type === Promotion::TARGET_MENU_ITEM) {
            $prices = MenuItem::query()->whereIn('id', $ids)->pluck('base_price', 'id');
            $groups = MenuItem::query()->whereIn('id', $ids)->pluck('menu_section_id', 'id');
            $groupType = Promotion::TARGET_MENU_SECTION;
        } else {
            $prices = BusinessCatalogListing::query()->whereIn('id', $ids)->pluck('price', 'id');
            $groups = DB::table('business_catalog_listings as l')->join('catalog_products as p', 'p.id', '=', 'l.catalog_product_id')->whereIn('l.id', $ids)->pluck('p.product_category_id', 'l.id');
            $groupType = Promotion::TARGET_LISTING_CATEGORY;
        }

        $out = [];

        foreach ($ids as $id) {
            $keys = ['all:0', $type . ':' . $id, $groupType . ':' . ($groups[$id] ?? 0)];
            $price = (float) ($prices[$id] ?? 0);

            if ($price <= 0 || ! $this->pricing->quietMonth($type === Promotion::TARGET_MENU_ITEM ? MenuItem::class : BusinessCatalogListing::class, (int) $id)) {
                continue;
            }

            foreach ($promotions as $promotion) {
                if (array_intersect($keys, $promotion->targets->map(fn ($t) => $t->target_type . ':' . ($t->target_id ?? 0))->all()) === []) {
                    continue;
                }

                $after = $this->pricing->unitPriceAfter($promotion, $price);
                $saving = $after !== null ? $price - $after : 0.0;
                $current = $out[$id] ?? null;

                // a priced cut beats a described one; between two cuts, the bigger saving
                if (! $current || ($after !== null && ($current['price_after'] === null || $saving > $current['saving']))) {
                    $out[$id] = ['promotion_id' => (int) $promotion->id, 'kind' => $promotion->kind, 'params' => $promotion->params, 'price_after' => $after, 'saving' => round($saving, 2)];
                }
            }
        }

        return array_map(fn ($b) => ['promotion_id' => $b['promotion_id'], 'kind' => $b['kind'], 'params' => $b['params'], 'price_after' => $b['price_after']], $out);
    }
}
