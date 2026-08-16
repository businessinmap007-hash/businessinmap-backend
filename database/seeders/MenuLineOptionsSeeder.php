<?php

namespace Database\Seeders;

use App\Models\OptionGroup;
use App\Services\Catalog\ChildOptionDecisions;
use App\Services\Catalog\MerchantOptionCommitments;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The menu's headings, as options.
 *
 *   php artisan db:seed --class=MenuLineOptionsSeeder
 *
 * «مشويات» was a platform ITEM TYPE — a permission on
 * `config.allowed_item_types` — and food children carried zero line options,
 * so a merchant had no priced vocabulary of his own. That forced the customer
 * down two separate narrowing steps (options, then the service's item types)
 * to ask one question: محافظة، تصنيف، ابن، خيارات، خدمات.
 *
 * The bands live in **«بنود المنيو»** and are read from
 * **data/menu_line_bands.php** — NOT from the live item types any more. They
 * were mirrored from those types once and are now the source of truth in their
 * own right: the types have since collapsed to five coarse kinds that say
 * which SURFACE a child sells on, so deriving the bands from them again would
 * wipe all 44 and leave five.
 *
 * The map is per CHILD, never per branch. Branch-level linking is what put
 * «مشويات» and «وجبات أطفال» on a supermarket, which sells neither; this
 * seeder removes such strays as well as adding what is missing, so re-running
 * repairs rather than accumulates.
 *
 * A child that carries a DIFFERENT line group is left with none of these:
 * «آثاث» sells غرفة نوم، ركنة، أنتريه and «معرض سيارات» sells سيدان، SUV —
 * both better headings than anything here. Idempotent.
 */
class MenuLineOptionsSeeder extends Seeder
{
    private const GROUP_AR = 'بنود المنيو';

    private const GROUP_EN = 'Menu Sections';

    public function run(): void
    {
        DB::transaction(function () {
            $map = require __DIR__ . '/data/menu_line_bands.php';

            $groupId = $this->group();
            $created = 0;
            $optionOf = [];
            $order = [];
            $i = 0;

            foreach ($map['bands'] as $ar => $en) {
                $optionOf[$ar] = $this->option((string) $ar, (string) $en, $groupId, $created);
                $order[$ar] = $i++;
            }

            $ownIds = array_values($optionOf);
            $decisions = app(ChildOptionDecisions::class);
            $withdrawn = $decisions->blockedByChild();
            $pinned = $decisions->pinnedByChild();
            $commitments = app(MerchantOptionCommitments::class);
            $added = $removed = 0;
            $touched = [];
            $skipped = [];

            foreach ($this->menuChildren() as $childId => $name) {
                /*
                 * A child with its own richer vocabulary is LEFT ALONE — and
                 * leaving alone means writing nothing, not emptying it.
                 *
                 * This used to set `$wanted` to an empty collection and fall
                 * through, which made `$drop` the child's entire holding: every
                 * band this seeder had ever granted, deleted, because the child
                 * had grown a second list. The docblock on hasOtherLineGroup()
                 * describes that exact failure — «the seeder decides it must
                 * not trade down, and strips every band it owns» — and defends
                 * against it with a hard-coded family list, which holds only
                 * until somebody adds a genuinely new group.
                 *
                 * Somebody did, on 2026-08-16 03:09: «مني ماركت» was given the
                 * poultry list by hand, and the next full seed would have taken
                 * all twenty-four of its aisle words away for it — خضار وفاكهة،
                 * ألبان وبيض، مخبوزات، منظفات, the entire shop. Not trading down
                 * is a reason to write nothing; it was never a reason to delete.
                 */
                if ($this->hasOtherLineGroup((int) $childId, $groupId)) {
                    $skipped[] = $name;

                    continue;
                }

                $wanted = collect($map['children'][$name] ?? [])
                    ->map(fn ($band) => $optionOf[$band] ?? null)
                    ->filter()
                    ->unique()
                    ->values();

                $held = DB::table('category_child_option')
                    ->where('child_id', $childId)
                    ->whereIn('option_id', $ownIds)
                    ->pluck('option_id')
                    ->map(fn ($id) => (int) $id);

                // A band the owner unticked stays unticked: a supermarket that
                // stopped selling «ساندوتشات» is not asking to be reminded of
                // it every time this runs. `$drop` is untouched — the map is
                // still authoritative about bands nobody has ruled on.
                $add = $wanted->diff($held)->reject(fn ($id) => isset($withdrawn[(int) $childId][(int) $id]))->values();

                // A band he ticked on by hand stays on. Between them the two
                // decisions make this seeder's declaration a DEFAULT rather than
                // a demand: it still rules on every child nobody has touched.
                //
                // And so does a band a MERCHANT has committed to. This seeder
                // had no such guard at all while every other one did — it would
                // drop a heading somebody had priced, which is how «فندق
                // الاندلس» ended up with 2,000 on a «شقة» nothing offers.
                $drop = $held->diff($wanted)
                    ->reject(fn ($id) => isset($pinned[(int) $childId][(int) $id]))
                    ->diff($commitments->forChild((int) $childId, $held->diff($wanted)))
                    ->values();

                foreach ($add as $optionId) {
                    $band = array_search($optionId, $optionOf, true);

                    $added += DB::table('category_child_option')->insertOrIgnore([[
                        'child_id' => (int) $childId,
                        'category_id' => 0,   // shared: follows the child under every root
                        'option_id' => (int) $optionId,
                        'reorder' => $order[$band] ?? 0,
                    ]]);
                }

                if ($drop->isNotEmpty()) {
                    $removed += DB::table('category_child_option')
                        ->where('child_id', $childId)
                        ->whereIn('option_id', $drop)
                        ->delete();
                }

                // A child with its own vocabulary never reaches here — it is
                // skipped above — so this only reports children the map rules.
                if ($add->isNotEmpty() || $drop->isNotEmpty()) {
                    $touched[] = $name . ' (+' . $add->count() . ' -' . $drop->count() . ')';
                }
            }

            $this->command?->info('Menu line options:');
            $this->command?->line('  - بنود : ' . count($optionOf) . "  (جديدة: {$created})");
            $this->command?->line("  - روابط أُضيفت : {$added}  ·  أُزيلت : {$removed}");
            $this->command?->line('  - أبناء تغيّروا : ' . (empty($touched) ? 'لا شيء' : implode('، ', $touched)));
            $this->command?->line('  - تُركت بمعجمها الخاص : ' . (empty($skipped) ? 'لا شيء' : implode('، ', $skipped)));
        });
    }

    /** @return \Illuminate\Support\Collection<int,string> child id => name */
    private function menuChildren()
    {
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');

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
            ->whereNotIn('g.name_ar', $this->ownFamily())
            ->exists();
    }

    /**
     * The groups «بنود المنيو» was split into on 2026-08-10 are still THIS
     * seeder's vocabulary — the bands only changed drawer.
     *
     * Without this the split is self-destructing: a supermarket carrying
     * «أقسام السوبر ماركت» reads as a child with its own richer vocabulary, the
     * seeder decides it must not trade down, and strips every band it owns. It
     * took 214 links off 33 children on the first run after the split.
     *
     * Then «أقسام السوبر ماركت» was itself split into five, and the SECOND
     * generation has to be listed here for exactly the same reason — a
     * fishmonger carrying «أقسام الطازج واللحوم» would read as somebody else's
     * child. Any future split of any of these must add its offspring here too;
     * the family is the transitive one, not the direct children.
     *
     * @return array<int,string>
     */
    private function ownFamily(): array
    {
        return array_merge(
            array_column((require __DIR__ . '/data/menu_band_split.php')['groups'], 'name_ar'),
            array_keys((require __DIR__ . '/data/grocery_aisle_split.php')['groups'])
        );
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
     * taxonomy reused an English name across two item types — `seafood`
     * («مأكولات بحرية») and `seafood_grocery` («أسماك ومأكولات بحرية») were
     * both "Seafood" — ONE option serves both, under whichever Arabic name
     * reached the table first. That is why there are 44 bands and not 45.
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
