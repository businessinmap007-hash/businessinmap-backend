<?php

use App\Services\Catalog\MenuShapeCurator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Batch two of «كل منيو سيظهر حسب مجموعة الخيارات» — المالك، 2026-10-04: the single-purpose retail
 * trades with a dedicated group — gold, perfume, plants, rugs, medical supplies. Each says what its
 * items are (a kind with fields) and what describes them (groups chosen per item), scoped to its own
 * trade(s) so no other shop that happens to carry the list is asked.
 */
return new class extends Migration
{
    public function up(): void
    {
        $c = new MenuShapeCurator;
        if (! DB::table('platform_services')->where('key', 'menu')->exists()) {
            return;
        }
        $audience = (int) DB::table('option_groups')->where('name_ar', 'الجمهور المستهدف')->value('id');
        $description = ['description', 900, false, false, false, true];

        $c->attributes([['height_cm', 'الارتفاع (سم)', 'Height (cm)', 'number']]);

        // ── ذهب ومجوهرات ───────────────────────────────────────────────────
        $c->kind('jewelry', 'مجوهرات', 'Jewelry', [['weight', 10, true, true, true, true], $description], [447]);
        $karat = $c->group('عيار المشغولات', 'Jewelry karat', [['عيار ٢٤', 'Karat 24'], ['عيار ٢١', 'Karat 21'], ['عيار ١٨', 'Karat 18'], ['عيار ١٤', 'Karat 14'], ['فضة ٩٢٥', 'Sterling silver 925']], [127, 257]);
        $c->describe($karat, [127, 257], 10, 'chips', false);

        // ── عطور ───────────────────────────────────────────────────────────
        $c->kind('perfume', 'عطور', 'Perfume', [['volume', 10, true, true, true, true], $description], [449]);
        $concentration = $c->group('تركيز العطر', 'Fragrance concentration', [['برفيوم', 'Parfum'], ['أو دو برفيوم', 'Eau de parfum'], ['أو دو تواليت', 'Eau de toilette'], ['كولونيا', 'Cologne'], ['زيت عطري', 'Perfume oil']], [213]);
        $c->describe($concentration, [213], 10, 'chips', false);
        if ($audience) {
            $c->describe($audience, [213], 20, 'chips', false);
        }

        // ── نباتات ─────────────────────────────────────────────────────────
        $c->kind('plants', 'نباتات وزهور', 'Plants and flowers', [['height_cm', 10, true, true, true, true], $description], [455]);
        $care = $c->group('مستوى العناية', 'Care level', [['عناية سهلة', 'Easy care'], ['عناية متوسطة', 'Moderate care'], ['تحتاج عناية خاصة', 'Needs special care']], [79]);
        $light = $c->group('الإضاءة المناسبة', 'Suitable light', [['ظل', 'Shade'], ['إضاءة غير مباشرة', 'Indirect light'], ['شمس مباشرة', 'Direct sun']], [79]);
        $c->describe($care, [79], 10, 'chips', false);
        $c->describe($light, [79], 20, 'chips', false);

        // ── سجاد ───────────────────────────────────────────────────────────
        $c->kind('rugs', 'سجاد', 'Rugs', [['size', 10, true, true, true, true], $description], [398]);
        $pile = $c->group('خامة السجاد', 'Rug material', [['صوف طبيعي', 'Natural wool'], ['حرير', 'Silk rug'], ['أكريليك', 'Acrylic'], ['بولى بروبلين', 'Polypropylene'], ['قطن', 'Cotton rug']], [52]);
        $c->describe($pile, [52], 10, 'dropdown', true);

        // ── مستلزمات طبية ──────────────────────────────────────────────────
        $c->kind('medical_supplies', 'مستلزمات طبية', 'Medical supplies', [$description], [409]);
        $sterile = $c->group('حالة التعقيم', 'Sterility', [['معقم', 'Sterile'], ['غير معقم', 'Non-sterile']], [182]);
        $use = $c->group('نوع الاستخدام', 'Use type', [['للاستخدام مرة واحدة', 'Single use'], ['قابل لإعادة الاستخدام', 'Reusable']], [182]);
        $c->describe($sterile, [182], 10, 'chips', false);
        $c->describe($use, [182], 20, 'chips', false);
    }

    public function down(): void
    {
    }
};
