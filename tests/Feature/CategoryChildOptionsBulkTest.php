<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The bulk options screen: «عند تعديل جماعى لا يمكننى الغاء ما هو محدد سابقا
 * وعند الحفظ يعطى رسالة خطا» — owner, 2026-08-09.
 */
class CategoryChildOptionsBulkTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $admin = User::query()->where('type', User::TYPE_ADMIN)->firstOrFail();

        foreach ([AdminAbility::ACCESS, AdminAbility::CATALOG] as $ability) {
            \Bouncer::allow($admin)->to($ability);
        }
        \Bouncer::refresh();

        return $admin;
    }

    /** @return array{0:int,1:int,2:int} [rootId, childId, optionId] */
    private function subject(): array
    {
        $row = DB::table('category_parent_child')->first(['parent_id', 'child_id']);
        $optionId = (int) DB::table('options')->value('id');

        return [(int) $row->parent_id, (int) $row->child_id, $optionId];
    }

    public function test_the_screen_opens(): void
    {
        [$rootId] = $this->subject();

        $this->actingAs($this->admin())
            ->get(route('admin.category-child-options.bulk.edit', ['parent_id' => $rootId]))
            ->assertOk();
    }

    /**
     * «اريد عند الحفظ ان يظل فى نفس الصفحة» — owner, 2026-08-09. It used to land
     * on the children index, which ended the session after one save.
     */
    public function test_saving_comes_back_to_the_same_screen(): void
    {
        [$rootId, $childId, $optionId] = $this->subject();

        $this->actingAs($this->admin())
            ->post(route('admin.category-child-options.bulk.update'), [
                'parent_id' => $rootId,
                'child_ids' => [$childId],
                'option_ids' => [$optionId],
                'mode' => 'append',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.category-child-options.bulk.edit', [
                'child_ids' => [$childId],
                'parent_id' => $rootId,
            ]));
    }

    /** And the child it was working on comes back ticked. */
    public function test_the_selection_survives_the_save(): void
    {
        [$rootId, $childId] = $this->subject();

        $selected = $this->actingAs($this->admin())
            ->get(route('admin.category-child-options.bulk.edit', [
                'parent_id' => $rootId,
                'child_ids' => [$childId],
            ]))
            ->assertOk()
            ->viewData('selectedChildIds');

        $this->assertContains($childId, $selected);
    }

    /**
     * Opening the screen on a root that holds none of the picked children left
     * NO panel active — every child checkbox rendered `disabled`, so the browser
     * submitted no `child_ids` at all and the save could only ever answer «حقل
     * الأقسام الفرعية مطلوب». The page had nothing tickable on it.
     */
    public function test_a_root_holding_none_of_the_picked_children_still_leaves_a_panel_open(): void
    {
        [$rootId, $childId] = $this->subject();

        $otherRoot = (int) DB::table('category_parent_child')
            ->where('parent_id', '!=', $rootId)
            ->whereNotIn('parent_id', DB::table('category_parent_child')->where('child_id', $childId)->pluck('parent_id'))
            ->value('parent_id');

        $this->assertGreaterThan(0, $otherRoot, 'no second root to test with');

        $html = $this->actingAs($this->admin())
            ->get(route('admin.category-child-options.bulk.edit', [
                'parent_id' => $otherRoot,
                'child_ids' => [$childId],
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'name="child_ids[]"',
            $html,
            'the screen rendered no child checkboxes at all'
        );

        // At least one child checkbox must be enabled, or the form cannot pass
        // its own validation no matter what the admin does.
        $enabled = preg_match_all('/name="child_ids\[\]"(?:(?!disabled)[^>])*>/', $html);

        $this->assertGreaterThan(0, $enabled, 'every child checkbox is disabled — the form can never be saved');
    }

    /** Unticking everything and saving must CLEAR the child, not 422. */
    public function test_replace_with_nothing_ticked_clears_the_child(): void
    {
        [$rootId, $childId, $optionId] = $this->subject();

        DB::table('category_child_option')->updateOrInsert(
            ['child_id' => $childId, 'category_id' => 0, 'option_id' => $optionId],
            ['reorder' => 1]
        );

        $this->actingAs($this->admin())
            ->post(route('admin.category-child-options.bulk.update'), [
                'parent_id' => $rootId,
                'child_ids' => [$childId],
                'mode' => 'replace',
                // no option_ids at all — the browser sends nothing for an empty
                // set of checkboxes, which is exactly how «uncheck everything»
                // reaches the server.
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(
            0,
            DB::table('category_child_option')->where('child_id', $childId)->count(),
            'unticking everything did not clear the child'
        );
    }

    /**
     * The half the owner asked for by name: «لو قمت بتحدد ابن واحد تظهر كل
     * الخيارات له مجمعة فى كارت استطيع الغاء منها غير المناسب مباشرة».
     *
     * The card is drawn client-side from two payloads the controller ships —
     * what each child carries, and a name for every option id. Neither can be
     * asserted through the DOM here, so this pins the payloads themselves: the
     * card cannot render a name it was never sent.
     */
    public function test_the_screen_ships_what_the_card_needs_to_name_an_option(): void
    {
        [$rootId, $childId, $optionId] = $this->subject();

        DB::table('category_child_option')->updateOrInsert(
            ['child_id' => $childId, 'category_id' => 0, 'option_id' => $optionId],
            ['reorder' => 1]
        );

        $response = $this->actingAs($this->admin())
            ->get(route('admin.category-child-options.bulk.edit', ['parent_id' => $rootId]))
            ->assertOk();

        $optionIndex = $response->viewData('optionIndex');
        $childOptions = $response->viewData('childOptionMap');

        $this->assertArrayHasKey($optionId, $optionIndex, 'the card could not name this option');
        $this->assertNotEmpty($optionIndex[$optionId][0] ?? '', 'the option travelled without a name');

        $this->assertContains(
            $optionId,
            $childOptions['shared'][$childId] ?? [],
            'the child arrived without the option it carries, so the card would open empty'
        );
    }

    /** `child_ids` pre-selects; it must never hide the children it did not name. */
    public function test_picking_children_does_not_hide_the_rest(): void
    {
        [$rootId, $childId] = $this->subject();

        $siblings = DB::table('category_parent_child')->where('parent_id', $rootId)->count();

        if ($siblings < 2) {
            $this->markTestSkipped('this root holds a single child');
        }

        $drawn = $this->actingAs($this->admin())
            ->get(route('admin.category-child-options.bulk.edit', [
                'parent_id' => $rootId,
                'child_ids' => [$childId],
            ]))
            ->assertOk()
            ->viewData('roots')
            ->firstWhere('id', $rootId);

        $this->assertSame(
            $siblings,
            $drawn->children->count(),
            'picking one child hid its siblings — after a save the admin is locked inside his own selection'
        );
    }

    /** Withdrawing ONE option must leave the rest of the child's set alone. */
    public function test_replace_withdraws_only_what_was_untied(): void
    {
        [$rootId, $childId] = $this->subject();

        $optionIds = DB::table('options')->orderBy('id')->limit(3)->pluck('id')
            ->map(fn ($id) => (int) $id)->all();

        $this->assertCount(3, $optionIds);

        foreach ($optionIds as $position => $optionId) {
            DB::table('category_child_option')->updateOrInsert(
                ['child_id' => $childId, 'category_id' => 0, 'option_id' => $optionId],
                ['reorder' => $position]
            );
        }

        // What the seeded screen posts back after the admin clicks × on one chip.
        $kept = array_slice($optionIds, 0, 2);

        $this->actingAs($this->admin())
            ->post(route('admin.category-child-options.bulk.update'), [
                'parent_id' => $rootId,
                'child_ids' => [$childId],
                'option_ids' => $kept,
                'mode' => 'replace',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $now = DB::table('category_child_option')->where('child_id', $childId)
            ->whereIn('category_id', [0, $rootId])
            ->pluck('option_id')->map(fn ($id) => (int) $id)->all();

        foreach ($kept as $optionId) {
            $this->assertContains($optionId, $now, 'a kept option was withdrawn too');
        }

        $this->assertNotContains($optionIds[2], $now, 'the removed option survived');
    }

    /**
     * The guard written after this screen took two whole roots apart.
     *
     * On 2026-08-11 it wrote «أنواع الأبواب والشبابيك» onto 42 of مصانع's 44
     * children and «أنواع الأجهزة الرياضية» onto 69 of شركات's 70, each losing
     * its own trade list in the same write. A save that empties a vocabulary on
     * more than five children now comes back once and names the groups.
     */
    public function test_a_root_wide_withdrawal_stops_to_ask(): void
    {
        $rootId = (int) DB::table('category_parent_child')
            ->select('parent_id', DB::raw('COUNT(*) as n'))
            ->groupBy('parent_id')->orderByDesc('n')->value('parent_id');

        // Children of that root that actually carry something to lose.
        $childIds = DB::table('category_parent_child as pc')
            ->join('category_child_option as co', 'co.child_id', '=', 'pc.child_id')
            ->where('pc.parent_id', $rootId)
            ->whereIn('co.category_id', [0, $rootId])
            ->distinct()->limit(10)->pluck('pc.child_id')->all();

        $this->assertGreaterThan(5, count($childIds), 'need a root wide enough to trip the guard');

        // One option ticked, replace mode: exactly the shape of the accident.
        $optionId = (int) DB::table('category_child_option')
            ->whereIn('child_id', $childIds)->value('option_id');

        $before = DB::table('category_child_option')->whereIn('child_id', $childIds)->count();

        $this->actingAs($this->admin())
            ->post(route('admin.category-child-options.bulk.update'), [
                'parent_id' => $rootId,
                'child_ids' => $childIds,
                'option_ids' => [$optionId],
                'mode' => 'replace',
            ])
            ->assertSessionHas('confirm_wide_withdrawal');

        $this->assertSame(
            $before,
            DB::table('category_child_option')->whereIn('child_id', $childIds)->count(),
            'the guard warned and wrote anyway'
        );

        // Confirmed, the same save goes through — the guard asks once.
        $this->actingAs($this->admin())
            ->post(route('admin.category-child-options.bulk.update'), [
                'parent_id' => $rootId,
                'child_ids' => $childIds,
                'option_ids' => [$optionId],
                'mode' => 'replace',
                'confirm_wide_withdrawal' => 1,
            ])
            ->assertSessionHas('success');

        $this->assertLessThan(
            $before,
            DB::table('category_child_option')->whereIn('child_id', $childIds)->count()
        );
    }

    /**
     * «استبدال بالكامل» with nothing ticked, across a selection, is never what
     * anyone means — so it is refused outright rather than offered a checkbox.
     * On ONE child it stays legal: that is how a child is deliberately emptied.
     */
    public function test_replacing_many_children_with_nothing_is_refused(): void
    {
        $rootId = (int) DB::table('category_parent_child')
            ->select('parent_id', DB::raw('COUNT(*) as n'))
            ->groupBy('parent_id')->orderByDesc('n')->value('parent_id');

        $childIds = DB::table('category_parent_child')->where('parent_id', $rootId)
            ->limit(3)->pluck('child_id')->all();

        $before = DB::table('category_child_option')->whereIn('child_id', $childIds)->count();

        $this->actingAs($this->admin())
            ->post(route('admin.category-child-options.bulk.update'), [
                'parent_id' => $rootId,
                'child_ids' => $childIds,
                'mode' => 'replace',
            ])
            ->assertSessionHas('error');

        $this->assertSame(
            $before,
            DB::table('category_child_option')->whereIn('child_id', $childIds)->count()
        );
    }

    /** Saving with no child picked must say so in Arabic, not in English. */
    public function test_saving_with_no_child_names_the_problem(): void
    {
        [$rootId, , $optionId] = $this->subject();

        $this->actingAs($this->admin())
            ->post(route('admin.category-child-options.bulk.update'), [
                'parent_id' => $rootId,
                'option_ids' => [$optionId],
                'mode' => 'replace',
            ])
            ->assertSessionHasErrors(['child_ids' => __('اختر قسمًا فرعيًا واحدًا على الأقل قبل الحفظ.')]);
    }
}
