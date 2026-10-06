<?php

namespace App\Services\Menu;

use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\PlatformService;
use App\Models\ServiceOptionGroupPlacement as Placement;
use App\Models\User;
use App\Services\Catalog\ServiceOptionPlacements;
use Illuminate\Support\Facades\DB;

/**
 * «شروط المتجر» — المالك، 2026-10-04: returns, minimum order, delivery and trade scope are POLICIES OF
 * THE STORE, not fields of an item. Which groups a trade asks for is the placement usage `store_terms`
 * («مكونات الخدمة»); what THIS store answered is its own ticks (`option_user`), set once in its
 * profile, shown on its page and at checkout, and frozen on the order when it is placed.
 */
final class StoreTerms
{
    public function __construct(private readonly ServiceOptionPlacements $placements)
    {
    }

    /** @return list<int> the groups this store's trade asks it to answer, in order */
    public function groupIds(int $businessId): array
    {
        $child = (int) User::query()->whereKey($businessId)->value('category_child_id');
        $menu = (int) PlatformService::query()->where('key', PlatformService::KEY_MENU)->value('id');

        if ($child <= 0 || $menu <= 0) {
            return [];
        }

        return $this->placements->for($menu, $child, Placement::USAGE_STORE_TERMS)
            ->pluck('option_group_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Every term of the store with all its options, each flagged `selected` — what the merchant edits.
     *
     * @return list<array{group_id:int,group_name:string,options:list<array{id:int,name:string,selected:bool}>}>
     */
    public function forMerchant(int $businessId): array
    {
        return $this->build($businessId, false);
    }

    /**
     * Only what the store answered — what a customer reads, and what an order freezes.
     *
     * @return list<array{group_id:int,group_name:string,options:list<array{id:int,name:string}>}>
     */
    public function forCustomer(int $businessId): array
    {
        return $this->build($businessId, true);
    }

    /** Does this store's trade ask it to answer this group? */
    public function asks(int $businessId, int $groupId): bool
    {
        return in_array($groupId, $this->groupIds($businessId), true);
    }

    /** @return list<int> every option of every group the store answers (what the profile may tick) */
    public function optionIds(int $businessId): array
    {
        $groups = $this->groupIds($businessId);

        return $groups === [] ? [] : DB::table('options')->whereIn('group_id', $groups)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function build(int $businessId, bool $onlySelected): array
    {
        $groupIds = $this->groupIds($businessId);
        if ($groupIds === []) {
            return [];
        }

        $ticked = DB::table('option_user')->where('user_id', $businessId)->pluck('option_id')->map(fn ($id) => (int) $id)->all();
        $groups = OptionGroup::query()->whereIn('id', $groupIds)->get()->keyBy('id');
        $options = Option::query()->whereIn('group_id', $groupIds)->orderBy('sort_order')->orderBy('id')->get()->groupBy('group_id');

        $out = [];
        foreach ($groupIds as $groupId) {
            $group = $groups[$groupId] ?? null;
            if (! $group) {
                continue;
            }

            $rows = [];
            foreach ($options[$groupId] ?? [] as $option) {
                $selected = in_array((int) $option->id, $ticked, true);
                if ($onlySelected && ! $selected) {
                    continue;
                }
                $rows[] = ['id' => (int) $option->id, 'name' => $option->displayName()] + ($onlySelected ? [] : ['selected' => $selected]);
            }

            if ($onlySelected && $rows === []) {
                continue;
            }
            $out[] = ['group_id' => (int) $groupId, 'group_name' => $group->displayName(), 'options' => $rows];
        }

        return $out;
    }
}
