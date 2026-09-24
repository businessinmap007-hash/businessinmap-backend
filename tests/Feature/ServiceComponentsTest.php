<?php

namespace Tests\Feature;

use App\Models\ServiceOptionGroupPlacement as P;
use App\Models\User;
use App\Services\Catalog\ServiceOptionPlacements;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «تحديد مكونات الخدمة واين تعرض كل مجموعة وكيفية استخدامها مع اى بند» —
 * المالك، 2026-09-24. Rolls back.
 */
class ServiceComponentsTest extends TestCase
{
    use DatabaseTransactions;

    private function ids(): array
    {
        $service = (int) DB::table('platform_services')->where('key', 'retail')->value('id');
        $group = (int) DB::table('option_groups')->where('name_ar', 'حالة المنتج')->value('id');

        return [$service, $group];
    }

    public function test_a_childs_override_beats_the_service_default_for_the_same_group_and_item(): void
    {
        [$service, $group] = $this->ids();

        P::create(['platform_service_id' => $service, 'option_group_id' => $group, 'child_id' => 0,
            'surfaces' => [P::SURFACE_ITEM_FORM, P::SURFACE_SEARCH_FILTER], 'usage' => P::USAGE_DESCRIPTIVE]);
        P::create(['platform_service_id' => $service, 'option_group_id' => $group, 'child_id' => 88,
            'surfaces' => [P::SURFACE_PRICING], 'usage' => P::USAGE_CHANGES_PRICE, 'input_type' => P::INPUT_MULTIPLE]);

        $resolver = app(ServiceOptionPlacements::class);

        $default = $resolver->for($service, 999999);
        $this->assertCount(1, $default);
        $this->assertSame(P::USAGE_DESCRIPTIVE, $default->first()->usage);

        $forChild = $resolver->for($service, 88);
        $this->assertCount(1, $forChild);
        $this->assertSame(P::USAGE_CHANGES_PRICE, $forChild->first()->usage);
        $this->assertSame(P::INPUT_MULTIPLE, $forChild->first()->input_type);
    }

    public function test_surface_and_item_type_filters(): void
    {
        [$service, $group] = $this->ids();

        P::create(['platform_service_id' => $service, 'option_group_id' => $group, 'child_id' => 0,
            'item_type_key' => 'home_appliances', 'surfaces' => [P::SURFACE_SEARCH_FILTER], 'usage' => P::USAGE_FILTER_ONLY]);

        $resolver = app(ServiceOptionPlacements::class);

        $this->assertCount(1, $resolver->for($service, 88, P::SURFACE_SEARCH_FILTER, 'home_appliances'));
        $this->assertCount(0, $resolver->for($service, 88, P::SURFACE_PRICING, 'home_appliances'));
        $this->assertCount(0, $resolver->for($service, 88, P::SURFACE_SEARCH_FILTER, 'appliance_spare_parts'));
    }

    public function test_an_inactive_child_override_hides_the_group_for_that_child_only(): void
    {
        [$service, $group] = $this->ids();

        P::create(['platform_service_id' => $service, 'option_group_id' => $group, 'child_id' => 0,
            'surfaces' => [P::SURFACE_ITEM_FORM], 'usage' => P::USAGE_DESCRIPTIVE]);
        P::create(['platform_service_id' => $service, 'option_group_id' => $group, 'child_id' => 88,
            'surfaces' => [P::SURFACE_ITEM_FORM], 'usage' => P::USAGE_DESCRIPTIVE, 'is_active' => false]);

        $resolver = app(ServiceOptionPlacements::class);

        $this->assertCount(0, $resolver->for($service, 88));
        $this->assertCount(1, $resolver->for($service, 89));
    }

    private function admin(): User
    {
        return User::query()->where('type', 'admin')->first() ?: $this->markTestSkipped('No admin account to act as.');
    }

    /** @return array{0:int,1:int,2:int,3:int} root, child, service, group — a real child with a service and a group */
    private function scope(): array
    {
        $row = DB::table('category_platform_services as cps')
            ->join('category_child_option as cco', function ($j) {
                $j->on('cco.child_id', '=', 'cps.child_id')->whereIn('cco.category_id', [0]);
            })
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->where('cps.is_active', 1)->where('cps.category_id', '>', 0)
            ->first(['cps.category_id as root', 'cps.child_id as child', 'cps.platform_service_id as service', 'o.group_id as grp']);

        return $row ? [(int) $row->root, (int) $row->child, (int) $row->service, (int) $row->grp]
            : $this->markTestSkipped('Needs a child with an active service and a linked option group.');
    }

    public function test_the_admin_screen_lists_the_childs_services_and_saves_for_that_child_only(): void
    {
        [$root, $child, $service, $group] = $this->scope();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.service-components.index', ['root_id' => $root, 'child_id' => $child, 'service_id' => $service], false))
            ->assertOk()->assertSee('name="rows[0][usage]"', false);

        $this->actingAs($admin)->post(route('admin.service-components.save', [], false), [
            'root_id' => $root, 'child_id' => $child, 'service_id' => $service,
            'rows' => [[
                'option_group_id' => $group,
                'surfaces' => [P::SURFACE_ITEM_FORM, P::SURFACE_PRICING],
                'usage' => P::USAGE_CHANGES_PRICE,
                'input_type' => P::INPUT_MULTIPLE,
                'is_required' => 1, 'is_active' => 1,
            ]],
        ])->assertRedirect();

        $row = P::query()->where('platform_service_id', $service)->where('option_group_id', $group)->where('child_id', $child)->first();
        $this->assertNotNull($row);
        $this->assertSame(P::USAGE_CHANGES_PRICE, $row->usage);
        $this->assertSame(0, P::query()->where('child_id', 0)->count(), 'never written service-wide from this screen');

        // Clearing the role removes the row.
        $this->actingAs($admin)->post(route('admin.service-components.save', [], false), [
            'root_id' => $root, 'child_id' => $child, 'service_id' => $service,
            'rows' => [['option_group_id' => $group, 'usage' => '']],
        ])->assertRedirect();

        $this->assertSame(0, P::query()->where('platform_service_id', $service)->where('child_id', $child)->count());
    }

    public function test_a_service_the_child_does_not_offer_is_refused(): void
    {
        [$root, $child, , $group] = $this->scope();
        $offered = DB::table('category_platform_services')->where('category_id', $root)->where('child_id', $child)->where('is_active', 1)->pluck('platform_service_id');
        $foreign = (int) DB::table('platform_services')->whereNotIn('id', $offered)->value('id');

        $this->actingAs($this->admin())->post(route('admin.service-components.save', [], false), [
            'root_id' => $root, 'child_id' => $child, 'service_id' => $foreign,
            'rows' => [['option_group_id' => $group, 'usage' => P::USAGE_DESCRIPTIVE]],
        ])->assertStatus(422);
    }

    public function test_an_unknown_surface_is_refused(): void
    {
        [$root, $child, $service, $group] = $this->scope();

        $this->actingAs($this->admin())->post(route('admin.service-components.save', [], false), [
            'root_id' => $root, 'child_id' => $child, 'service_id' => $service,
            'rows' => [['option_group_id' => $group, 'surfaces' => ['nowhere'], 'usage' => P::USAGE_DESCRIPTIVE]],
        ])->assertSessionHasErrors('rows.0.surfaces.0');
    }
}
