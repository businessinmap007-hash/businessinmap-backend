<?php

use App\Services\Catalog\MenuShapeCurator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «كمّل على مواد البناء» — المالك، 2026-10-04. Building materials are sold by size and thickness, so
 * each group gets a kind whose fields say exactly that; the trades that really have a choice per item
 * (stone finish, how a door opens, a timber's grade) also get a describing group.
 *
 * «نظام التصنيع» STAYS on these trades: marble, glass, doors and timber ARE made to order — unlike the
 * shops batch one took it from. Scoped like the earlier batches: kinds travel with the group,
 * describing groups stay with the dedicated trade.
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
        $size = ['size', 10, true, true, true, true];
        $thickness = ['thickness_mm', 20, true, true, true, true];
        $color = ['color', 30, false, true, true, true];

        $c->attributes([['thickness_mm', 'السُمك (مم)', 'Thickness (mm)', 'number']]);

        $c->kind('stone', 'رخام وجرانيت', 'Marble and granite', [$size, $thickness, $color, $description], [407]);
        $finish = $c->group('تشطيب السطح', 'Surface finish', [['مصقول', 'Polished'], ['مطفي', 'Honed'], ['خشن (فلامي)', 'Flamed'], ['مجلخ', 'Brushed']], [173, 174]);
        $c->describe($finish, [173, 174], 10, 'chips', false);

        $c->kind('doors_windows', 'أبواب وشبابيك', 'Doors and windows', [$size, $color, $description], [299]);
        $opening = $c->group('طريقة الفتح', 'Opening', [['مفصلي', 'Hinged'], ['منزلق', 'Sliding'], ['قلاب', 'Tilt'], ['ثابت', 'Fixed']], [50]);
        $c->describe($opening, [50], 10, 'chips', false);

        $c->kind('timber', 'أخشاب', 'Timber', [$size, $thickness, $description], [418]);
        $grade = $c->group('درجة الخشب', 'Timber grade', [['درجة أولى', 'First grade'], ['درجة ثانية', 'Second grade'], ['خشب معالج ومجفف', 'Treated and dried']], [301]);
        $c->describe($grade, [301], 10, 'chips', false);

        $c->kind('ceramic', 'سيراميك وبورسلين', 'Ceramic and porcelain', [$size, $thickness, $color, $description], [649]);
        $c->kind('sanitary_ware', 'أدوات صحية', 'Sanitary ware', [$color, $description], [403]);
        $c->kind('glass', 'زجاج', 'Glass', [$size, $thickness, $color, $description], [402]);
        $c->kind('building_basics', 'مواد بناء أساسية', 'Basic building materials', [['weight', 10, true, true, true, true], $size, $description], [396, 399, 415]);
        $c->kind('paints_hardware', 'حدايد وبويات', 'Paints and hardware', [['volume', 10, true, true, true, true], $color, $description], [410]);
        $c->kind('carpentry_supplies', 'مستلزمات نجارة', 'Carpentry supplies', [$size, $color, $description], [397]);
    }

    public function down(): void
    {
    }
};
