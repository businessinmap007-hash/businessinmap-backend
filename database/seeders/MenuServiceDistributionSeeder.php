<?php

namespace Database\Seeders;

use App\Models\OptionGroup;
use App\Models\PlatformService;
use App\Models\ServiceOptionGroupPlacement as Placement;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «قم بتوزيع الخدمة بناء على ما يناسب كل مجموعة خيارات» — المالك،
 * 2026-09-28. Scoped to (root, child) pairs that already have **retail**
 * active — retail.allowed_item_types is the platform's own policed proof
 * that a child sells a real, identifiable physical good (the
 * product_category_children mirror — see [[three-catalog-shapes]] and
 * "never collapse retail"). A first, broader cut of this seeder ("any
 * active service") wrongly turned menu on for a pure-services business
 * («تسويق», #177 — booking + business_offers, no retail) and was reverted;
 * this is the corrected, narrower version.
 *
 * Each such pair gets «المنيو» turned back on (most already had a dormant
 * config from before the owner's own deliberate menu shutoff — this
 * reactivates EXACTLY that config, never touching `allowed_item_types`)
 * and every option group the child carries gets a starting placement under
 * menu, matching «مكونات الخدمة»'s own defaults
 * (`ServiceComponentsController::ROLE_DEFAULTS`).
 *
 * A pair keeps whatever `allowed_item_types` it already carries (most had a
 * dormant one from before the owner's own deliberate menu shutoff); one
 * with none at all defaults to `menu_market` — a retail-active child, by
 * definition, already sells a real, identifiable physical good, and
 * `menu_market` is exactly the kind that means "a ready-made good browsed
 * by shelf" (see {@see \App\Support\MarketCatalogChildren}). This default
 * was first written unconditionally by an earlier, broader cut of this
 * seeder and was mistaken, live, for a bug: it turns
 * `MenuItem::heading()`'s branch from `option_combo` to `catalog_group` for
 * every item on that child, which broke MenuHeadingTest/MenuOptionHeadingTest
 * — but only because those tests pick "the first business in the table" and
 * assumed it could never legitimately be a goods catalog. It legitimately
 * can now; the tests were the stale part and were narrowed to skip a
 * goods-catalog business instead of every business in the table changing
 * behaviour.
 *
 * Purely additive otherwise:
 *  - `ChildServiceWriter::enable()` merges the config; passing no
 *    `allowed_item_types` key never touches whatever kind a child already
 *    had, and this seeder only ever names that key when the pair had
 *    nothing to preserve.
 *  - A menu placement is written ONLY when none exists yet for that
 *    (child, group) — an admin's later hand curation on «مكونات الخدمة»
 *    is never overwritten by a re-run. See [[seeder-must-withdraw]].
 *
 * «فى مكونات الخدمة محلات او اونلاين قسم اجهزة بلايستيشن تم ضبط المكونات
 * عمم نفس الضبط على كل الابناء» — المالك، 2026-09-28. `ROLE_DEFAULTS` alone
 * is too blunt: a `descriptive`-role group is not always just informational.
 * «التسليم والاستلام» (group 49) and «الخدمات المالية بالمحل» (group 700)
 * had ALREADY been hand-curated to `store_cart`/`store_filter` under retail
 * and business_offers — this seeder's first run missed that and wrote plain
 * `descriptive` for all 83 of their menu placements instead (fixed live,
 * same session). `usageOverrides()` below reads that established curation
 * dynamically instead of hardcoding the two group ids, so a future admin
 * edit on any OTHER group is picked up by a re-run too, matching the
 * PlayStation-child example this instruction pointed at.
 *
 * Idempotent; safe to re-run after any admin edit.
 */
class MenuServiceDistributionSeeder extends Seeder
{
    private const ROLE_DEFAULTS = [
        OptionGroup::ROLE_LINE => Placement::USAGE_SECTION,
        OptionGroup::ROLE_MODIFIER => Placement::USAGE_PRICE_VARIANT,
        OptionGroup::ROLE_DESCRIPTIVE => Placement::USAGE_DESCRIPTIVE,
    ];

    public function run(): void
    {
        $menuServiceId = (int) PlatformService::query()->where('key', 'menu')->value('id');
        $retailServiceId = (int) PlatformService::query()->where('key', 'retail')->value('id');
        if ($menuServiceId <= 0 || $retailServiceId <= 0) {
            return;
        }

        $writer = app(ChildServiceWriter::class);
        $usageOverrides = $this->usageOverrides($menuServiceId);

        $pairs = DB::table('category_platform_services')
            ->where('is_active', 1)
            ->where('platform_service_id', $retailServiceId)
            ->select('category_id', 'child_id')
            ->distinct()
            ->get();

        $grantedMenu = 0;
        $placementsWritten = 0;

        foreach ($pairs as $pair) {
            $rootId = (int) $pair->category_id;
            $childId = (int) $pair->child_id;

            $hasMenu = DB::table('category_platform_services')
                ->where('category_id', $rootId)->where('child_id', $childId)
                ->where('platform_service_id', $menuServiceId)->where('is_active', 1)
                ->exists();

            if (! $hasMenu) {
                // Preserve whatever kind is already sitting there (dormant
                // since before the owner's own menu shutoff); only a pair
                // with NOTHING at all gets a default, and 'menu_market' is
                // the correct one — retail being active on this child is
                // the platform's own proof it sells a real ready-made good.
                $existingKind = DB::table('category_service_configs')
                    ->where('category_id', $rootId)->where('child_id', $childId)
                    ->where('platform_service_id', $menuServiceId)
                    ->value('config');
                $existingKind = $existingKind ? (json_decode($existingKind, true)['allowed_item_types'] ?? []) : [];

                $patch = [
                    'config_source' => 'menu_option_group_distribution',
                    'config_updated_at' => now()->toDateTimeString(),
                ];
                if (empty($existingKind)) {
                    $patch['allowed_item_types'] = ['menu_market'];
                }

                $writer->enable($rootId, $childId, $menuServiceId, $patch);
                $grantedMenu++;
            }

            $groups = DB::table('category_child_option as cco')
                ->join('options as o', 'o.id', '=', 'cco.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('cco.child_id', $childId)
                ->whereIn('cco.category_id', [0, $rootId])
                ->where('g.is_active', 1)
                ->distinct()
                ->get(['g.id', 'g.price_role']);

            foreach ($groups as $group) {
                $exists = Placement::query()
                    ->where('platform_service_id', $menuServiceId)
                    ->where('child_id', $childId)
                    ->where('option_group_id', $group->id)
                    ->where('item_type_key', '')
                    ->exists();

                if ($exists) {
                    continue;
                }

                Placement::create([
                    'platform_service_id' => $menuServiceId,
                    'option_group_id' => $group->id,
                    'child_id' => $childId,
                    'item_type_key' => '',
                    'usage' => $usageOverrides[(int) $group->id]
                        ?? self::ROLE_DEFAULTS[$group->price_role]
                        ?? Placement::USAGE_DESCRIPTIVE,
                    'is_active' => true,
                    'sort_order' => 0,
                ]);
                $placementsWritten++;
            }
        }

        $fixed = $this->fixMisplacedUsages($menuServiceId, $usageOverrides);

        $this->command?->info("Menu granted to {$grantedMenu} retail-active (root,child) pairs; {$placementsWritten} option-group placements written; {$fixed} pre-existing placements corrected to their curated usage.");
    }

    /**
     * Every option group whose usage is consistently curated to something
     * OTHER than its own ROLE_DEFAULTS somewhere else on the platform
     * (retail, business_offers…) — the exact «تم ضبط المكونات» signal the
     * owner pointed at on the PlayStation child. A group with no such
     * consistent, non-default curation elsewhere is left out, and falls
     * through to ROLE_DEFAULTS as before.
     *
     * @return array<int,string> option_group_id => usage
     */
    private function usageOverrides(int $menuServiceId): array
    {
        $roleByGroup = OptionGroup::query()->pluck('price_role', 'id');

        return DB::table('service_option_group_placements')
            ->where('platform_service_id', '!=', $menuServiceId)
            ->where('is_active', 1)
            ->select('option_group_id', 'usage')
            ->distinct()
            ->get()
            ->groupBy('option_group_id')
            ->filter(fn ($rows) => $rows->pluck('usage')->unique()->count() === 1)
            ->map(fn ($rows) => $rows->first()->usage)
            ->filter(function ($usage, $groupId) use ($roleByGroup) {
                $role = $roleByGroup[$groupId] ?? null;
                return $role !== null && $usage !== (self::ROLE_DEFAULTS[$role] ?? null);
            })
            ->all();
    }

    /**
     * A placement THIS seeder already wrote (menu, usage still at the plain
     * ROLE_DEFAULTS value) before an override above existed for its group is
     * corrected in place — this is the seeder fixing its own earlier miss,
     * never an admin's hand curation, so it stays safe to run unattended.
     */
    private function fixMisplacedUsages(int $menuServiceId, array $usageOverrides): int
    {
        $fixed = 0;

        foreach ($usageOverrides as $groupId => $correctUsage) {
            $fixed += Placement::query()
                ->where('platform_service_id', $menuServiceId)
                ->where('option_group_id', $groupId)
                ->where('usage', '!=', $correctUsage)
                ->update(['usage' => $correctUsage]);
        }

        return $fixed;
    }
}
