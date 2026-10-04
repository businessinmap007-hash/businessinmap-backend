<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\PlatformService;
use App\Models\ServiceOptionGroupPlacement as Placement;
use App\Models\User;
use App\Services\Catalog\ServiceOptionPlacements;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «مجموعة الخيارات الوصفية يكون لها ويدجت بدل أن تكون متفرقة — الخيارات الوصفية التى أحددها
 * أنا يدويا لمرة واحدة ويعمم على الكل» — المالك، 2026-10-04.
 * The descriptive groups every menu carries are chosen ONCE in «أشكال المنيو» and stored as the
 * menu service's all-trades placements, which every trade already inherits. Rolls back.
 */
class CommonDescribingWidgetTest extends TestCase
{
    use DatabaseTransactions;

    private int $menu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->menu = (int) PlatformService::query()->where('key', PlatformService::KEY_MENU)->value('id');
        // A clean slate for the groups under test, so live curation cannot fail the file.
        DB::table('service_option_group_placements')->where('platform_service_id', $this->menu)->where('usage', Placement::USAGE_DESCRIPTIVE)->delete();
    }

    private function admin(): User
    {
        return User::query()->where('type', 'admin')->first() ?: $this->markTestSkipped('No admin account to act as.');
    }

    private function group(string $name): int
    {
        return (int) OptionGroup::query()->where('name_ar', $name)->value('id');
    }

    private function trade(): int
    {
        return (int) DB::table('users')->where('type', 'business')->whereNotNull('category_child_id')->value('category_child_id');
    }

    public function test_the_panel_lists_the_descriptive_groups_to_choose_from(): void
    {
        $group = OptionGroup::query()->where('name_ar', 'أثاث وتشطيب منزلي')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.menu-shapes.index', ['group_id' => $group->id], false))
            ->assertOk()
            ->assertSee('ويدجت الخيارات الوصفية العامة')
            ->assertSee('الدفع والسداد')
            ->assertSee('الاستبدال والإرجاع');
    }

    public function test_saving_makes_the_chosen_groups_apply_to_every_trade(): void
    {
        $pay = $this->group('الدفع والسداد');
        $swap = $this->group('مواصفات المنتج الغذائي');
        $other = $this->group('تجهيزات مساحة العمل');

        $this->actingAs($this->admin())->post(route('admin.menu-shapes.common-describing', [], false), [
            'groups' => [
                $swap => ['enabled' => 1, 'sort_order' => 20, 'show_on_page' => 1, 'display' => 'chips', 'multiple' => 0],
                $pay => ['enabled' => 1, 'sort_order' => 10, 'show_on_page' => 1, 'display' => 'auto', 'multiple' => 1],
                $other => ['sort_order' => 30],
            ],
        ])->assertRedirect();

        $effective = app(ServiceOptionPlacements::class)->for($this->menu, $this->trade(), Placement::USAGE_DESCRIPTIVE);

        $this->assertSame([$pay, $swap], $effective->pluck('option_group_id')->map(fn ($id) => (int) $id)->all(), 'chosen, in the order given; the unticked one is not there');
        $this->assertFalse((bool) $effective->firstWhere('option_group_id', $swap)->multiple);
        $this->assertSame('chips', $effective->firstWhere('option_group_id', $swap)->display);
    }

    public function test_unifying_folds_the_scattered_trade_copies_but_keeps_a_trade_that_hid_the_group(): void
    {
        $pay = $this->group('الدفع والسداد');
        $children = DB::table('users')->where('type', 'business')->whereNotNull('category_child_id')->distinct()->pluck('category_child_id')->take(2)->all();
        $this->assertCount(2, $children);
        [$copy, $hides] = $children;

        $row = fn (int $child, bool $active) => ['platform_service_id' => $this->menu, 'option_group_id' => $pay, 'child_id' => $child, 'item_type_key' => '', 'usage' => Placement::USAGE_DESCRIPTIVE, 'branches_as_sections' => 0, 'is_active' => $active, 'sort_order' => 5, 'show_on_page' => 1, 'display' => 'auto', 'multiple' => 1, 'created_at' => now(), 'updated_at' => now()];
        DB::table('service_option_group_placements')->insert([$row($copy, true), $row($hides, false)]);

        $this->actingAs($this->admin())->post(route('admin.menu-shapes.common-describing', [], false), [
            'unify' => 1,
            'groups' => [$pay => ['enabled' => 1, 'sort_order' => 10, 'show_on_page' => 1, 'display' => 'auto', 'multiple' => 1]],
        ])->assertRedirect();

        $this->assertFalse(DB::table('service_option_group_placements')->where('child_id', $copy)->where('option_group_id', $pay)->where('usage', Placement::USAGE_DESCRIPTIVE)->exists(), 'the redundant copy is gone');
        $this->assertTrue(DB::table('service_option_group_placements')->where('child_id', $hides)->where('option_group_id', $pay)->where('is_active', 0)->exists(), 'a trade that hid it keeps hiding it');

        $service = app(ServiceOptionPlacements::class);
        $this->assertContains($pay, $service->for($this->menu, $copy, Placement::USAGE_DESCRIPTIVE)->pluck('option_group_id')->map(fn ($id) => (int) $id)->all());
        $this->assertNotContains($pay, $service->for($this->menu, $hides, Placement::USAGE_DESCRIPTIVE)->pluck('option_group_id')->map(fn ($id) => (int) $id)->all());
    }
}
