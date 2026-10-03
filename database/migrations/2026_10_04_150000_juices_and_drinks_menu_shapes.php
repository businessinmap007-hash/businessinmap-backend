<?php

use App\Services\Catalog\MenuShapeCurator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «كمّل على العصائر والمشروبات» — المالك، 2026-10-04.
 *
 *   • عصائر (#158)       — priced by CUP SIZE (a juice is one drink in three prices), described by sugar
 *                          and by how it is served; it makes nothing to order, so «نظام التصنيع» goes
 *   • مشروبات معبأة       — a kind (volume, pack count); only markets and factories carry the list, so
 *                          nothing is scoped to a trade
 *   • شاي وقهوة (#63 بن)  — a kind (weight) and the bean's origin; its roast/grind axis already existed
 */
return new class extends Migration
{
    public function up(): void
    {
        $c = new MenuShapeCurator;
        if (! DB::table('platform_services')->where('key', 'menu')->exists()) {
            return;
        }
        $description = ['description', 900, false, false, false, true];

        // ── عصائر ──────────────────────────────────────────────────────────
        $c->kind('juices', 'عصائر ومشروبات طازجة', 'Fresh juices and drinks', [['volume', 10, true, true, true, true], $description], [596]);
        $cup = $c->group('حجم الكوب', 'Cup size', [['كوب صغير', 'Small cup'], ['كوب وسط', 'Medium cup'], ['كوب كبير', 'Large cup'], ['لتر', 'One litre']]);
        $c->priceAxis($cup, [158], 0);
        $sugar = $c->group('درجة السكر', 'Sugar level', [['بدون سكر', 'No sugar'], ['سكر خفيف', 'Light sugar'], ['سكر وسط', 'Medium sugar'], ['سكر زيادة', 'Extra sugar']], [158]);
        $serve = $c->group('طريقة التقديم', 'Serving', [['بارد', 'Chilled'], ['مع ثلج', 'With ice'], ['بدون ثلج', 'No ice']], [158]);
        $c->describe($sugar, [158], 10, 'chips', false);
        $c->describe($serve, [158], 20, 'chips', false);
        $c->unlink(MenuShapeCurator::MANUFACTURING, [158]);

        // ── مشروبات معبأة ──────────────────────────────────────────────────
        $c->kind('bottled_drinks', 'مشروبات معبأة', 'Bottled drinks', [['volume', 10, true, true, true, true], ['package_count', 20, true, true, true, true], $description], [851]);

        // ── شاي وقهوة ──────────────────────────────────────────────────────
        $c->kind('coffee_tea', 'شاي وقهوة', 'Tea and coffee', [['weight', 10, true, true, true, true], $description], [847]);
        $origin = $c->group('أصل البن', 'Bean origin', [['برازيلي', 'Brazilian beans'], ['حبشي', 'Ethiopian beans'], ['يمني', 'Yemeni beans'], ['كولومبي', 'Colombian beans'], ['هندي', 'Indian beans']], [63]);
        $c->describe($origin, [63], 10, 'chips', false);
    }

    public function down(): void
    {
    }
};
