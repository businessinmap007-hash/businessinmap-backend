<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1c continued: bulk-link category_children_master to the options that
 * actually describe them. Before this only child 68 had any real link.
 *
 * Deliberately additive, never destructive, and safe to re-run:
 *  - INSERTs a missing (child_id, option_id) pair; never deletes or edits an
 *    existing category_child_option row. The owner is hand-curating options
 *    through the bulk editor in parallel (see item-type-vs-option-rule
 *    memory) — this must never fight that.
 *  - Matches by CHILD NAME keyword per option GROUP, not by root category. A
 *    child can sit under more than one root, and a root can mix unrelated
 *    themes (e.g. «المحلات أو أونلاين» holds both فواكه and قطع غيار سيارات),
 *    so matching the child's own name is the more precise signal.
 *  - Group 12 «أنماط خدمة وتجارية» USED to be universal here: every child got
 *    all 24, on the reasoning that an unusable option costs nothing. It cost
 *    more than nothing — 7,634 links, three quarters of the platform's total,
 *    and a filter list where a painter was asked about «تصدير». That group is
 *    now split into eight single-question groups and assigned per child by
 *    ChildOptionGroupsSeeder, which OWNS those eight. Its rule is gone from
 *    here so a re-run can never spray them back.
 *
 * If the owner moves more service items into an EXISTING option group later,
 * re-run this seeder — it re-derives the option list from the group each
 * time, so newly-active options reach already-matched children automatically.
 * A brand-new group only needs one new pattern line below.
 */
class LinkCategoryChildrenToOptionsSeeder extends Seeder
{
    /**
     * group_id => ['include' => regex, 'exclude' => ?regex]. Matched against
     * a normalized name_ar (alef/ta-marbuta/alef-maksura folded). Null include
     * means universal (every child).
     */
    private const RULES = [
        1 => [
            'include' => 'سيار|موتوسيكل|دراج[ةه]|توك ?توك|نقل ركاب|ليموزين|تاكسي|جراج|ونش|كوتش(?!ي)|مقطور[ةه]|ميكروباص|مني ?باص|مني ?فان|ربع نقل',
            'exclude' => null,
        ],
        9 => [
            'include' => 'عقار|ارض|شق[ةه]|فيلا|ڤيلا|عمار[ةه]|مصنع|مزرع[ةه]|مكتب(?!ي)|محل|ورش[ةه]|استراح|^معرض$',
            // a bare «مكتب» is the courier desk under شحن وتوصيل, not a property
            // office — «مكتب عقاري» still matches
            'exclude' => 'سيار|موتوسيكل|^مكتب$',
        ],
        10 => [
            'include' => 'ملابس|ازياء|اقمش[ةه]|احذي[ةه]|شنط|جلود|زفاف|اكسسوار',
            'exclude' => 'سيار|موبيل|كمبيوتر',
        ],
        3 => [
            'include' => 'اثاث|موبيلي[ةا]|مفروش|صالون|انتريه|سفر[ةه]|غرف[ةه] نوم|غرف[ةه] اطفال|نجف|مطبخ|مطابخ',
            'exclude' => 'سيار|صيد|بحري|نيلي',
        ],
        11 => [
            'include' => 'تعبئ[ةه]|تغليف|اكواب|اطباق فوم|اطباق فويل|علب[ةه] معدن',
            'exclude' => null,
        ],
        23 => [
            'include' => 'قاع[ةه]|فندق|مؤتمر|اجتماع|ندو[ةه]|حفل|كافي[ةه]|مطعم|منتجع|تدريب|⭐',
            // hospitality answers wifi through «مرافق الإقامة» («واي فاي مجاني»);
            // adding the generic «واي فاي» here would ask the same question twice
            'exclude' => 'مستلزمات|تنسيق|فندق|منتجع|نزل|هوستل|بيت ضياف',
        ],
    ];

    public function run(): void
    {
        // Only children that hang off a root. 80 master rows belong to no root
        // — retired stars, unlinked duplicates, and the real-estate rows that
        // became booking item types — and linking options onto those is how a
        // stale answer sheet gets waiting for whoever re-attaches the child.
        // OrphanChildLinksCleanupSeeder clears them; this must not refill them.
        $children = DB::table('category_children_master as ch')
            ->whereExists(fn ($q) => $q->from('category_parent_child as pc')->whereColumn('pc.child_id', 'ch.id'))
            ->get(['ch.id', 'ch.name_ar']);

        $existingPairs = DB::table('category_child_option')
            ->get(['child_id', 'option_id'])
            ->map(fn ($r) => $r->child_id.':'.$r->option_id)
            ->flip();

        $rows = [];

        foreach (self::RULES as $groupId => $rule) {
            $optionIds = DB::table('options')->where('group_id', $groupId)->orderBy('id')->pluck('id')->values();

            if ($optionIds->isEmpty()) {
                continue;
            }

            $matched = $rule['include'] === null
                ? $children
                : $children->filter(fn ($c) => $this->matches($c->name_ar, $rule['include'], $rule['exclude']));

            foreach ($matched as $child) {
                foreach ($optionIds as $i => $optionId) {
                    $key = $child->id.':'.$optionId;

                    if ($existingPairs->has($key)) {
                        continue;
                    }

                    $rows[] = ['child_id' => $child->id, 'option_id' => $optionId, 'reorder' => $i];
                    $existingPairs->put($key, true);
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('category_child_option')->insert($chunk);
        }
    }

    private function matches(string $name, string $include, ?string $exclude): bool
    {
        $normalized = $this->normalizeArabic($name);

        if (! preg_match('/('.$include.')/u', $normalized)) {
            return false;
        }

        return ! ($exclude && preg_match('/('.$exclude.')/u', $normalized));
    }

    private function normalizeArabic(string $s): string
    {
        $s = str_replace(['أ', 'إ', 'آ'], 'ا', $s);
        $s = str_replace('ة', 'ه', $s);
        $s = str_replace('ى', 'ي', $s);

        return $s;
    }
}
