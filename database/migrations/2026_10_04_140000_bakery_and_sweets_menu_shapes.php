<?php

use App\Services\Catalog\MenuShapeCurator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «كمّل على المخبوزات والحلويات» — المالك، 2026-10-04. The bakery (#27) and the sweet shop (#210)
 * take the shape the fresh counters have:
 *
 *   • مخبوزات — a kind with the piece count; describes by the flour and by how fresh it is
 *   • حلويات وجاتوه — a kind with weight and piece count; describes by diet and by occasion;
 *                       priced by SALE UNIT («بالكيلو»، «بالعبوة»…) — the group «وحدة البيع» the
 *                       butcher already uses, borrowed rather than cloned
 *
 * Kinds travel with their group; describing groups and the price axis stay with the two dedicated
 * trades, so a supermarket's bread aisle is not asked about its flour.
 */
return new class extends Migration
{
    private const SALE_UNIT = 475;

    public function up(): void
    {
        $c = new MenuShapeCurator;
        if (! DB::table('platform_services')->where('key', 'menu')->exists()) {
            return;
        }
        $description = ['description', 900, false, false, false, true];

        // ── مخبوزات ────────────────────────────────────────────────────────
        $c->kind('bakery', 'مخبوزات', 'Bakery', [['package_count', 10, true, true, true, true], $description], [834]);
        $flour = $c->group('نوع الدقيق', 'Flour type', [['دقيق أبيض', 'White flour'], ['قمح كامل', 'Whole wheat'], ['نخالة وشوفان', 'Bran and oats'], ['خالي من الجلوتين', 'Gluten free']], [27]);
        $fresh = $c->group('موعد الخبز', 'Baked', [['خبز اليوم', 'Baked today'], ['مجمد', 'Frozen bakes']], [27]);
        $c->describe($flour, [27], 10, 'chips', true);
        $c->describe($fresh, [27], 20, 'chips', false);

        // ── حلويات وجاتوه ──────────────────────────────────────────────────
        $c->kind('sweets', 'حلويات وجاتوه', 'Sweets and cakes', [['weight', 10, true, true, true, true], ['package_count', 20, true, true, true, true], $description], [597]);
        $diet = $c->group('خيارات غذائية', 'Dietary options', [['دايت', 'Diet'], ['خالى من السكر', 'Sugar free'], ['خالى من الجلوتين', 'Gluten free sweets'], ['نباتى', 'Vegan']], [27, 210]);
        $occasion = $c->group('المناسبة', 'Occasion', [['أفراح', 'Weddings'], ['أعياد ومواسم', 'Feasts and seasons'], ['أعياد ميلاد', 'Birthdays'], ['استخدام يومى', 'Everyday']], [27, 210]);
        $c->describe($diet, [27, 210], 10, 'chips', true);
        $c->describe($occasion, [27, 210], 20, 'chips', true);
        $c->priceAxis(self::SALE_UNIT, [27, 210], 0);
    }

    public function down(): void
    {
    }
};
