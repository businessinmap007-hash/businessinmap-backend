<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «المخزون والمشتريات» for every (root, child) that sells goods — a menu or a retail shelf (owner, 2026-10-29: «ابنى نواه
 * تصلح للكل وتظهر الحقول بناء على التصنيفات»). The features are the service's item types; what a trade starts with is
 * the table approved on the canvas:
 *
 *   restaurant (menu_food)      everything; lots/expiry, barcode, several warehouses optional
 *   pharmacy (child 215)        no recipes; lots/expiry and barcode basic
 *   shop / market / retail      no recipes; several warehouses optional
 *   factory / company           no recipes; menu link and lots optional, several warehouses basic
 *
 * `optional_item_types` are allowed but start OFF for a business until it switches them on.
 *
 * Additive and curation-safe: a pair that already has an inventory link — active or switched off by the admin — is
 * never touched again, so an admin's «no» survives a re-seed. Pure service trades (menu_services / menu_properties
 * only) get nothing: they sell work, not stock.
 */
class InventoryServiceDistributionSeeder extends Seeder
{
    private const PHARMACY_CHILD = 215;
    private const FACTORY_ROOTS = [22, 23];

    public const ALL = ['units', 'sales_link', 'recipes', 'lots_expiry', 'barcode', 'multi_warehouse', 'waste', 'purchases', 'pos'];

    public function run(): void
    {
        $inventory = (int) DB::table('platform_services')->where('key', 'inventory')->value('id');
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');
        $retail = (int) DB::table('platform_services')->where('key', 'retail')->value('id');

        if ($inventory <= 0) {
            return;
        }

        $pairs = DB::table('category_platform_services')
            ->whereIn('platform_service_id', [$menu, $retail])
            ->where('is_active', 1)
            ->select('category_id', 'child_id')
            ->distinct()
            ->get();

        $writer = app(ChildServiceWriter::class);

        foreach ($pairs as $pair) {
            $rootId = (int) $pair->category_id;
            $childId = (int) $pair->child_id;

            if ($rootId <= 0 || $childId <= 0) {
                continue;
            }

            $known = DB::table('category_platform_services')
                ->where('category_id', $rootId)->where('child_id', $childId)->where('platform_service_id', $inventory)->exists();

            if ($known) {
                continue;
            }

            $profile = $this->profile($rootId, $childId, $menu, $retail);

            if ($profile === null) {
                continue;
            }

            [$allowed, $optional] = $profile;
            $writer->enable($rootId, $childId, $inventory, [
                'allowed_item_types' => $allowed,
                'optional_item_types' => $optional,
            ], null, null, 'inventory_distribution');
        }
    }

    /** @return array{0:list<string>,1:list<string>}|null */
    private function profile(int $rootId, int $childId, int $menu, int $retail): ?array
    {
        $menuKinds = (array) (json_decode((string) DB::table('category_service_configs')
            ->where('category_id', $rootId)->where('child_id', $childId)->where('platform_service_id', $menu)->where('is_active', 1)
            ->value('config'), true)['allowed_item_types'] ?? []);
        $sellsRetail = DB::table('category_platform_services')
            ->where('category_id', $rootId)->where('child_id', $childId)->where('platform_service_id', $retail)->where('is_active', 1)->exists();

        $without = fn (array $drop) => array_values(array_diff(self::ALL, $drop));

        if ($childId === self::PHARMACY_CHILD) {
            return [$without(['recipes']), ['multi_warehouse']];
        }

        if (in_array($rootId, self::FACTORY_ROOTS, true)) {
            return [$without(['recipes']), ['sales_link', 'lots_expiry', 'pos']];
        }

        if (in_array('menu_food', $menuKinds, true)) {
            return [self::ALL, ['lots_expiry', 'barcode', 'multi_warehouse']];
        }

        $goods = array_diff($menuKinds, ['menu_services', 'menu_properties']);

        if ($sellsRetail || $goods !== [] || $menuKinds === []) {
            return [$without(['recipes']), ['multi_warehouse']];
        }

        return null;
    }
}
