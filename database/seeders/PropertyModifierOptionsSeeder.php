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
 * The finish is a modifier by the same test as «مودرن»: nobody buys «سوبر
 * لوكس», but a super-lux flat costs more than a semi-finished one. The room
 * count STOPPED being one on 2026-08-05, when it was merged into «الغرف» beside
 * the hotel kinds — see the note on `GROUPS`.
 *
 * Linked SHARED (category_id = 0) to the children of «عقارات وأراضي», so they
 * follow wherever those go. Idempotent.
 *
 * **Nothing runs this.** It is in no seeder list, which is why «مطور عقاري»
 * #518 stood without either group until 2026-08-17 and why the two corrections
 * above went unnoticed for so long. The links this root actually relies on are
 * declared in `data/property_child_vocabularies.php`, which IS in the chain.
 */
class PropertyModifierOptionsSeeder extends Seeder
{
    private const ROOT_SLUG = 'property-and-land';

    /*
     * `price_role` is declared per group because one of the two moved.
     *
     * The room counts were created here as «عدد الغرف» and MERGED into «الغرف»
     * on 2026-08-05, when a hotel's جناح and a flat's ثلاث غرف became one list —
     * and that list is a `line`, because it is the thing being paid for. This
     * file went on naming the dissolved group, so running it minted «عدد الغرف»
     * again, empty: `option()` finds the six rows by name_en wherever they live
     * and leaves them there, so the new group got the name and none of the rows.
     * `TaxonomyRedistributionTest` catches it as an empty active group.
     *
     * Naming «الغرف» instead is only safe with the role beside it. `group()`
     * writes the role onto whatever it finds, and «الغرف» is a line — pushing it
     * to `modifier` would tell the six hotel children their rooms are a
     * qualifier and not the thing sold.
     */
    private const GROUPS = [
        'الغرف' => [
            'name_en' => 'Room Count',
            'price_role' => OptionGroup::ROLE_LINE,
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
            'price_role' => OptionGroup::ROLE_MODIFIER,
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
                $this->command?->warn('  ! جذر «عقارات وأراضي» بلا أبناء — لم يُربط شيء.');

                return;
            }

            $created = $linked = 0;

            foreach (self::GROUPS as $nameAr => $spec) {
                $groupId = $this->group($nameAr, $spec['name_en'], $spec['price_role']);

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

    /**
     * The children of «عقارات وأراضي» — a ROOT, not a list of ids.
     *
     * This used to ask «who already carries the property types», and while the
     * only children holding them were the broker, the owner and the developer
     * that was the same question. It stopped being the same question: «شقق
     * فندقية» #537 holds «شقة» and «منتجع» #538 holds «شقة» and «ڤيلا» as
     * ACCOMMODATION types, which is correct and deliberate, and one shared row
     * was enough to pull both into the answer.
     *
     * Run as it stood, this seeder would hand a resort «على المحارة» and «نصف
     * تشطيب» and write it into «الغرف», whose hotel contents are declared by
     * HotelRoomKindOptionsSeeder — which prunes what it does not name. Two
     * seeders taking the same rows off and putting them back on every run is
     * the loop this taxonomy keeps having to undo.
     *
     * The root keeps what the old query was for: a fourth property child added
     * tomorrow inherits both groups without anyone remembering this file.
     */
    private function propertyChildren()
    {
        return DB::table('category_parent_child as pc')
            ->join('categories as r', 'r.id', '=', 'pc.parent_id')
            ->join('category_children_master as ch', 'ch.id', '=', 'pc.child_id')
            ->where('r.slug', self::ROOT_SLUG)
            ->distinct()
            ->pluck('ch.name_ar', 'ch.id');
    }

    private function group(string $nameAr, string $nameEn, string $role): int
    {
        $id = DB::table('option_groups')->where('name_ar', $nameAr)->value('id');

        if ($id) {
            DB::table('option_groups')->where('id', $id)
                ->update(['price_role' => $role, 'is_active' => 1, 'updated_at' => now()]);

            return (int) $id;
        }

        return (int) DB::table('option_groups')->insertGetId([
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'reorder' => (int) DB::table('option_groups')->max('reorder') + 1,
            'is_active' => 1,
            'price_role' => $role,
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
