<?php

namespace Database\Seeders;

use App\Models\OptionGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * What a vehicle showroom actually sells.
 *
 *   php artisan db:seed --class=VehicleTypeOptionsSeeder
 *
 * A showroom's heading was «مركبة معروضة» — the platform item type, mirrored
 * into an option because the child had nothing better. It says only that the
 * thing is a vehicle. «سيدان» says what a customer is looking for, and paired
 * with the brand modifiers the child already carries it reads
 *
 *     سيدان — BMW
 *     بيك أب — تويوتا
 *
 * A LINE group by the price test: a customer buys a سيدان. The brand does not
 * exist without something to be the brand OF, which is why «ماركات السيارات»
 * stays a modifier.
 *
 * Owner-approved list, exactly three — سيدان، SUV، بيك أب. Do NOT add
 * هاتشباك/كروس أوفر/كوبيه/ميني فان without asking; they were proposed once and
 * deliberately not approved.
 *
 * Linked SHARED (category_id = 0) to the children that sell the VEHICLE
 * itself: two showrooms that LIST one, and **خدمة ليموزين** (560 businesses,
 * the platform's largest child) and **نقل ركاب** (55), which sell the RIDE.
 *
 * The two transport children are not the showroom case repeated — they close a
 * real hole. Their own line group «مركبات النقل والركاب» (g60) reaches them
 * with big vehicles only: كوتش، ميكروباص ١٥، ميني ڤان ٧، باص ٥٠، ميني باص ٢٥.
 * **A customer asking for a sedan with a driver could not be answered at all**,
 * which is the commonest request either of them gets. The two groups are
 * complementary, not competing — g60 carries the fleet, this one the cars —
 * and with the modifiers each child already has, an offering reads
 *
 *     سيدان — مرسيدس — سيارة بسائق
 *
 * The children carrying «ماركات السيارات» but NOT listed here are workshops and
 * parts shops. They fit and service vehicles, they never sell one, so a body
 * type is not their heading.
 */
class VehicleTypeOptionsSeeder extends Seeder
{
    private const GROUP_AR = 'نوع المركبة';

    private const GROUP_EN = 'Vehicle Type';

    /** @var array<int,array{0:string,1:string}> */
    private const OPTIONS = [
        ['سيدان', 'Sedan'],
        ['SUV', 'SUV'],
        ['بيك أب', 'Pickup'],
    ];

    /**
     * Children that sell the vehicle itself — one lists it, two drive it.
     *
     * «سيارات» #53 stood here until 2026-08-17/18 (`f3a03d1c`), when the owner
     * folded it into «معرض سيارات» #188 — «خليه معرض سيارات ونفذ الطى والنقل»
     * — and moved the keeper onto root «سيارات» #13. The keeper already
     * carries the whole vocabulary this seeder writes; naming the folded
     * child here would only relink a child with no root to stand under, for
     * `OrphanChildLinksCleanupSeeder` to strip straight back off.
     */
    private const CHILDREN = ['معرض سيارات', 'خدمة ليموزين', 'نقل ركاب'];

    public function run(): void
    {
        DB::transaction(function () {
            $children = DB::table('category_children_master')
                ->whereIn('name_ar', self::CHILDREN)
                ->pluck('name_ar', 'id');

            if ($children->isEmpty()) {
                $this->command?->warn('  ! لا يوجد ابن معارض سيارات — لم يُربط شيء.');

                return;
            }

            $groupId = $this->group();
            $created = $linked = 0;

            foreach (self::OPTIONS as $i => [$ar, $en]) {
                $optionId = $this->option($ar, $en, $groupId, $created);
                $linked += $this->link($optionId, $children, $i);
            }

            // The showroom now has a vocabulary of its own, so the mirrored item
            // type «مركبة معروضة» is a second heading saying less. Drop it —
            // MenuLineOptionsSeeder leaves such a child alone from here on.
            $dropped = $this->dropMirroredItemTypeBands($children->keys()->all(), $groupId);

            $this->command?->info('Vehicle types:');
            $this->command?->line("  - خيارات جديدة : {$created}");
            $this->command?->line("  - روابط أُضيفت : {$linked}");
            $this->command?->line("  - بنود «القائمة» أُزيلت : {$dropped}");
            $this->command?->line('  - الأبناء : ' . $children->implode('، '));
        });
    }

    /**
     * Remove this child's «بنود المنيو» bands, which only ever mirrored the
     * platform item types. Scoped to that one group so a child's real options
     * are never touched.
     */
    private function dropMirroredItemTypeBands(array $childIds, int $keepGroupId): int
    {
        $bandGroup = DB::table('option_groups')->where('name_ar', 'بنود المنيو')->value('id');

        if (! $bandGroup || (int) $bandGroup === $keepGroupId) {
            return 0;
        }

        return DB::table('category_child_option')
            ->whereIn('child_id', $childIds)
            ->whereIn('option_id', DB::table('options')->where('group_id', $bandGroup)->select('id'))
            ->delete();
    }

    private function group(): int
    {
        $id = DB::table('option_groups')->where('name_ar', self::GROUP_AR)->value('id');

        if ($id) {
            DB::table('option_groups')->where('id', $id)
                ->update(['price_role' => OptionGroup::ROLE_LINE, 'is_active' => 1, 'updated_at' => now()]);

            return (int) $id;
        }

        return (int) DB::table('option_groups')->insertGetId([
            'name_ar' => self::GROUP_AR,
            'name_en' => self::GROUP_EN,
            'reorder' => (int) DB::table('option_groups')->max('reorder') + 1,
            'is_active' => 1,
            'price_role' => OptionGroup::ROLE_LINE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Matched on the globally-unique name_en; a found option keeps its group. */
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
                'category_id' => 0,   // shared: follows the child under every root
                'option_id' => $optionId,
                'reorder' => $order,
            ];
        }

        return DB::table('category_child_option')->insertOrIgnore($rows);
    }
}
