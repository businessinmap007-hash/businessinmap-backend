<?php

use App\Services\Catalog\MenuShapeCurator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «كمّل على اللحوم والدواجن والألبان» — المالك، 2026-10-04, after the fish menu. The same shape for
 * the other fresh counters:
 *
 *   • لحوم (#553 جزارة) — describes by condition, prices by preparation (بالعظم، مقطّع، مفروم…)
 *   • دواجن (#229)      — its price axis «حالة الدواجن» already existed; it gains a kind and says how
 *                          the bird was raised
 *   • ألبان              — a kind with fat percentage and weight. There is NO dairy shop on the
 *                          platform (only markets, a food factory and a freezer shop carry the list),
 *                          so nothing here is scoped to a trade that is not a dairy: the fields travel
 *                          with the group, the describing groups stay out.
 *
 * Kinds attach to the group (so a market's meat counter gets the fields too); describing groups and
 * price axes are scoped to the dedicated trade — a supermarket's rice is never asked about its cut.
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
        $c->attributes([['fat_percent', 'نسبة الدسم (%)', 'Fat (%)', 'number']]);

        // ── لحوم ───────────────────────────────────────────────────────────
        $c->kind('meat', 'لحوم', 'Meat', [['weight', 10, true, true, true, true], $description], [830]);
        $condition = $c->group('حالة اللحم', 'Meat condition', [['لحم طازج', 'Fresh meat'], ['لحم مبرد', 'Chilled meat'], ['لحم مجمد', 'Frozen meat']], [553]);
        $c->describe($condition, [553], 10, 'chips', false);
        $cut = $c->group('تجهيز اللحم', 'Meat preparation', [['بالعظم', 'Bone-in'], ['مقطّع', 'Cut pieces'], ['مفروم', 'Minced meat'], ['شرائح وستيك', 'Slices and steaks'], ['منزوع العظم', 'Boneless']]);
        $c->priceAxis($cut, [553], 0);
        DB::table('service_option_group_placements')->where('child_id', 553)->where('option_group_id', 475)->where('usage', 'price_variant')->update(['sort_order' => 10]);

        // ── دواجن ──────────────────────────────────────────────────────────
        $c->kind('poultry', 'دواجن وبيض', 'Poultry and eggs', [['weight', 10, true, true, true, true], $description], [576]);
        $raised = $c->group('طريقة التربية', 'How it was raised', [['تربية مزارع', 'Farm raised'], ['تربية بلدي حر', 'Free-range'], ['عضوي', 'Organic']], [229]);
        $c->describe($raised, [229], 10, 'chips', false);

        // ── ألبان ──────────────────────────────────────────────────────────
        $c->kind('dairy', 'ألبان وأجبان', 'Dairy and cheese', [['weight', 10, true, true, true, true], ['fat_percent', 20, false, true, true, true], $description], [831]);
    }

    public function down(): void
    {
    }
};
