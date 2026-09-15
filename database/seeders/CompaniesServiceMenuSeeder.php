<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

/**
 * Eight service-only children under «شركات» (Companies, root 22) also
 * catalogue their own offerings - an ad agency names a campaign package, a
 * software house names a language or a machine-control job - the same
 * section-names-it/items-are-its-branches shape as a restaurant's menu, via
 * the new `menu_services` kind (MenuServicesKindSeeder must run first).
 *
 * «تسويق» #177 (Marketing) is deliberately excluded: BusinessCapabilityScopeTest
 * and BusinessPanelNavTest hold a dated, owner-documented rule (2026-08-19)
 * that a marketing agency is never offered Menu - it was tried once
 * (2026-09-15) and reverted the same day once that conflict surfaced.
 */
class CompaniesServiceMenuSeeder extends Seeder
{
    private const ROOT_ID = 22; // شركات

    private const ITEM_GROUPS = [85];

    private const CHILDREN = [
        11,  // دعاية وإعلان وإدارة صفحات
        70,  // تنسيق حفلات
        72,  // مقاولات
        153, // شركات تأمين
        187, // صرافة وتحويل أموال
        253, // أمن
        261, // برمجيات
        279, // سياحة
    ];

    public function run(): void
    {
        $menuId = (int) DB::table('platform_services')->where('key', 'menu')->value('id');

        if ($menuId <= 0) {
            $this->command?->warn('menu service not found.');

            return;
        }

        $writer = app(ChildServiceWriter::class);

        foreach (self::CHILDREN as $childId) {
            $writer->enable(
                rootId: self::ROOT_ID,
                childId: $childId,
                serviceId: $menuId,
                configPatch: ['allowed_item_types' => ['menu_services'], 'item_groups' => self::ITEM_GROUPS],
                source: 'companies_service_menu_2026_09_15'
            );
        }

        $this->command?->info('Companies service menu: ' . count(self::CHILDREN) . ' children enabled for menu_services.');
    }
}
