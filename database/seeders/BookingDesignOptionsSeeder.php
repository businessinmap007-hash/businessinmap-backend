<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The words the booking canvas draws that the vocabulary did not have yet — each one a CHOICE the business ticks
 * for itself, never something it is handed whole («كل الاضافات الموجودة فى التصميم ضيفها الى قاعدة البيانات وتكون
 * اختيارات للبزنس يختار منها ما لديه بالفعل» — المالك، 2026-10-07).
 *
 *     php artisan db:seed --class=BookingDesignOptionsSeeder
 *
 *   وجبات Day use     فطار / غداء / عشاء — a guest who only sleeps a few hours still eats; added to the day's price.
 *                     Offered wherever «نظام الوجبات» is (the children that feed a guest at all).
 *   إطلالة الوحدة     + إطلالة على الحديقة / إطلالة على النيل — the canvas shows both next to the pool and the sea.
 *   نوع الزيارة       كشف / إعادة / استشارة — the line a clinic prices; a hospital and a medical centre hold clinics too.
 *
 * What already existed is NOT repeated: the meal plans, the pool view, «زيارة منزلية» (a lab's home collection),
 * «أنواع الأشعة», «التحاليل الطبية» and the packages are all in the vocabulary — the canvas only drew them.
 *
 * Add-only, and it asks the withdrawal ledger first ([[seeder-must-withdraw]]): what a business or an admin took off a
 * child on purpose is not handed back.
 */
class BookingDesignOptionsSeeder extends Seeder
{
    /**
     * group → [name_en, price_role, sort anchor, options (ar => en), children it is linked to].
     * `children` is either a list of trade names or `['like' => group name]` — the children that already carry that group.
     */
    private const GROUPS = [
        'وجبات Day use' => [
            'en' => 'Day Use Meals', 'role' => 'modifier', 'after' => 'نظام الوجبات',
            'options' => ['فطار' => 'Day Use Breakfast', 'غداء' => 'Day Use Lunch', 'عشاء' => 'Day Use Dinner'],
            'children' => ['like' => 'نظام الوجبات'],
        ],
        'نوع الزيارة' => [
            'en' => 'Visit Type', 'role' => 'line', 'at' => 1200,
            'options' => ['كشف' => 'Examination Visit', 'إعادة' => 'Follow-up Visit', 'استشارة' => 'Consultation Visit'],
            'children' => ['عيادة', 'مركز طبي', 'مستشفى'],
        ],
    ];

    /** group → options added to a group that already exists, linked wherever that group already is. */
    private const EXTRA_OPTIONS = [
        'إطلالة الوحدة' => ['إطلالة على الحديقة' => 'Garden View', 'إطلالة على النيل' => 'Nile View'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->command?->info('Booking design options:');

            foreach (self::GROUPS as $name => $def) {
                $groupId = $this->group($name, $def);
                $optionIds = $this->options($groupId, $def['options']);
                $linked = $this->link($optionIds, $this->childrenOf($def['children']));

                $this->command?->line("  - «{$name}» #{$groupId} : " . count($optionIds) . " خيارات · روابط جديدة {$linked}");
            }

            foreach (self::EXTRA_OPTIONS as $name => $options) {
                $groupId = (int) DB::table('option_groups')->where('name_ar', $name)->value('id');

                if ($groupId <= 0) {
                    $this->command?->warn("  ! «{$name}» غير موجودة — تُخطّي.");

                    continue;
                }

                $optionIds = $this->options($groupId, $options);
                $linked = $this->link($optionIds, $this->childrenOf(['like' => $name]));

                $this->command?->line("  - «{$name}» #{$groupId} : + " . count($optionIds) . " · روابط جديدة {$linked}");
            }
        });

        // A new group takes its alphabetical place inside its price role, like every other group.
        $this->call(DisplayOrderSeeder::class);
    }

    /** @param array<string,mixed> $def */
    private function group(string $name, array $def): int
    {
        $id = (int) DB::table('option_groups')->where('name_ar', $name)->value('id');

        $order = isset($def['after'])
            ? 1 + (int) DB::table('option_groups')->where('name_ar', $def['after'])->value('reorder')
            : (int) ($def['at'] ?? 1 + (int) DB::table('option_groups')->max('reorder'));

        if ($id <= 0) {
            $id = (int) DB::table('option_groups')->insertGetId([
                'name_ar' => $name,
                'name_en' => $def['en'],
                'reorder' => $order,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Written here AND declared in option_price_roles.php, so a standalone run gives a working screen and the
        // next OptionPriceRolesSeeder run does not reset it.
        DB::table('option_groups')->where('id', $id)->update(['price_role' => $def['role'], 'updated_at' => now()]);

        return $id;
    }

    /**
     * @param  array<string,string>  $options  name_ar => name_en
     * @return list<int>
     */
    private function options(int $groupId, array $options): array
    {
        $ids = [];
        $sort = (int) DB::table('options')->where('group_id', $groupId)->max('sort_order');

        foreach ($options as $ar => $en) {
            $id = (int) DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->value('id');

            if ($id <= 0) {
                // `options.name_en` collides across groups — one word, two meanings, two rows.
                if (DB::table('options')->where('name_en', $en)->exists()) {
                    $en .= ' (Booking)';
                }

                $id = (int) DB::table('options')->insertGetId([
                    'group_id' => $groupId,
                    'sort_order' => ++$sort,
                    'name_ar' => $ar,
                    'name_en' => $en,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * @param  array<string,mixed>|list<string>  $spec
     * @return list<int>
     */
    private function childrenOf(array $spec): array
    {
        if (isset($spec['like'])) {
            return DB::table('category_child_option as cco')
                ->join('options as o', 'o.id', '=', 'cco.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('g.name_ar', $spec['like'])
                ->distinct()->pluck('cco.child_id')->map(fn ($id) => (int) $id)->all();
        }

        return DB::table('category_children_master')->whereIn('name_ar', $spec)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<int>  $optionIds
     * @param  list<int>  $childIds
     */
    private function link(array $optionIds, array $childIds): int
    {
        $written = 0;

        foreach ($childIds as $childId) {
            $have = DB::table('category_child_option')->where('child_id', $childId)->where('category_id', 0)
                ->pluck('option_id')->map(fn ($id) => (int) $id)->all();

            $rows = collect(app(ChildOptionDecisions::class)->filter($childId, 0, $optionIds))
                ->reject(fn ($id) => in_array((int) $id, $have, true))
                ->map(fn ($id) => ['child_id' => $childId, 'category_id' => 0, 'option_id' => (int) $id, 'reorder' => 0])
                ->values()->all();

            if ($rows !== []) {
                $written += DB::table('category_child_option')->insertOrIgnore($rows);
            }
        }

        return $written;
    }
}
