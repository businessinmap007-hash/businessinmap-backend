<?php

use App\Services\Catalog\MenuShapeCurator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «راجع كل المنيوهات … كل منيو سيظهر حسب مجموعة الخيارات» — المالك، 2026-10-04, after the fish
 * menu. The three biggest retail trades that were still a name-price-quantity form carrying a
 * factory's «نظام التصنيع»:
 *
 *   #73  أدوات تجميل           — a kind with the pack size; describes by skin type and audience
 *   #115 مفروشات               — a kind with the piece count; the FABRIC and the bed size describe
 *                                the item (as the wood describes a bedroom), they are not shelves
 *   #59/#60/#168 ملابس / جلود  — a kind with size and colour; audience, fabric and season describe it
 *
 * Each is scoped to its own trades only (a supermarket carrying a cosmetics aisle is untouched).
 * The kinds are written once; an admin who retunes them in «أشكال المنيو» is never overruled.
 */
return new class extends Migration
{
    public function up(): void
    {
        $curator = new MenuShapeCurator;
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');
        if ($menu <= 0) {
            return;
        }

        $audience = (int) DB::table('option_groups')->where('name_ar', 'الجمهور المستهدف')->value('id');   // حريمي · أطفال · رجالي
        $fabrics = (int) DB::table('option_groups')->where('name_ar', 'أنواع الأقمشة')->value('id');
        $none = MenuShapeCurator::MANUFACTURING;

        // ── مستحضرات التجميل ───────────────────────────────────────────────
        $beauty = 401; // أصناف مستحضرات التجميل
        $curator->kind('beauty', 'مستحضرات تجميل', 'Beauty products', [['volume', 10, true, true, true, true], ['description', 900, false, false, false, true]], [$beauty]);
        $skin = $curator->group('نوع البشرة', 'Skin type', [['كل أنواع البشرة', 'All skin types'], ['بشرة دهنية', 'Oily skin'], ['بشرة جافة', 'Dry skin'], ['بشرة مختلطة', 'Combination skin'], ['بشرة حساسة', 'Sensitive skin']], [73]);
        $curator->describe($skin, [73], 10, 'chips', true);
        if ($audience) {
            $curator->describe($audience, [73], 20, 'chips', false);
        }
        $curator->withdraw($none, [73], 'price_variant');

        // ── المفروشات ──────────────────────────────────────────────────────
        $curator->kind('linens', 'مفروشات', 'Linens', [['package_count', 10, true, true, true, true], ['description', 900, false, false, false, true]], [503]);
        $bed = $curator->group('مقاس المفرش', 'Linen size', [['سرير فردي', 'Single bed'], ['سرير مزدوج', 'Double bed'], ['سرير كوين', 'Queen bed'], ['سرير كينج', 'King bed'], ['حسب الطلب', 'Made to measure']], [115]);
        $curator->describe($bed, [115], 10, 'chips', false);
        if ($fabrics) {
            // The fabric describes a sheet; it is not a shelf of the shop (a fabric SHOP, #95, keeps it as its line).
            $curator->describe($fabrics, [115], 20, 'dropdown', true);
            $curator->withdraw($fabrics, [115], 'section');
        }

        // ── الملابس والجلود ────────────────────────────────────────────────
        $fashion = 10; // موضة وعناية شخصية
        $curator->kind('fashion', 'ملابس وأحذية', 'Fashion', [['size', 10, true, true, true, true], ['color', 20, true, true, true, true], ['description', 900, false, false, false, true]], [$fashion]);
        $season = $curator->group('الموسم', 'Season', [['صيفي', 'Summer wear'], ['شتوي', 'Winter wear'], ['ربيعي وخريفي', 'Spring and autumn wear'], ['لكل المواسم', 'All seasons']], [59, 60, 168]);
        if ($audience) {
            // «حريمي / رجالي» is what the garment IS FOR — not a second price of the same garment.
            $curator->withdraw($audience, [60, 168], 'price_variant');
            $curator->describe($audience, [59, 60, 168], 10, 'chips', false);
        }
        $curator->describe($season, [59, 60, 168], 20, 'chips', false);
        if ($fabrics) {
            $curator->describe($fabrics, [59, 60], 30, 'dropdown', true);
            $curator->withdraw($fabrics, [59, 60], 'section');
        }
        $curator->withdraw($none, [60, 168], 'price_variant');
    }

    public function down(): void
    {
        // Curated data; the admin owns it from here.
    }
};
