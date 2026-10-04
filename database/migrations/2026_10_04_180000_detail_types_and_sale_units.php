<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase two of re-ordering the option groups — المالك، 2026-10-04: instead of ~160 menu shapes, a
 * handful of DETAIL TYPES; every line group carries one, and the type decides the screen's shape, the
 * units a product is sold in, and whether payment plans («كاش / أقساط») exist at all.
 *
 *   • «وحدة البيع» listed every row of `catalog_units` — including حصان، بوصة، شعلة، جيجا، وات (units of a
 *     SPECIFICATION, not of a sale). Only units flagged `is_sale_unit` are ever offered now, and each
 *     type narrows them further: fruit is sold by the kilo, the gram, the bunch — never by the horse-power.
 *   • Payment plans are for big-ticket goods only (phones and computers, cars, furniture) — never food.
 *
 * The 25-odd kinds written earlier stay as per-group FIELD SETS (what a stone or a fish adds on top of
 * its type); they are no longer what a group "is".
 */
return new class extends Migration
{
    /** code => [name_ar, name_en, units (null = every sale unit), payment plans, sort] */
    private const TYPES = [
        'basic' => ['أساسي', 'Basic', null, false, 10],
        'fresh_produce' => ['طازج بالوزن أو الرابطة', 'Fresh produce', ['kg', 'g', 'bunch'], false, 20],
        'weighed' => ['بالوزن والحجم', 'By weight or volume', ['kg', 'g', 'l', 'ml', 'pcs', 'pack', 'box', 'bag'], false, 30],
        'pharmacy' => ['صيدلية', 'Pharmacy', ['pack', 'strip', 'pcs'], false, 40],
        'piece_goods' => ['بالقطعة والعبوة', 'By piece or pack', ['pcs', 'pack', 'box', 'set', 'dozen'], false, 50],
        'measured' => ['بالمقاس والكمية', 'By measure', ['m', 'm2', 'm3', 'pcs', 'kg', 'ton', 'bag', 'thousand'], false, 60],
        'tech' => ['مواصفات فنية', 'Technical specs', ['pcs', 'set'], true, 70],
        'vehicles' => ['مركبات', 'Vehicles', ['pcs'], true, 80],
        'furniture' => ['أثاث', 'Furniture', ['pcs', 'set'], true, 90],
        'meal' => ['وجبات (منيو المطاعم)', 'Meals', ['pcs', 'pack', 'box'], false, 100],
    ];

    /** type => group ids (menu line groups) */
    private const GROUPS = [
        'fresh_produce' => [825, 824, 993],
        'weighed' => [830, 576, 829, 831, 834, 597, 849, 596, 851, 847, 577, 842, 840, 841, 845, 846, 843, 844, 839, 848, 850, 860, 854, 858, 581, 528, 516, 419],
        'pharmacy' => [92, 411],
        'piece_goods' => [453, 855, 450, 417, 401, 449, 447, 448, 319, 1037, 434, 413, 404, 412, 430, 456, 511, 595, 429, 526, 405, 406, 454, 856, 857, 452, 414, 431, 492, 455, 451, 409, 287, 410, 397, 11, 421, 433, 527, 260, 529, 388, 503],
        'measured' => [407, 402, 649, 299, 418, 479, 416, 504, 398, 499, 396, 399, 415],
        'tech' => [592, 400, 1064, 286],
        'vehicles' => [133, 60, 59, 809],
        'furniture' => [3, 408],
        'meal' => [122],
    ];

    public function up(): void
    {
        // ── the units a product is SOLD in ─────────────────────────────────
        if (! Schema::hasColumn('catalog_units', 'is_sale_unit')) {
            Schema::table('catalog_units', function (Blueprint $table) {
                $table->boolean('is_sale_unit')->default(false);
            });
            DB::table('catalog_units')->whereIn('unit_type', ['weight', 'volume', 'count', 'length'])->update(['is_sale_unit' => 1]);
        }
        foreach ([
            ['m', 'متر', 'Metre', 'length'], ['m2', 'متر مربع', 'Square metre', 'length'], ['m3', 'متر مكعب', 'Cubic metre', 'length'],
            ['set', 'طقم', 'Set', 'count'], ['dozen', 'دستة', 'Dozen', 'count'], ['thousand', 'ألف', 'Thousand', 'count'],
        ] as $i => [$code, $ar, $en, $type]) {
            if (! DB::table('catalog_units')->where('code', $code)->exists()) {
                DB::table('catalog_units')->insert([
                    'code' => $code, 'name_ar' => $ar, 'name_en' => $en, 'unit_type' => $type, 'is_active' => 1,
                    'sort_order' => 110 + $i, 'is_sale_unit' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // ── the detail types ───────────────────────────────────────────────
        if (! Schema::hasTable('menu_detail_types')) {
            Schema::create('menu_detail_types', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name_ar', 120);
                $table->string('name_en', 120)->nullable();
                // The sale-unit codes this type offers; null = every unit flagged is_sale_unit.
                $table->json('sale_unit_codes')->nullable();
                // «كاش / أقساط» — big-ticket goods only (phones, computers, cars, furniture), never food.
                $table->boolean('allows_payment_plans')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
        foreach (self::TYPES as $code => [$ar, $en, $units, $plans, $sort]) {
            DB::table('menu_detail_types')->updateOrInsert(['code' => $code], [
                'name_ar' => $ar, 'name_en' => $en, 'sale_unit_codes' => $units === null ? null : json_encode($units),
                'allows_payment_plans' => $plans, 'sort_order' => $sort, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (! Schema::hasColumn('option_groups', 'detail_type')) {
            Schema::table('option_groups', function (Blueprint $table) {
                $table->string('detail_type', 40)->nullable()->index();
            });
        }
        // Classified ONCE — a type an admin sets later is never overruled.
        foreach (self::GROUPS as $type => $ids) {
            DB::table('option_groups')->whereIn('id', $ids)->whereNull('detail_type')->update(['detail_type' => $type]);
        }
    }

    public function down(): void
    {
    }
};
