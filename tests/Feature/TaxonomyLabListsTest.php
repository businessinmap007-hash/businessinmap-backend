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
 * The Taxonomy Lab hierarchical "lists" builder: unified sections whose items
 * come from options_new OR platform_service_item_types_new, nesting one level
 * deep (سيارات → ماركات سيارات/موتوسيكلات).
 */
class TaxonomyLabListsTest extends TestCase
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

    public function test_seeded_cars_list_splits_into_car_and_moto_brand_sublists(): void
    {
        $cars = LabList::where('key', 'cars')->first();
        if (! $cars) {
            $this->markTestSkipped('Run taxonomy-lab:build-lists first.');
        }

        $childKeys = $cars->children->pluck('key')->all();
        $this->assertContains('cars.car_brands', $childKeys);
        $this->assertContains('cars.moto_brands', $childKeys);

        // Brands are options; both sub-lists are non-empty.
        $car = LabList::where('key', 'cars.car_brands')->first();
        $moto = LabList::where('key', 'cars.moto_brands')->first();
        $this->assertGreaterThan(0, $car->items()->count());
        $this->assertGreaterThan(0, $moto->items()->count());

        // The only brands allowed in BOTH lists are the dual makers (BMW/Honda/
        // Suzuki) — everything else is exclusive to one side.
        $overlap = array_values(array_intersect(
            $car->items()->pluck('source_id')->all(),
            $moto->items()->pluck('source_id')->all()
        ));
        sort($overlap);
        $this->assertSame([44, 185, 351], $overlap, 'only dual makers may appear in both sub-lists');
        $this->assertSame(['option'], $car->items()->distinct()->pluck('source')->all());
    }

    public function test_seeded_health_list_holds_service_item_types_and_specialties(): void
    {
        $health = LabList::where('key', 'health')->first();
        if (! $health) {
            $this->markTestSkipped('Run taxonomy-lab:build-lists first.');
        }

        // Health now unifies two sources: the service item types AND the medical
        // specialties (category children). No options.
        $sources = $health->items()->distinct()->pluck('source')->sort()->values()->all();
        $this->assertSame(['category_child', 'item_type'], $sources);

        // The specialties come from the «الصحة» category children (parent 20).
        $specialtyItems = $health->items()->where('source', LabListItem::SOURCE_CATEGORY_CHILD)->get();
        $expected = DB::table('category_parent_child')->where('parent_id', 20)->count();
        $this->assertSame($expected, $specialtyItems->count());

        // …and they resolve to real names, not raw ids.
        $names = LabListItem::resolveNames($specialtyItems);
        $this->assertNotEmpty($names);
        $this->assertNotContains('', array_map('strval', $names));
    }

    public function test_drilldown_page_shows_sublists_and_resolved_item_names(): void
    {
        $cars = LabList::where('key', 'cars')->first();
        if (! $cars) {
            $this->markTestSkipped('Run taxonomy-lab:build-lists first.');
        }

        $res = $this->actingAs($this->admin())
            ->get(route('admin.taxonomy-lab.lists.show', $cars->id))
            ->assertOk();

        $subLists = collect($res->viewData('subLists'));
        $this->assertGreaterThanOrEqual(2, $subLists->count());
        // Names are resolved from the source table, not raw ids.
        $someItems = collect($subLists->firstWhere('name', 'ماركات سيارات')['items'] ?? []);
        $this->assertNotEmpty($someItems);
        $this->assertNotEmpty($someItems->first()['name']);
    }

    public function test_add_and_remove_item_against_a_fresh_list(): void
    {
        $admin = $this->admin();
        $list = LabList::create(['name_ar' => 'اختبار', 'sort_order' => 99]);

        $optionId = (int) DB::table('options_new')->value('id');

        $add = $this->actingAs($admin)->postJson(
            route('admin.taxonomy-lab.lists.items.add', $list->id),
            ['source' => LabListItem::SOURCE_OPTION, 'source_id' => $optionId]
        )->assertOk()->assertJsonPath('ok', true);

        $itemId = $add->json('item.id');
        $this->assertDatabaseHas('lab_list_items', ['id' => $itemId, 'list_id' => $list->id]);

        // Idempotent: adding the same atom again returns the same row.
        $this->actingAs($admin)->postJson(
            route('admin.taxonomy-lab.lists.items.add', $list->id),
            ['source' => LabListItem::SOURCE_OPTION, 'source_id' => $optionId]
        )->assertOk();
        $this->assertSame(1, $list->items()->count());

        $this->actingAs($admin)->deleteJson(
            route('admin.taxonomy-lab.lists.items.remove', [$list->id, $itemId])
        )->assertOk();
        $this->assertSame(0, $list->items()->count());
    }

    public function test_a_category_child_specialty_can_be_added_via_the_controller(): void
    {
        $admin = $this->admin();
        $list = LabList::create(['name_ar' => 'اختبار تخصص', 'sort_order' => 97]);

        $childId = (int) DB::table('category_parent_child')->where('parent_id', 20)->value('child_id');

        $add = $this->actingAs($admin)->postJson(
            route('admin.taxonomy-lab.lists.items.add', $list->id),
            ['source' => LabListItem::SOURCE_CATEGORY_CHILD, 'source_id' => $childId]
        )->assertOk()->assertJsonPath('ok', true);

        $this->assertSame('category_child', $add->json('item.source'));
        $this->assertNotEmpty($add->json('item.name'));
        $this->assertSame('تخصص', $add->json('item.source_label'));

        // The pool surfaces category children as a third source too.
        $pool = $this->actingAs($admin)
            ->getJson(route('admin.taxonomy-lab.lists.pool', $list->id))
            ->assertOk();
        $this->assertContains('category_child', collect($pool->json('results'))->pluck('source')->unique()->all());
    }

    public function test_pool_excludes_atoms_already_in_the_list(): void
    {
        $admin = $this->admin();
        $list = LabList::create(['name_ar' => 'اختبار pool', 'sort_order' => 98]);
        $option = DB::table('options_new')->whereNotNull('name_ar')->where('name_ar', '!=', '')->orderBy('id')->first();
        $optionId = (int) $option->id;

        LabListItem::create(['list_id' => $list->id, 'source' => 'option', 'source_id' => $optionId]);

        // Excluded with no query…
        $res = $this->actingAs($admin)
            ->getJson(route('admin.taxonomy-lab.lists.pool', $list->id))
            ->assertOk();
        $optionHits = collect($res->json('results'))->where('source', 'option')->pluck('source_id')->all();
        $this->assertNotContains($optionId, $optionHits, 'an already-added atom must not appear in the pool');

        // …and still excluded when its own name is the search term (OR-precedence guard).
        $res2 = $this->actingAs($admin)
            ->getJson(route('admin.taxonomy-lab.lists.pool', $list->id) . '?q=' . urlencode($option->name_ar))
            ->assertOk();
        $optionHits2 = collect($res2->json('results'))->where('source', 'option')->pluck('source_id')->all();
        $this->assertNotContains($optionId, $optionHits2, 'a taken atom must stay excluded even when searched by name');
    }
}
