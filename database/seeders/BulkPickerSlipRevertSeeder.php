<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Undoes a services-bulk save that wrote ONE vocabulary onto a whole root.
 *
 *     php artisan db:seed --class=BulkPickerSlipRevertSeeder
 *
 * The bulk child-options screen in replace mode applies the picked group to
 * every selected child AND withdraws whatever each of them was saying. Pick a
 * root's worth of children with one group in the picker and the root comes out
 * of it saying one thing:
 *
 *   2026-08-11 22:45  مصانع    «أنواع الأبواب والشبابيك»  → 42 children
 *                              a food factory sold «شاتر كهربائي»
 *   2026-08-11 23:41  شركات    «أنواع الأجهزة الرياضية»   → 69 children
 *                              a contractor sold treadmills
 *
 * Both were slips, confirmed by the owner. It is the third time this screen has
 * done this — on 2026-08-11 03:01 it switched booking on for all seventy
 * «شركات» children and emptied their allowed_item_types.
 *
 * Three things come out, in this order and for this reason:
 *
 *   1. the imposed LINKS at that root's scope, except for the children whose
 *      trade the group actually is;
 *   2. the `pinned` records behind them, or the pin re-asserts the link;
 *   3. every `withdrawn` record from the same window — LAST and WHOLE, because
 *      the withdrawal record outranks every seeder on the platform. Leave one
 *      standing and the vocabulary it took can never be restored.
 *
 * ⚠ A withdrawal is keyed by CHILD, not by root. The شركات save blocked the
 * مصانع restoration for six children that stand under both, which is why the
 * two events are reverted together rather than one at a time.
 *
 * A merchant's own tick is never removed, and the guard is per-CHILD: a flat
 * "this option is ticked somewhere" spared «بي في سي (UPVC)» on all forty-three
 * factories, because three accounts under «باب وشباك» hold it.
 *
 * The vocabularies themselves come back from the ordinary seeders once the
 * withdrawals are gone — run ChildTradeVocabulariesSeeder and
 * LinkCategoryChildrenToOptionsSeeder after this.
 *
 * Idempotent: a second run reports zero of everything.
 */
class BulkPickerSlipRevertSeeder extends Seeder
{
    /**
     * The saves being undone.
     *
     * `rightful_holders` are the children the imposed group genuinely belongs
     * to under that root — they keep it. Everyone else was handed it by the
     * picker.
     *
     * @var array<int,array<string,mixed>>
     */
    private const SLIPS = [
        [
            'root_slug' => 'factories',
            'from' => '2026-08-11 22:45:33',
            'to' => '2026-08-11 22:46:06',
            'imposed_group' => 'أنواع الأبواب والشبابيك',
            'rightful_holders' => [50],   // باب وشباك
        ],
        [
            'root_slug' => 'companies',
            'from' => '2026-08-11 23:41:43',
            'to' => '2026-08-11 23:41:45',
            'imposed_group' => 'أنواع الأجهزة الرياضية',
            'rightful_holders' => [24],   // أجهزة رياضية
        ],
    ];

    /**
     * (child, group) pairs that must be SHARED again, not root-scoped.
     *
     * A mirror puts a vocabulary back under one root, which is right when the
     * child answers differently per root and wrong when it does not. «ماركات
     * السيارات» on «قطع غيار سيارات» #44 was shared before the save and has to
     * be shared after it: a scoped row is what once let the SHOP say BMW while
     * the factory beside it could not, which is the bug TradeAxesTest exists
     * for. Restored at scope 0, and the scoped copies are dropped as duplicates
     * of a row that already covers every root.
     *
     * @var array<int,array{child:int,group:string}>
     */
    private const SHARED_AGAIN = [
        ['child' => 44, 'group' => 'ماركات السيارات'],
        // «أجهزة كهربائية» #88 stands under four roots and said the same
        // eighteen appliances under each — four scoped copies of one answer.
        // TradeVocabularyTest names this child for the rule.
        ['child' => 88, 'group' => 'أنواع الأجهزة الكهربائية'],
        // «استيراد وتصدير» #150 lost the food range outright: it holds no
        // market list, so this modifier was the only answer it had to «what do
        // you deal in». Nothing to copy from, so the whole group comes back.
        ['child' => 150, 'group' => 'أصناف المنتجات الغذائية'],
    ];

    /**
     * Universal axes the slip also handed out, kept ONLY where they contradict
     * a decision already on the record.
     *
     * The rest of what the save pinned — نطاق التعامل، التسليم والاستلام،
     * الاستبدال والإرجاع across the root — stays. It is the same evening-out
     * this walk has been doing child by child, and there is no reason to strip
     * an axis just because a slip is what supplied it.
     *
     * These two have a reason:
     *
     *   #8  اكسسوار  the owner withdrew six groups from it by hand at 20:17
     *                and the save handed one of that shape straight back;
     *   #34 طوب      a kiln fires new brick only, so جديد · مستعمل is a
     *                question with one answer — noise on a pricing screen.
     *
     * @var array<int,array{child:int,group:string,root_slug:string}>
     */
    private const CONTRADICTED = [
        ['child' => 8, 'group' => 'الاستبدال والإرجاع', 'root_slug' => 'factories'],
        ['child' => 34, 'group' => 'حالة المنتج', 'root_slug' => 'factories'],
    ];

