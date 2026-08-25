<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MerchantOfferingVocabulary;
use Database\Seeders\OptionGroupGapsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «راجع باقي مجموعات الخيارات وأضف إليها ما ينقصها مثل الفواكه والخضروات»
 *  — المالك، 2026-08-25.
 *
 * Rolls back.
 */
class OptionGroupGapsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Both halves of the pass — the goods file and the services file. They are
     * merged per group, exactly as the seeder merges them.
     *
     * @return array<string,array<string,string>>
     */
    private function map(): array
    {
        $map = [];

        foreach (['option_group_gaps.php', 'option_group_gaps_services.php'] as $file) {
            foreach ((require base_path('database/seeders/data/' . $file))['extend'] as $group => $rows) {
                $map[$group] = array_merge($map[$group] ?? [], $rows);
            }
        }

        return $map;
    }

    private function groupId(string $nameAr): int
    {
        return (int) DB::table('option_groups')->where('name_ar', $nameAr)->value('id');
    }

    // ── the rows are there ──────────────────────────────────────────────────

    public function test_every_group_the_list_extends_exists_and_is_live(): void
    {
        foreach ($this->map() as $name => $rows) {
            $group = DB::table('option_groups')->where('name_ar', $name)->first(['id', 'is_active']);

            $this->assertNotNull($group, "«{$name}» غير موجودة.");
            $this->assertSame(1, (int) $group->is_active, "«{$name}» موقوفة — لا يُضاف إليها.");
        }
    }

    public function test_every_name_on_the_list_reached_its_group(): void
    {
        $missing = [];

        foreach ($this->map() as $name => $rows) {
            $id = $this->groupId($name);
            $have = DB::table('options')->where('group_id', $id)->pluck('name_ar')->all();

            foreach ($rows as $ar => $en) {
                if (! in_array($ar, $have, true)) {
                    $missing[] = "«{$name}» / «{$ar}»";
                }
            }
        }

        $this->assertSame([], $missing, 'لم تصل : ' . implode(' · ', $missing));
    }

    public function test_an_english_name_is_spent_once_platform_wide(): void
    {
        // The invariant the seeder refuses to break. A duplicate here is how
        // «Turkish Carpet (2)» is born.
        $dupes = DB::table('options')
            ->select('name_en')
            ->whereNotNull('name_en')
            ->where('name_en', '!=', '')
            ->groupBy('name_en')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name_en')
            ->all();

        $this->assertSame([], $dupes, 'أسماء إنجليزية مكررة : ' . implode(' · ', $dupes));
    }

    // ── and nothing else moved ──────────────────────────────────────────────

    /**
     * The invariant of the whole pass: a new row goes exactly where its group
     * already goes — never to a child that did not have the group, never to a
     * root the child does not carry it under.
     */
    public function test_a_new_row_reaches_exactly_the_children_that_had_the_group(): void
    {
        $wrong = [];

        foreach ($this->map() as $group => $rows) {
            $id = $this->groupId($group);
            $named = array_map('strval', array_keys($rows));

            $newIds = DB::table('options')->where('group_id', $id)->whereIn('name_ar', $named)->pluck('id');
            $oldIds = DB::table('options')->where('group_id', $id)->whereNotIn('name_ar', $named)->pluck('id');

            $pairs = fn ($ids) => DB::table('category_child_option')->whereIn('option_id', $ids)
                ->selectRaw("CONCAT(child_id, '-', category_id) as pair")
                ->distinct()->pluck('pair')->sort()->values()->all();

            $had = $pairs($oldIds);
            $has = $pairs($newIds);

            foreach (array_diff($has, $had) as $extra) {
                $wrong[] = "«{$group}» وصلت إلى {$extra} ولم تكن عنده";
            }
        }

        $this->assertSame([], $wrong, implode(' · ', $wrong));
    }

    public function test_adding_rows_created_no_group_and_no_decision(): void
    {
        $decisions = DB::table('category_child_option_decisions')->count();
        $groups = DB::table('option_groups')->count();

        (new OptionGroupGapsSeeder())->run();

        $this->assertSame($decisions, DB::table('category_child_option_decisions')->count());
        $this->assertSame($groups, DB::table('option_groups')->count());
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $options = DB::table('options')->count();
        $links = DB::table('category_child_option')->count();

        (new OptionGroupGapsSeeder())->run();

        $this->assertSame($options, DB::table('options')->count());
        $this->assertSame($links, DB::table('category_child_option')->count());
    }

    /**
     * ⚠ The failure this pass exists to avoid. A row nobody carries is not an
     * addition — it is a name in a table, invisible on every screen, and it
     * reads as a deliberate absence forever after.
     */
    public function test_no_added_row_is_stranded(): void
    {
        $stranded = [];

        foreach ($this->map() as $group => $rows) {
            $id = $this->groupId($group);

            // A group nobody carries at all strands nothing — there is no
            // carriage to follow.
            $carried = DB::table('category_child_option as l')
                ->join('options as o', 'o.id', '=', 'l.option_id')
                ->where('o.group_id', $id)->whereNotIn('o.name_ar', array_map('strval', array_keys($rows)))
                ->exists();

            if (! $carried) {
                continue;
            }

            foreach (array_keys($rows) as $ar) {
                $optionId = (int) DB::table('options')->where('group_id', $id)->where('name_ar', $ar)->value('id');

                if (! DB::table('category_child_option')->where('option_id', $optionId)->exists()) {
                    $stranded[] = "«{$group}» / «{$ar}»";
                }
            }
        }

        $this->assertSame([], $stranded, 'لا تصل أحدًا : ' . implode(' · ', $stranded));
    }

    /**
     * ⚠ The mechanical half of the failure caught during review: this pass
     * extended «أنواع المناسبات» before finding it has a CLOSED per-child
     * scope — hardcoded option ids in data/child_option_scopes.php for «قاعة
     * مناسبات» #527 and «مركز مؤتمرات واجتماعات» #528 — and the addition was
     * reverted. A row added to a scoped group without a matching scope entry
     * is stripped straight back off by `ChildOptionScopeSeeder`.
     */
    public function test_no_extended_group_is_governed_by_a_hardcoded_scope(): void
    {
        $scoped = array_keys(require base_path('database/seeders/data/child_option_scopes.php'));

        $offenders = array_intersect(array_keys($this->map()), $scoped);

        $this->assertSame([], $offenders, 'مجموعات مقيَّدة بخريطة نطاق ثابتة : ' . implode(' · ', $offenders));
    }

    /**
     * ⚠ The other half, which no structural check can catch: four groups —
     * «خدمات الفندق», «خدمات النادي الرياضي», «خدمات غسيل السيارات», «وسيلة
     * الشحن» — were extended and reverted because each has a SMALL, HAND-
     * ARGUED list with a stated ruling in its own file's prose («ثلاثة صفوف
     * ولا رابع», «مركز خدمة متكامل»…). «أنواع التأمين» was moved into its
     * origin file (company_child_vocabularies.php) on the same review, not
     * because it was closed but because a group WITH an origin file is
     * extended there, keeping one file able to say the whole list.
     *
     * This list is deliberately named by hand — grep the group across
     * `database/seeders/` before adding a new entry to either gaps file, per
     * [[closed-vocabulary-maps]], and add its name here if it turns out to
     * have an owner.
     */
    public function test_no_extended_group_is_on_the_known_closed_list(): void
    {
        $closed = [
            'خدمات الفندق', 'خدمات النادي الرياضي', 'خدمات غسيل السيارات',
            'وسيلة الشحن', 'أنواع المناسبات', 'أنواع التأمين',
        ];

        $offenders = array_intersect(array_keys($this->map()), $closed);

        $this->assertSame([], $offenders, 'مجموعات معروف أن لها صاحبًا : ' . implode(' · ', $offenders));
    }

    // ── what the merchant sees ──────────────────────────────────────────────

    /**
     * The end of the wire: a shop that already carried the group is offered the
     * new names, without anyone linking anything.
     */
    public function test_a_carpet_shop_is_offered_the_new_carpets(): void
    {
        $groupId = $this->groupId('أنواع السجاد');

        $link = DB::table('category_child_option as l')
            ->join('options as o', 'o.id', '=', 'l.option_id')
            ->where('o.group_id', $groupId)
            ->first(['l.child_id', 'l.category_id']);

        if ($link === null) {
            $this->markTestSkipped('«أنواع السجاد» ليست مع أى ابن فى هذه القاعدة.');
        }

        $rootId = (int) $link->category_id;

        if ($rootId === 0) {
            $rootId = (int) DB::table('category_parent_child')
                ->where('child_id', $link->child_id)->value('parent_id');
        }

        $shop = User::query()->where('type', 'business')->orderBy('id')->firstOrFail();
        $shop->forceFill(['category_id' => $rootId, 'category_child_id' => (int) $link->child_id])->save();

        $names = collect(app(MerchantOfferingVocabulary::class)
            ->for((int) $shop->id, (int) $link->child_id, $rootId)['lines'])
            ->flatMap(fn ($set) => collect($set)->pluck('name_ar'))
            ->all();

        $this->assertContains('سجاد تركي', $names);
        $this->assertContains('سجاد مساجد', $names);
    }

    /**
     * The three lists that were too thin to price a trade with. The numbers are
     * floors, not counts — the point is that none of them is a stub any more.
     */
    public function test_the_thinnest_lists_are_not_stubs_any_more(): void
    {
        foreach ([
            'نوع المركبة' => 12,
            'خدمات الصيدلية' => 13,
            'أقسام الصيدلية' => 13,
            'أنواع الإسفنج' => 11,
            'أنواع الأقمشة' => 25,
        ] as $name => $floor) {
            $this->assertGreaterThanOrEqual(
                $floor,
                DB::table('options')->where('group_id', $this->groupId($name))->count(),
                "«{$name}»",
            );
        }
    }

    /**
     * A row that names a PLACE can not be priced — the lesson the aisle lists
     * cost. Nothing on this list is a shelf.
     */
    public function test_nothing_added_is_a_shelf(): void
    {
        $shelfWords = ['قسم', 'أقسام', 'رف', 'أرفف', 'صفوف'];
        $offenders = [];

        foreach ($this->map() as $group => $rows) {
            // «أقسام الصيدلية» and «أقسام المكتبة» are group names, not rows —
            // only the rows are checked.
            foreach (array_keys($rows) as $ar) {
                foreach ($shelfWords as $word) {
                    if (str_starts_with((string) $ar, $word . ' ')) {
                        $offenders[] = "«{$group}» / «{$ar}»";
                    }
                }
            }
        }

        $this->assertSame([], $offenders, implode(' · ', $offenders));
    }
}
