<?php

namespace Database\Seeders;

use App\Models\OptionGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Moves every menu heading into the OPTIONS vocabulary.
 *
 *   php artisan db:seed --class=MenuLineOptionsSeeder
 *
 * «مشويات» was a platform ITEM TYPE — a permission on
 * `config.allowed_item_types` — and food children carried **zero** line
 * options, so a merchant had no priced vocabulary of his own. That forced the
 * customer down two separate narrowing steps (options, then the service's item
 * types) to ask one question: محافظة، تصنيف، ابن، خيارات، خدمات.
 *
 * Every menu item type now has a line option of the same name in
 * **«بنود المنيو»**, and a child is linked to exactly the ones **its own
 * config allows** — per TYPE, never per branch. Branch-level linking is what
 * put «مشويات» and «وجبات أطفال» on a supermarket, which allows only
 * ساندوتشات and the two drink bands; this seeder removes such strays as well
 * as adding what is missing, so re-running repairs rather than accumulates.
 *
 * A child that already carries a DIFFERENT line group is left completely
 * alone: «آثاث» sells غرفة نوم، ركنة، أنتريه, and replacing that with the item
 * type «قطعة أثاث» would be a worse heading, not a better one. Same for the
 * four real-estate children on «عقارات وممتلكات».
 *
 * The item types themselves are untouched — `allowed_item_types` still gates
 * what a child may LIST, and retail's whole catalog scoping rides on it. Only
 * the HEADING moved. Idempotent.
 */
class MenuLineOptionsSeeder extends Seeder
{
    private const GROUP_AR = 'بنود المنيو';

    private const GROUP_EN = 'Menu Sections';

    public function run(): void
    {
        DB::transaction(function () {
            $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');

            if (! $menu) {
                $this->command?->warn('  ! خدمة «القائمة» غير موجودة — لم يُضف شيء.');

                return;
            }

            $groupId = $this->group();
            $created = 0;

            // type key => option id, in the platform's own order
            $optionOf = [];
            $order = [];

            foreach ($this->itemTypes($menu) as $i => $type) {
                $optionOf[$type->key] = $this->option((string) $type->name_ar, (string) $type->name_en, $groupId, $created);
                $order[$type->key] = $i;
            }

            $ownIds = array_values($optionOf);
            $added = $removed = 0;
            $touched = [];
            $skipped = [];

            foreach ($this->menuChildren($menu) as $childId => $name) {
                if ($this->hasOtherLineGroup((int) $childId, $groupId)) {
                    $skipped[] = $name;
                    continue;
                }

                $wanted = collect($this->allowedTypes((int) $childId, $menu))
                    ->map(fn ($key) => $optionOf[$key] ?? null)
                    ->filter()
                    ->unique()
                    ->values();

                $held = DB::table('category_child_option')
                    ->where('child_id', $childId)
                    ->whereIn('option_id', $ownIds)
                    ->pluck('option_id')
                    ->map(fn ($id) => (int) $id);

                $add = $wanted->diff($held);
                $drop = $held->diff($wanted);

                foreach ($add as $optionId) {
                    $key = array_search($optionId, $optionOf, true);

                    $added += DB::table('category_child_option')->insertOrIgnore([[
                        'child_id' => (int) $childId,
                        'category_id' => 0,   // shared: follows the child under every root
                        'option_id' => (int) $optionId,
                        'reorder' => $order[$key] ?? 0,
                    ]]);
                }

                if ($drop->isNotEmpty()) {
                    $removed += DB::table('category_child_option')
                        ->where('child_id', $childId)
                        ->whereIn('option_id', $drop)
                        ->delete();
                }

                if ($add->isNotEmpty() || $drop->isNotEmpty()) {
                    $touched[] = $name . ' (+' . $add->count() . ' −' . $drop->count() . ')';
                }
            }

            $this->command?->info('Menu line options:');
            $this->command?->line('  - بنود : ' . count($optionOf) . "  (جديدة: {$created})");
            $this->command?->line("  - روابط أُضيفت : {$added}  ·  أُزيلت : {$removed}");
            $this->command?->line('  - أبناء تغيّروا : ' . (empty($touched) ? 'لا شيء' : implode('، ', $touched)));
            $this->command?->line('  - تُركت بمعجمها الخاص : ' . (empty($skipped) ? 'لا شيء' : implode('، ', $skipped)));
        });
    }

    /** @return \Illuminate\Support\Collection<int,object> */
    private function itemTypes(int $menu)
    {
        return DB::table('platform_service_item_types')
            ->where('platform_service_id', $menu)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['key', 'name_ar', 'name_en'])
            ->values();
    }

    /** @return \Illuminate\Support\Collection<int,string> child id => name */
    private function menuChildren(int $menu)
    {
        return DB::table('category_platform_services as l')
            ->join('category_children_master as m', 'm.id', '=', 'l.child_id')
            ->where('l.platform_service_id', $menu)
            ->where('l.is_active', 1)
            ->distinct()
            ->pluck('m.name_ar', 'm.id');
    }

    /**
     * A child that already sells by a richer vocabulary keeps it — «غرفة نوم»
     * beats «قطعة أثاث» as a heading, and this seeder must never trade down.
     */
    private function hasOtherLineGroup(int $childId, int $groupId): bool
    {
        return DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $childId)
            ->where('g.price_role', OptionGroup::ROLE_LINE)
            ->where('g.id', '!=', $groupId)
            ->exists();
    }

    /** @return array<int,string> */
    private function allowedTypes(int $childId, int $menu): array
    {
        return DB::table('category_service_configs')
            ->where('child_id', $childId)
            ->where('platform_service_id', $menu)
            ->where('is_active', 1)
            ->pluck('config')
            ->flatMap(fn ($c) => (json_decode((string) $c, true) ?: [])['allowed_item_types'] ?? [])
            ->unique()
            ->values()
            ->all();
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

    /**
     * Matched on the globally-unique name_en; a found option keeps its group.
     *
     * `options.name_en` carries a UNIQUE index platform-wide, so where the
     * taxonomy reuses an English name across two item types — `seafood`
     * («مأكولات بحرية») and `seafood_grocery` («أسماك ومأكولات بحرية») are both
     * "Seafood" — ONE option necessarily serves both keys, under whichever
     * Arabic name reached the table first. That is the schema's answer, not a
     * defect here: fix it by renaming the item type, never by trying to insert
     * a second option with the same name_en.
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
}
