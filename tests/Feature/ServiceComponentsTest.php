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

    public function test_the_admin_screen_renders_and_saves_a_placement(): void
    {
        [$service, $group] = $this->ids();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.service-components.index', ['service_id' => $service], false))
            ->assertOk();

        $this->actingAs($admin)->post(route('admin.service-components.save', [], false), [
            'service_id' => $service,
            'child_id' => 0,
            'rows' => [[
                'option_group_id' => $group,
                'item_type_key' => '',
                'surfaces' => [P::SURFACE_ITEM_FORM, P::SURFACE_SEARCH_FILTER],
                'usage' => P::USAGE_DESCRIPTIVE,
                'input_type' => P::INPUT_SINGLE,
                'is_required' => 1,
                'is_active' => 1,
            ]],
        ])->assertRedirect();

        $row = P::query()->where('platform_service_id', $service)->where('option_group_id', $group)->where('child_id', 0)->first();
        $this->assertNotNull($row);
        $this->assertSame([P::SURFACE_ITEM_FORM, P::SURFACE_SEARCH_FILTER], $row->surfaces);
        $this->assertTrue($row->is_required);

        // Saving with the row's usage cleared removes it (nothing configured).
        $this->actingAs($admin)->post(route('admin.service-components.save', [], false), [
            'service_id' => $service, 'child_id' => 0,
            'rows' => [['option_group_id' => $group, 'item_type_key' => '', 'usage' => '']],
        ])->assertRedirect();

        $this->assertSame(0, P::query()->where('platform_service_id', $service)->where('child_id', 0)->count());
    }

    public function test_an_unknown_item_type_or_surface_is_refused(): void
    {
        [$service, $group] = $this->ids();

        $this->actingAs($this->admin())->post(route('admin.service-components.save', [], false), [
            'service_id' => $service, 'child_id' => 0,
            'rows' => [['option_group_id' => $group, 'surfaces' => ['nowhere'], 'usage' => P::USAGE_DESCRIPTIVE]],
        ])->assertSessionHasErrors('rows.0.surfaces.0');
    }
}
