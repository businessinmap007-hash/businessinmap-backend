<?php

namespace Database\Seeders;

use App\Models\OptionGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Moves the restaurant menu's headings into the OPTIONS vocabulary.
 *
 *   php artisan db:seed --class=MenuLineOptionsSeeder
 *
 * «مشويات» was a platform ITEM TYPE — a permission on
 * `config.allowed_item_types` — and every food child carried **zero** line
 * options, so a restaurant had no priced vocabulary of its own at all. That
 * forced the customer down two separate narrowing steps (options, then the
 * service's item types) for one question.
 *
 * Now the fourteen headings are `line` options, which is what they always were
 * by the price test: a customer pays for «مشويات», not for «توصيل طلبات».
 * The merchant ticks them once at registration; every grill he adds files
 * itself under «مشويات», and the same list is what a customer filters by.
 *
 * The item types are left alone on purpose — `allowed_item_types` still gates
 * what a child may LIST (see ListingServiceLinkSeeder), and retail's whole
 * catalog scoping is that same column. Only the HEADING moved; nothing was
 * deleted.
 *
 * Linked SHARED (category_id = 0) to every child whose menu config already
 * allows the restaurant_menu branch, so it follows them under every root.
 * Idempotent.
 */
class MenuLineOptionsSeeder extends Seeder
{
    private const GROUP_AR = 'بنود المنيو';

    private const GROUP_EN = 'Menu Sections';

    /** The restaurant_menu branch, in its own order. */
    private const BRANCH = 'restaurant_menu';

    public function run(): void
    {
        DB::transaction(function () {
            $types = $this->branchTypes();

            if ($types->isEmpty()) {
                $this->command?->warn('  ! فرع «منيو المطاعم» غير موجود — لم يُضف شيء.');

                return;
            }

            $children = $this->childrenAllowing($types->pluck('key')->all());

            if ($children->isEmpty()) {
                $this->command?->warn('  ! لا يوجد ابن يسمح ببنود المنيو — لم يُربط شيء.');

                return;
            }

            $groupId = $this->group();
            $created = $linked = 0;

            foreach ($types->values() as $i => $type) {
                $optionId = $this->option((string) $type->name_ar, (string) $type->name_en, $groupId, $created);
                $linked += $this->link($optionId, $children, $i);
            }

            $this->command?->info('Menu line options:');
            $this->command?->line("  - بنود : {$types->count()}  (جديدة: {$created})");
            $this->command?->line("  - روابط أُضيفت : {$linked}");
            $this->command?->line('  - الأبناء : ' . $children->implode('، '));
        });
    }

    /** @return \Illuminate\Support\Collection<int,object> */
    private function branchTypes()
    {
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');

        $group = DB::table('platform_service_item_groups')
            ->where('platform_service_id', $menu)
            ->where('key', self::BRANCH)
            ->value('id');

        if (! $group) {
            return collect();
        }

        return DB::table('platform_service_item_group_type as gt')
            ->join('platform_service_item_types as t', 't.id', '=', 'gt.item_type_id')
            ->where('gt.group_id', $group)
            ->orderBy('t.sort_order')
            ->orderBy('t.id')
            ->get(['t.key', 't.name_ar', 't.name_en']);
    }

    /**
     * Children the platform already lets list these kinds of food — the map is
     * read from the config rather than hand-written, so it cannot drift from
     * what the taxonomy says.
     *
     * @param  array<int,string>  $typeKeys
     * @return \Illuminate\Support\Collection<int,string>
     */
    private function childrenAllowing(array $typeKeys)
    {
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');

        return DB::table('category_service_configs as c')
            ->join('category_children_master as m', 'm.id', '=', 'c.child_id')
            ->where('c.platform_service_id', $menu)
            ->where('c.is_active', 1)
            ->get(['c.child_id', 'm.name_ar', 'c.config'])
            ->filter(function ($row) use ($typeKeys) {
                $allowed = (json_decode((string) $row->config, true) ?: [])['allowed_item_types'] ?? [];

                return (bool) array_intersect($allowed, $typeKeys);
            })
            ->pluck('name_ar', 'child_id');
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
