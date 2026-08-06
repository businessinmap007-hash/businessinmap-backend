<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\User;
use App\Services\CategoryChildOptionScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The bulk options screen used to open with every checkbox blank, whatever the
 * children already carried. So it could say what they should ALL get and never
 * what any ONE of them has — and «إضافة» to a child that already held the
 * option looked exactly like «إضافة» to one that did not.
 *
 * Two things fix that: each child names its own set, and each group says how
 * many of its options are ticked. A third thing keeps the screen honest about
 * consequences — the group's price role, because ticking «الغرف» creates a
 * priced booking row and ticking «مرافق الإقامة» only ever narrows a search.
 */
class BulkChildOptionsVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $admin = User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin account to act as.');
        }

        return $admin;
    }

    private function screen(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())
            ->get(route('admin.category-child-options.bulk.edit', [], false))
            ->assertOk();
    }

    /**
     * The map ships in the two halves the table stores, because the union
     * depends on which root tab is open and the tabs switch without a reload.
     */
    public function test_each_child_carries_its_own_registered_set(): void
    {
        $map = $this->screen()->viewData('childOptionMap');

        $this->assertArrayHasKey('shared', $map);
        $this->assertArrayHasKey('scoped', $map);

        $child = DB::table('category_child_option')
            ->where('category_id', CategoryChildOptionScope::ALL_ROOTS)
            ->value('child_id');

        $this->assertNotNull($child, 'no child carries a shared option, so nothing can be shown');
        $this->assertNotEmpty($map['shared'][(int) $child] ?? [], 'a configured child came back empty');
    }

    /**
     * The union the screen takes client-side must be the union the scope
     * service takes server-side, or the panel would show a set nothing else
     * agrees with.
     */
    public function test_the_two_halves_union_to_what_the_scope_service_reports(): void
    {
        $row = DB::table('category_child_option')
            ->where('category_id', '>', 0)
            ->first(['child_id', 'category_id']);

        if (! $row) {
            $this->markTestSkipped('No root-scoped link to check the union against.');
        }

        $childId = (int) $row->child_id;
        $rootId = (int) $row->category_id;

        $map = $this->screen()->viewData('childOptionMap');

        $union = collect($map['shared'][$childId] ?? [])
            ->merge($map['scoped'][$rootId][$childId] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();

        $expected = app(CategoryChildOptionScope::class)->idsFor($childId, $rootId)->sort()->values();

        $this->assertSame($expected->all(), $union->all());
    }

    /**
     * Every option a child carries must be nameable. The picker draws only
     * active groups, so an option whose group was deactivated would be counted
     * and not listed — the panel would disagree with its own badge.
     */
    public function test_every_registered_option_can_be_named(): void
    {
        $response = $this->screen();
        $map = $response->viewData('childOptionMap');
        $index = $response->viewData('optionIndex');

        $referenced = collect($map['shared'])
            ->merge(collect($map['scoped'])->flatMap(fn ($byChild) => $byChild))
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->unique();

        $this->assertNotEmpty($referenced, 'nothing is registered anywhere, so this proves nothing');

        $missing = $referenced->reject(fn ($id) => isset($index[$id]));

        $this->assertSame([], $missing->values()->all(), 'options a child carries that the panel cannot name');
    }

    /** A screen that hides the count is a screen that invites ticking everything. */
    public function test_each_group_shows_how_many_of_it_are_ticked(): void
    {
        $html = $this->screen()->getContent();

        $this->assertStringContainsString('js-group-count', $html);
        $this->assertMatchesRegularExpression(
            '/js-group-count"\s*\n?\s*data-total="\d+"/u',
            $html,
            'the group badge has no total to count against'
        );
    }

    /**
     * The role is what the owner asked to see: «الغرف» is picked by the merchant
     * and becomes a priced booking row; a descriptive group only ever filters a
     * search. They are the same checkbox on this screen and must not read alike.
     */
    public function test_the_screen_says_where_each_group_surfaces(): void
    {
        $response = $this->screen();

        $groups = collect($response->viewData('optionGroups'));

        $this->assertNotEmpty($groups);

        foreach ($groups as $group) {
            $this->assertContains(
                (string) $group->price_role,
                OptionGroup::ROLES,
                "«{$group->name_ar}» reached the screen without a usable price role"
            );
        }

        $html = $response->getContent();

        foreach ([__('سطر مُسعَّر'), __('وصفي')] as $label) {
            $this->assertStringContainsString($label, $html, "the «{$label}» marking is missing");
        }

        // The rooms are the example the owner gave: a line group, never a filter.
        $rooms = $groups->firstWhere('name_ar', 'الغرف');

        if ($rooms) {
            $this->assertSame(OptionGroup::ROLE_LINE, (string) $rooms->price_role);
        }
    }
}
