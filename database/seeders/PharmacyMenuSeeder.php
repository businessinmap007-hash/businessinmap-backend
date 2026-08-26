<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «الصيدلية لها قائمة بكل الادوية والاسعار اسمها قاموس الادوية قم بعمل المنيو
 *  الخاص بها» — المالك، 2026-08-26.
 *
 *     php artisan db:seed --class=PharmacyMenuSeeder
 *
 * `menu` stood configured for «صيدلية» #215 but switched OFF — a
 * `child_workbench` default from 2026-08-11 nobody had reviewed, wrongly typed
 * `menu_food` on top. A pharmacy shelves priced drugs at a fixed price the way
 * any ready-goods shop does; it does not plate a dish. `menu_market` is the
 * item type the platform already uses for exactly that
 * ({@see \App\Support\MarketCatalogChildren}), and turning it on is what makes
 * «قاموس الأدوية» ({@see \App\Http\Controllers\Business\MenuPharmacyCatalogController})
 * reachable at all — the nav and the controller both gate on the menu service
 * being active.
 *
 * Idempotent — `ChildServiceWriter::enable()` upserts both rows.
 */
class PharmacyMenuSeeder extends Seeder
{
    private const CHILD_ID = 215;

    public function run(): void
    {
        $rootId = (int) DB::table('category_parent_child')->where('child_id', self::CHILD_ID)->value('parent_id');

        if ($rootId <= 0) {
            $this->command?->warn('  ! «صيدلية» #' . self::CHILD_ID . ' stands under no root.');

            return;
        }

        $serviceId = (int) DB::table('platform_services')->where('key', 'menu')->value('id');

        if ($serviceId <= 0) {
            $this->command?->warn('  ! خدمة «menu» غير موجودة.');

            return;
        }

        app(ChildServiceWriter::class)->enable($rootId, self::CHILD_ID, $serviceId, [
            'has_variants' => false,
            'has_addons' => false,
            'supports_notes' => false,
            'supports_stock' => true,
            'allowed_item_types' => ['menu_market'],
        ], null, null, 'pharmacy-menu');

        $this->command?->info('Pharmacy menu: enabled (menu_market) for child #' . self::CHILD_ID);
    }
}