    public function run(): void
    {
        $this->command?->info('Bulk picker slip revert:');

        DB::transaction(function () {
            foreach (self::SLIPS as $slip) {
                $this->revert($slip);
            }

            foreach (self::SHARED_AGAIN as $pair) {
                $this->reshare($pair['child'], $pair['group']);
            }

            foreach (self::CONTRADICTED as $entry) {
                $this->unpick($entry['child'], $entry['group'], $entry['root_slug']);
            }
        });
    }

    private function unpick(int $childId, string $groupName, string $rootSlug): void
    {
        $rootId = (int) DB::table('categories')->where('slug', $rootSlug)->value('id');

        $options = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $groupName)->pluck('o.id');

        $links = DB::table('category_child_option')
            ->where('child_id', $childId)->where('category_id', $rootId)
            ->whereIn('option_id', $options)->delete();

        $pins = DB::table('category_child_option_decisions')
            ->where('child_id', $childId)->where('kind', 'pinned')
            ->whereIn('option_id', $options)->delete();

        $this->command?->line("  - «{$groupName}» × ابن {$childId} تحت «{$rootSlug}»"
            . " : روابط {$links} · تثبيتات {$pins}");
    }

    private function reshare(int $childId, string $groupName): void
    {
        $options = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $groupName)->pluck('o.id');

        $blocked = DB::table('category_child_option_decisions')
            ->where('child_id', $childId)->where('kind', 'withdrawn')
            ->pluck('option_id')->all();

        /*
         * What the child says ANYWHERE is what it should say everywhere — that
         * preserves a narrowing, where taking the whole group would hand back
         * what `child_option_scopes.php` had cut away. Only when the save left
         * it with nothing to copy does the whole group come back.
         */
        $anywhere = DB::table('category_child_option')
            ->where('child_id', $childId)->whereIn('option_id', $options)
            ->distinct()->pluck('option_id');

        $options = $anywhere->isNotEmpty() ? $anywhere : $options;

        $held = DB::table('category_child_option')
            ->where('child_id', $childId)->where('category_id', 0)
            ->whereIn('option_id', $options)->pluck('option_id')->all();

        $rows = [];

        foreach ($options as $optionId) {
            if (in_array($optionId, $held) || in_array($optionId, $blocked)) {
                continue;
            }

            $rows[] = ['child_id' => $childId, 'category_id' => 0, 'option_id' => $optionId, 'reorder' => 0];
        }

        $added = $rows === [] ? 0 : DB::table('category_child_option')->insertOrIgnore($rows);

        // Safe only because the shared row above already covers every root.
        $dropped = DB::table('category_child_option')
            ->where('child_id', $childId)->where('category_id', '>', 0)
            ->whereIn('option_id', $options)->delete();

        $this->command?->line("  - «{$groupName}» × ابن {$childId} عادت مشتركة"
            . " : أُضيفت {$added} · حُذفت نسخ مقيَّدة {$dropped}");
    }

    /** @param array<string,mixed> $slip */
    private function revert(array $slip): void
    {
        $rootId = (int) DB::table('categories')->where('slug', $slip['root_slug'])->value('id');

        if ($rootId <= 0) {
            $this->command?->warn("  ! الجذر «{$slip['root_slug']}» غير موجود — يُتخطّى.");

            return;
        }

        $imposed = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $slip['imposed_group'])->pluck('o.id');

        // Collected first and deleted by id: MariaDB refuses a DELETE whose
        // FROM carries an alias, and the correlated guard below needs one.
        $doomed = DB::table('category_child_option as co')
            ->whereIn('co.option_id', $imposed)
            ->where('co.category_id', $rootId)
            ->whereNotIn('co.child_id', $slip['rightful_holders'])
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('option_user as ou')
                ->join('users as u', 'u.id', '=', 'ou.user_id')
                ->whereColumn('ou.option_id', 'co.option_id')
                ->whereColumn('u.category_child_id', 'co.child_id'))
            ->pluck('co.id');

        $links = DB::table('category_child_option')->whereIn('id', $doomed)->delete();

        $pins = DB::table('category_child_option_decisions as d')
            ->join('options as o', 'o.id', '=', 'd.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->whereBetween('d.created_at', [$slip['from'], $slip['to']])
            ->where('d.kind', 'pinned')
            ->where('g.name_ar', $slip['imposed_group'])
            ->whereNotIn('d.child_id', $slip['rightful_holders'])
            ->pluck('d.id');

        $pins = DB::table('category_child_option_decisions')->whereIn('id', $pins)->delete();

        $withdrawals = DB::table('category_child_option_decisions')
            ->whereBetween('created_at', [$slip['from'], $slip['to']])
            ->where('kind', 'withdrawn')
            ->delete();

        $this->command?->line("  - «{$slip['root_slug']}» × «{$slip['imposed_group']}»");
        $this->command?->line("      روابط مفروضة أُزيلت : {$links}"
            . " · تثبيتات : {$pins} · سجلات سحب : {$withdrawals}");
    }
}
