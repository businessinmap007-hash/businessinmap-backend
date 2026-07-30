<?php

namespace Tests\Feature;

use App\Models\LabList;
use App\Models\LabListItem;
use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * The two-column transfer builders:
 *  - services: sync a lab list's item-type items in one batch (other sources untouched)
 *  - options : sync a category child's options into category_child_option_new
 */
class TaxonomyLabBuildersTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $admin = User::query()->orderBy('id')->firstOrFail();
        Bouncer::allow($admin)->to(AdminAbility::ACCESS);
        Bouncer::allow($admin)->to(AdminAbility::CATALOG);
        Bouncer::refresh();

        return $admin;
    }

    public function test_service_sync_replaces_item_types_but_leaves_other_sources(): void
    {
        $admin = $this->admin();
        $list = LabList::create(['name_ar' => 'فرع اختبار', 'sort_order' => 90]);

        // Seed one item-type item and one option item.
        $type1 = (int) DB::table('platform_service_item_types_new')->orderBy('id')->value('id');
        $optId = (int) DB::table('options_new')->orderBy('id')->value('id');
        LabListItem::create(['list_id' => $list->id, 'source' => 'item_type', 'source_id' => $type1]);
        LabListItem::create(['list_id' => $list->id, 'source' => 'option', 'source_id' => $optId]);

        // Sync to a DIFFERENT pair of item types.
        $newTypes = DB::table('platform_service_item_types_new')->where('id', '!=', $type1)
            ->orderBy('id')->limit(2)->pluck('id')->map(fn ($v) => (int) $v)->all();

        $this->actingAs($admin)->postJson(
            route('admin.taxonomy-lab.lists.items.sync', $list->id),
            ['source' => 'item_type', 'ids' => $newTypes]
        )->assertOk()->assertJsonPath('ok', true)->assertJsonPath('count', 2);

        $itemTypeIds = $list->items()->where('source', 'item_type')->pluck('source_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
        sort($newTypes);
        $this->assertSame($newTypes, $itemTypeIds, 'old item type removed, new ones added');
        $this->assertNotContains($type1, $itemTypeIds);

        // The option item must survive the item-type sync.
        $this->assertTrue($list->items()->where('source', 'option')->where('source_id', $optId)->exists());
    }

    public function test_options_builder_syncs_child_options(): void
    {
        $admin = $this->admin();
        $child = (int) DB::table('category_children_master')->orderBy('id')->value('id');
        DB::table('category_child_option_new')->where('child_id', $child)->delete();

        $opts = DB::table('options_new')->orderBy('id')->limit(3)->pluck('id')->map(fn ($v) => (int) $v)->all();

        $this->actingAs($admin)->postJson(
            route('admin.taxonomy-lab.options.save', $child),
            ['option_ids' => $opts]
        )->assertOk()->assertJsonPath('ok', true)->assertJsonPath('count', 3);

        $stored = DB::table('category_child_option_new')->where('child_id', $child)->pluck('option_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
        sort($opts);
        $this->assertSame($opts, $stored);

        // Re-sync to a subset removes the dropped one (batch replace semantics).
        $subset = array_slice($opts, 0, 1);
        $this->actingAs($admin)->postJson(
            route('admin.taxonomy-lab.options.save', $child),
            ['option_ids' => $subset]
        )->assertOk();
        $this->assertSame(1, DB::table('category_child_option_new')->where('child_id', $child)->count());
    }

    public function test_options_builder_pages_render(): void
    {
        $admin = $this->admin();
        $child = DB::table('category_children_master')->orderBy('id')->first();

        $this->actingAs($admin)->get(route('admin.taxonomy-lab.options.index'))->assertOk();

        $res = $this->actingAs($admin)->get(route('admin.taxonomy-lab.options.child', $child->id))->assertOk();
        $this->assertNotEmpty($res->viewData('all'));
    }

    public function test_service_transfer_view_exposes_all_types_and_selection(): void
    {
        $admin = $this->admin();
        $list = LabList::where('key', 'restaurant')->first();
        if (! $list) {
            $this->markTestSkipped('Run taxonomy-lab:build-lists first.');
        }

        $res = $this->actingAs($admin)->get(route('admin.taxonomy-lab.lists.show', $list->id))->assertOk();
        $this->assertGreaterThan(50, count($res->viewData('allTypes')), 'source column has all item types');
        $this->assertNotEmpty($res->viewData('selectedTypes'), 'the restaurant branch already has services selected');
    }
}
