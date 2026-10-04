<?php

namespace App\Services\Menu;

use App\Models\MenuItem;
use App\Models\MenuItemExtra;
use App\Models\MenuItemExtraGroup;
use App\Models\OptionGroup;
use App\Models\PlatformService;
use App\Models\ServiceOptionGroupPlacement as Placement;
use App\Models\User;
use App\Services\Catalog\ServiceOptionPlacements;
use Illuminate\Support\Facades\DB;

/**
 * «خدمات المحل» — services a shop adds on top of what it sells, priced once for the whole shop and per
 * unit of what is bought («المشوى 50، المقلى 80، الصينية 100 للكيلو»). Which groups a trade has is the
 * placement usage `addon` («مكونات الخدمة»); the shop's prices are `business_addon_prices`; every item of
 * the shop carries them as ONE single-choice extra group, so the cart prices them (unit + extras, times
 * the quantity) and the invoice lists them without a second mechanism.
 */
final class BusinessAddons
{
    public function __construct(private readonly ServiceOptionPlacements $placements)
    {
    }

    /** @return list<int> the groups this shop's trade offers as services, in order */
    public function groupIds(int $businessId): array
    {
        $child = (int) User::query()->whereKey($businessId)->value('category_child_id');
        $menu = (int) PlatformService::query()->where('key', PlatformService::KEY_MENU)->value('id');

        if ($child <= 0 || $menu <= 0) {
            return [];
        }

        return $this->placements->for($menu, $child, Placement::USAGE_ADDON)
            ->pluck('option_group_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Every service with its options and the shop's price for each (null = not offered).
     *
     * @return list<array{group_id:int,group_name:string,options:list<array{id:int,name:string,price:?float}>}>
     */
    public function forMerchant(int $businessId): array
    {
        $groupIds = $this->groupIds($businessId);
        if ($groupIds === []) {
            return [];
        }

        $prices = DB::table('business_addon_prices')->where('business_id', $businessId)->pluck('price', 'option_id');
        $groups = OptionGroup::query()->whereIn('id', $groupIds)->get()->keyBy('id');
        $options = \App\Models\Option::query()->whereIn('group_id', $groupIds)->orderBy('sort_order')->orderBy('id')->get()->groupBy('group_id');

        $out = [];
        foreach ($groupIds as $groupId) {
            if (! isset($groups[$groupId])) {
                continue;
            }
            $out[] = [
                'group_id' => $groupId,
                'group_name' => $groups[$groupId]->displayName(),
                'options' => collect($options[$groupId] ?? [])->map(fn ($o) => [
                    'id' => (int) $o->id,
                    'name' => $o->displayName(),
                    'price' => isset($prices[$o->id]) && (float) $prices[$o->id] > 0 ? (float) $prices[$o->id] : null,
                ])->values()->all(),
            ];
        }

        return $out;
    }

    /**
     * Set the shop's prices — `option id => price` (empty / 0 = not offered) — and bring every item of
     * the shop up to date. Options of groups the trade does not offer as services are ignored.
     *
     * @param  array<int|string,mixed>  $prices
     */
    public function save(int $businessId, array $prices): array
    {
        $allowed = DB::table('options')->whereIn('group_id', $this->groupIds($businessId))->pluck('id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($businessId, $prices, $allowed) {
            foreach ($prices as $optionId => $price) {
                $optionId = (int) $optionId;
                if (! in_array($optionId, $allowed, true)) {
                    continue;
                }
                $price = round((float) $price, 2);
                if ($price > 0) {
                    DB::table('business_addon_prices')->updateOrInsert(['business_id' => $businessId, 'option_id' => $optionId], ['price' => $price, 'updated_at' => now(), 'created_at' => now()]);
                } else {
                    DB::table('business_addon_prices')->where('business_id', $businessId)->where('option_id', $optionId)->delete();
                }
            }
        });

        $this->syncBusiness($businessId);

        return $this->forMerchant($businessId);
    }

    public function syncBusiness(int $businessId): void
    {
        MenuItem::query()->where('business_id', $businessId)->get(['id', 'business_id'])->each(fn (MenuItem $item) => $this->syncItem($item));
    }

    /**
     * Give ONE item the shop's services as a single-choice extra group. Creates what is missing, follows
     * the shop's prices, retires a service the shop stopped pricing — and never re-activates a group the
     * merchant switched off on this item.
     */
    public function syncItem(MenuItem $item): void
    {
        $prices = DB::table('business_addon_prices')->where('business_id', $item->business_id)->where('price', '>', 0)->pluck('price', 'option_id');

        foreach ($this->groupIds((int) $item->business_id) as $groupId) {
            $optionIds = DB::table('options')->where('group_id', $groupId)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $priced = collect($optionIds)->filter(fn ($id) => isset($prices[$id]))->values();

            $group = MenuItemExtraGroup::query()->where('menu_item_id', $item->id)->where('source_group_id', $groupId)->first();

            if (! $group) {
                if ($priced->isEmpty()) {
                    continue;
                }
                $source = OptionGroup::query()->find($groupId);
                $group = MenuItemExtraGroup::query()->create([
                    'menu_item_id' => $item->id, 'name_ar' => (string) ($source->name_ar ?? ''), 'name_en' => $source->name_en,
                    'selection_type' => MenuItemExtraGroup::SELECTION_SINGLE, 'reorder' => 0, 'is_active' => true,
                ]);
                $group->forceFill(['source_group_id' => $groupId])->save();
            }

            foreach ($optionIds as $optionId) {
                $extra = MenuItemExtra::query()->where('menu_item_id', $item->id)->where('source_option_id', $optionId)->first();
                $price = $prices[$optionId] ?? null;

                if ($price === null) {
                    $extra?->update(['is_active' => false]);

                    continue;
                }

                $option = \App\Models\Option::query()->find($optionId);
                $values = [
                    'extra_group_id' => $group->id, 'name_ar' => (string) $option->name_ar, 'name_en' => $option->name_en,
                    'price' => round((float) $price, 2), 'max_qty' => 1, 'is_active' => true,
                ];
                if ($extra) {
                    $extra->update($values);
                } else {
                    $extra = MenuItemExtra::query()->create($values + ['menu_item_id' => $item->id]);
                    $extra->forceFill(['source_option_id' => $optionId])->save();
                }
            }
        }
    }
}
