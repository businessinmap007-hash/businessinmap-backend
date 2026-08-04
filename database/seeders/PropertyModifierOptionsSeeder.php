<?php

namespace Database\Seeders;

use App\Models\OptionGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The two things a property listing says that nothing could say yet.
 *
 *   php artisan db:seed --class=PropertyModifierOptionsSeeder
 *
 * «عقارات وممتلكات» holds the property TYPES — شقة، ڤيلا، أرض — and that is the
 * priced line. But a listing reads «شقة — غرفتين — سوبر لوكس», and neither the
 * room count nor the finish existed anywhere on the platform, so both had to be
 * typed into free text where no filter could reach them.
 *
 * Both are modifiers by the same test as «مودرن»: nobody buys «سوبر لوكس», but
 * a super-lux flat costs more than a semi-finished one.
 *
 * Linked SHARED (category_id = 0) to the four children that already carry the
 * property types, so they follow wherever those go. Idempotent.
 */
class PropertyModifierOptionsSeeder extends Seeder
{
    private const PROPERTY_GROUP = 'عقارات وممتلكات';

    private const GROUPS = [
        'عدد الغرف' => [
            'name_en' => 'Room Count',
            'options' => [
                ['استوديو', 'Studio'],
                ['غرفة', 'One Room'],
                ['غرفتين', 'Two Rooms'],
                ['ثلاث غرف', 'Three Rooms'],
                ['أربع غرف', 'Four Rooms'],
                ['خمس غرف فأكثر', 'Five Rooms Or More'],
            ],
        ],
        'مستوى التشطيب' => [
            'name_en' => 'Finishing Level',
            'options' => [
                ['على المحارة', 'Core And Shell'],
                ['نصف تشطيب', 'Semi Finished'],
                ['تشطيب كامل', 'Fully Finished'],
                ['سوبر لوكس', 'Super Lux'],
                ['ألترا سوبر لوكس', 'Ultra Super Lux'],
                ['مفروش', 'Furnished'],
            ],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $children = $this->propertyChildren();

            if ($children->isEmpty()) {
                $this->command?->warn('  ! لا يوجد ابن يحمل أنواع العقارات — لم يُربط شيء.');

                return;
            }

            $created = $linked = 0;

            foreach (self::GROUPS as $nameAr => $spec) {
                $groupId = $this->group($nameAr, $spec['name_en']);

                foreach ($spec['options'] as $i => [$optionAr, $optionEn]) {
                    $optionId = $this->option($optionAr, $optionEn, $groupId, $created);
                    $linked += $this->link($optionId, $children, $i);
                }
            }

            $this->command?->info('Property modifiers:');
            $this->command?->line("  - خيارات جديدة : {$created}");
            $this->command?->line("  - روابط أُضيفت : {$linked}");
            $this->command?->line('  - الأبناء : ' . $children->implode('، '));
        });
    }

    /** Children that already carry the property types — the same ones price them. */
    private function propertyChildren()
    {
        return DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->join('category_children_master as ch', 'ch.id', '=', 'co.child_id')
            ->where('g.name_ar', self::PROPERTY_GROUP)
            ->distinct()
            ->pluck('ch.name_ar', 'ch.id');
    }

    private function group(string $nameAr, string $nameEn): int
    {
        $id = DB::table('option_groups')->where('name_ar', $nameAr)->value('id');

        if ($id) {
            DB::table('option_groups')->where('id', $id)
                ->update(['price_role' => OptionGroup::ROLE_MODIFIER, 'is_active' => 1, 'updated_at' => now()]);

            return (int) $id;
        }

        return (int) DB::table('option_groups')->insertGetId([
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'reorder' => (int) DB::table('option_groups')->max('reorder') + 1,
            'is_active' => 1,
            'price_role' => OptionGroup::ROLE_MODIFIER,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Find by the globally-unique name_en, and leave a found row in whatever
     * group it already sits in — a seeder says what must EXIST, not where it
     * must be filed.
     */
    private function option(string $ar, string $en, int $groupId, int &$created): int
    {
        $id = DB::table('options')->where('name_en', $en)->value('id')
            ?: DB::table('options')->where('name_ar', $ar)->value('id');

        if ($id) {
            return (int) $id;
        }

        $created++;

        return (int) DB::table('options')->insertGetId([
            'group_id' => $groupId,
            'name_ar' => $ar,
            'name_en' => $en,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function link(int $optionId, $children, int $order): int
    {
        $rows = [];

        foreach ($children->keys() as $childId) {
            $rows[] = [
                'child_id' => (int) $childId,
                'category_id' => 0,   // shared: it follows the child under every root
                'option_id' => $optionId,
                'reorder' => $order,
            ];
        }

        return DB::table('category_child_option')->insertOrIgnore($rows);
    }
}
