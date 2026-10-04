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

    /**
     * Replace the store's answers for the groups it was sent; a group left out is untouched.
     *
     * @param  array<int|string,list<int>>  $selection  group id => option ids
     * @return list<array{group_id:int,group_name:string,options:list<array{id:int,name:string,selected:bool}>}>
     */
    public function save(int $businessId, array $selection): array
    {
        $allowed = $this->groupIds($businessId);

        DB::transaction(function () use ($businessId, $selection, $allowed) {
            foreach ($selection as $groupId => $optionIds) {
                $groupId = (int) $groupId;
                if (! in_array($groupId, $allowed, true)) {
                    continue;
                }
                $valid = DB::table('options')->where('group_id', $groupId)->pluck('id')->map(fn ($id) => (int) $id)->all();
                $chosen = array_values(array_intersect($valid, array_map('intval', (array) $optionIds)));

                DB::table('option_user')->where('user_id', $businessId)->whereIn('option_id', $valid)->delete();
                foreach ($chosen as $optionId) {
                    DB::table('option_user')->insert(['user_id' => $businessId, 'option_id' => $optionId]);
                }
            }
        });

        return $this->forMerchant($businessId);
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
