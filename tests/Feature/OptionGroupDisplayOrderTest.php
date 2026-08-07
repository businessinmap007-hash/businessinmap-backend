<?php

namespace Tests\Feature;

use App\Models\CategoryChild;
use App\Models\OptionGroup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A customer meets the option groups in one order: what is bought, then what
 * changes its price, then what only describes it.
 *
 * `option_groups.reorder` was assigned when each group was created and says
 * nothing about importance, so the filter list opened on «مرافق الإقامة» — ten
 * facilities — and buried «الغرف», the thing actually being paid for. The role
 * already knows which is which; the sort now follows it, and `reorder` decides
 * only WITHIN a tier, which is the part an admin genuinely curates.
 *
 * @see \App\Models\OptionGroup::ROLE_RANK
 */
class OptionGroupDisplayOrderTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<int,string> role of each group, in the order they come back */
    private function rolesFor(string $childName): array
    {
        $child = CategoryChild::query()->where('name_ar', $childName)->first();

        if (! $child) {
            $this->markTestSkipped("«{$childName}» is gone.");
        }

        return $child->optionGroups()->get(['id', 'price_role'])
            ->map(fn ($group) => (string) $group->price_role)
            ->all();
    }

    /** The rule, on the child the owner described it with. */
    public function test_the_hotel_meets_the_priced_groups_before_the_descriptive_ones(): void
    {
        $names = CategoryChild::query()->where('name_ar', 'فندق')->first()
            ?->optionGroups()->get(['id', 'name_ar'])->pluck('name_ar')->all() ?? [];

        if (empty($names)) {
            $this->markTestSkipped('«فندق» carries no grouped options.');
        }

        $position = array_flip($names);

        foreach ([
            ['الغرف', 'إطلالة الوحدة'],
            ['إطلالة الوحدة', 'تصنيف الإقامة'],
            ['إطلالة الوحدة', 'مرافق الإقامة'],
            // The one the owner named: class before facilities. Both are
            // descriptive, so this is `reorder`'s job, not the role's.
            ['تصنيف الإقامة', 'مرافق الإقامة'],
        ] as [$first, $second]) {
            if (! isset($position[$first], $position[$second])) {
                continue;
            }

            $this->assertLessThan(
                $position[$second],
                $position[$first],
                "«{$first}» must come before «{$second}»"
            );
        }
    }

    /** No child anywhere may show a descriptive group above a priced one. */
    public function test_no_child_shows_a_descriptive_group_before_a_priced_one(): void
    {
        $children = CategoryChild::query()
            ->whereIn('id', DB::table('category_child_option')->distinct()->pluck('child_id'))
            ->get(['id', 'name_ar']);

        $this->assertNotEmpty($children, 'no child carries options, so this proves nothing');

        $checked = 0;

        foreach ($children as $child) {
            $ranks = $child->optionGroups()->get(['id', 'price_role'])
                ->map(fn ($group) => $group->roleRank())
                ->all();

            if (count($ranks) < 2) {
                continue;
            }

            $checked++;

            $sorted = $ranks;
            sort($sorted);

            $this->assertSame($sorted, $ranks, "«{$child->name_ar}» shows its groups out of tier order");
        }

        $this->assertGreaterThan(0, $checked, 'no child carries two groups to compare');
    }

    /** The customer-facing filter list is the one that had to change. */
    public function test_the_attributes_endpoint_returns_the_groups_in_tier_order(): void
    {
        $child = CategoryChild::query()->where('name_ar', 'فندق')->first();

        if (! $child) {
            $this->markTestSkipped('«فندق» is gone.');
        }

        $groups = $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson('/api/v2/discovery/attributes?child_id=' . $child->id)
            ->assertOk()
            ->json('data.groups');

        $this->assertNotEmpty($groups);

        $ranks = collect($groups)
            ->map(fn ($group) => OptionGroup::query()->find($group['id'])?->roleRank() ?? 99)
            ->all();

        $sorted = $ranks;
        sort($sorted);

        $this->assertSame($sorted, $ranks, 'the filter list is not in tier order');

        // The sort keys must not leak into the payload.
        $this->assertArrayNotHasKey('_rank', $groups[0]);
        $this->assertArrayNotHasKey('_reorder', $groups[0]);
    }

    /**
     * The tiers are a rule, not data — a group with a role nobody recognises
     * sorts with the descriptive tail rather than jumping ahead of the priced
     * ones. An unknown role is not a claim to sell anything.
     */
    public function test_an_unrecognised_role_sorts_last(): void
    {
        $this->assertSame(99, (new OptionGroup(['price_role' => 'nonsense']))->roleRank());

        $this->assertSame(
            [0, 1, 2],
            [
                OptionGroup::ROLE_RANK[OptionGroup::ROLE_LINE],
                OptionGroup::ROLE_RANK[OptionGroup::ROLE_MODIFIER],
                OptionGroup::ROLE_RANK[OptionGroup::ROLE_DESCRIPTIVE],
            ]
        );
    }

    /** Re-running the ordering seeder must not shuffle anything. */
    public function test_the_seeder_is_idempotent(): void
    {
        $before = DB::table('option_groups')->orderBy('id')->pluck('reorder', 'id')->all();

        DB::beginTransaction();

        try {
            (new \Database\Seeders\OptionGroupDisplayOrderSeeder)->run();

            $this->assertSame($before, DB::table('option_groups')->orderBy('id')->pluck('reorder', 'id')->all());
        } finally {
            DB::rollBack();
        }
    }

    /**
     * The seeder may only order groups that share a tier. Ordering across tiers
     * is the role's job, and letting `reorder` override it would put a facility
     * above the thing being paid for — the bug this all exists to fix.
     */
    public function test_the_seeder_refuses_to_order_across_tiers(): void
    {
        $ordered = new \ReflectionClassConstant(\Database\Seeders\OptionGroupDisplayOrderSeeder::class, 'ORDERED');

        foreach ($ordered->getValue() as $names) {
            $roles = OptionGroup::query()
                ->whereIn('name_ar', $names)
                ->pluck('price_role')
                ->unique();

            $this->assertLessThanOrEqual(
                1,
                $roles->count(),
                'a list mixes tiers: ' . implode('، ', $names)
            );
        }
    }
}
