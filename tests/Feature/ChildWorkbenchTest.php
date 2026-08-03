<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The one screen that shows a child's options and services together.
 *
 * The two axes were only ever editable apart, which is how a child ended up
 * with a live service allowing zero item types and nobody noticed. Rolls back.
 *
 * @see \App\Http\Controllers\AdminV2\ChildWorkbenchController
 */
class ChildWorkbenchTest extends TestCase
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

    /** @return array{0:int,1:int} a (root, child) pair that really exists */
    private function pair(): array
    {
        $row = DB::table('category_parent_child as pc')
            ->join('categories as r', 'r.id', '=', 'pc.parent_id')
            ->where('r.slug', 'tourist-hotels')
            ->first(['pc.parent_id', 'pc.child_id']);

        if (! $row) {
            $this->markTestSkipped('No hospitality taxonomy in this database.');
        }

        return [(int) $row->parent_id, (int) $row->child_id];
    }

    public function test_the_page_opens_with_nothing_selected(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.child-workbench.index', [], false))
            ->assertOk()
            ->assertSee('طاولة عمل الابن', false)
            ->assertSee('اختر أبًا ثم ابنًا', false);
    }

    public function test_choosing_a_child_shows_both_axes(): void
    {
        [$rootId, $childId] = $this->pair();

        $this->actingAs($this->admin())
            ->get(route('admin.child-workbench.index', ['root_id' => $rootId, 'child_id' => $childId], false))
            ->assertOk()
            ->assertSee('الخيارات', false)
            ->assertSee('الخدمات', false)
            // the option groups the hotels remodel created must be reachable here
            ->assertSee('مرافق الإقامة', false);
    }

    /** Every root must render, not just the one with the richest taxonomy. */
    public function test_each_root_renders_its_first_child(): void
    {
        $pairs = DB::table('category_parent_child as pc')
            ->join('categories as r', 'r.id', '=', 'pc.parent_id')
            ->select('pc.parent_id', DB::raw('min(pc.child_id) as child_id'))
            ->groupBy('pc.parent_id')
            ->get();

        $admin = $this->admin();

        foreach ($pairs as $pair) {
            $this->actingAs($admin)
                ->get(route('admin.child-workbench.index', [
                    'root_id' => $pair->parent_id,
                    'child_id' => $pair->child_id,
                ], false))
                ->assertOk();
        }

        $this->assertGreaterThan(1, $pairs->count());
    }

    /** A child id left over from another root must not survive the switch. */
    public function test_a_child_from_another_root_is_dropped(): void
    {
        [$rootId, $childId] = $this->pair();

        $otherRoot = DB::table('categories')->where('slug', 'professions')->value('id');

        if (! $otherRoot) {
            $this->markTestSkipped('The professions root is absent.');
        }

        $this->actingAs($this->admin())
            ->get(route('admin.child-workbench.index', ['root_id' => $otherRoot, 'child_id' => $childId], false))
            ->assertOk()
            ->assertSee('اختر أبًا ثم ابنًا', false);
    }

    public function test_saving_options_adds_and_removes(): void
    {
        [$rootId, $childId] = $this->pair();

        $spare = DB::table('options')
            ->whereNotIn('id', DB::table('category_child_option')->where('child_id', $childId)->pluck('option_id'))
            ->value('id');

        $keep = DB::table('category_child_option')->where('child_id', $childId)->value('option_id');

        // options a merchant under this child already ticked survive regardless
        $locked = DB::table('option_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->where('u.category_child_id', $childId)
            ->pluck('ou.option_id')
            ->unique()
            ->diff([$keep, $spare]);

        $this->actingAs($this->admin())
            ->post(route('admin.child-workbench.options', [], false), [
                'root_id' => $rootId,
                'child_id' => $childId,
                'option_ids' => [$keep, $spare],
            ])
            ->assertRedirect();

        $now = DB::table('category_child_option')->where('child_id', $childId)->pluck('option_id');

        $this->assertContains((int) $spare, $now->map(fn ($id) => (int) $id)->all(), 'a ticked option must be added');
        $this->assertSame(2 + $locked->count(), $now->count(), 'unticked options must be withdrawn');
    }

    /** A merchant's own answer outranks the catalogue. */
    public function test_an_option_a_merchant_already_chose_cannot_be_withdrawn(): void
    {
        $pick = DB::table('option_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->join('category_child_option as co', function ($j) {
                $j->on('co.child_id', '=', 'u.category_child_id')->on('co.option_id', '=', 'ou.option_id');
            })
            ->first(['u.category_child_id as child_id', 'u.category_id as root_id', 'ou.option_id']);

        if (! $pick) {
            $this->markTestSkipped('No merchant has chosen an option yet.');
        }

        // submit an EMPTY list: everything would go, including the chosen one
        $this->actingAs($this->admin())
            ->post(route('admin.child-workbench.options', [], false), [
                'root_id' => $pick->root_id,
                'child_id' => $pick->child_id,
                'option_ids' => [],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('category_child_option', [
            'child_id' => $pick->child_id,
            'option_id' => $pick->option_id,
        ]);
    }

    /** Saving the services panel must not wipe config keys it never shows. */
    public function test_saving_services_keeps_the_keys_the_screen_does_not_show(): void
    {
        $row = DB::table('category_service_configs as c')
            ->join('platform_services as s', 's.id', '=', 'c.platform_service_id')
            ->where('s.key', 'delivery')
            ->where('s.is_active', 1)
            ->whereNotNull('c.config')
            ->where('c.config', 'like', '%delivery_type%')
            ->first(['c.id', 'c.category_id', 'c.child_id', 'c.platform_service_id', 'c.config']);

        if (! $row) {
            $this->markTestSkipped('No delivery config carrying its own keys.');
        }

        $before = json_decode($row->config, true);

        $this->actingAs($this->admin())
            ->post(route('admin.child-workbench.services', [], false), [
                'root_id' => $row->category_id,
                'child_id' => $row->child_id,
                'services' => [
                    $row->platform_service_id => [
                        'enabled' => '1',
                        'item_types' => $before['allowed_item_types'] ?? [],
                    ],
                ],
            ])
            ->assertRedirect();

        $after = json_decode(DB::table('category_service_configs')->where('id', $row->id)->value('config'), true);

        $this->assertSame(
            $before['delivery_type'] ?? null,
            $after['delivery_type'] ?? null,
            'a key the screen never renders must survive the save'
        );
    }
}
