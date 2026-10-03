<?php

namespace Tests\Feature;

use App\Models\MenuDetailProfile;
use App\Models\OptionGroup;
use App\Models\PlatformService;
use App\Models\ServiceOptionGroupPlacement as P;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «نفذ الاقتراح بنقل الحقول الوصفية الى مكونات الخدمة» — المالك، 2026-10-03: the
 * DESCRIPTIVE fields (طراز الأثاث، أنواع الأخشاب) are decided in one place —
 * «مكونات الخدمة», per trade: usage, order, shown on the customer's page, buttons
 * vs dropdown, one choice vs several. «أشكال المنيو» only shows the result.
 * Rolls back.
 */
class MenuShapesDescribingFieldsTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::query()->where('type', 'admin')->first() ?: $this->markTestSkipped('No admin account to act as.');
    }

    /** The furniture trade's menu rows posted the way the screen posts them, with the two describing groups' settings. */
    private function save(array $describing): void
    {
        $menu = (int) PlatformService::query()->where('key', PlatformService::KEY_MENU)->value('id');
        $line = OptionGroup::query()->where('name_ar', 'أثاث وتشطيب منزلي')->firstOrFail();
        $style = OptionGroup::query()->where('name_ar', 'طراز الأثاث')->firstOrFail();
        $wood = OptionGroup::query()->where('name_ar', 'أنواع الأخشاب')->firstOrFail();

        $rows = [
            ['option_group_id' => $line->id, 'usage' => 'section', 'branches_as_sections' => 1, 'is_active' => 1],
            ['option_group_id' => $style->id, 'usage' => 'descriptive', 'is_active' => 1] + ($describing['style'] ?? []),
            ['option_group_id' => $wood->id, 'usage' => 'descriptive', 'is_active' => 1] + ($describing['wood'] ?? []),
        ];

        $this->actingAs($this->admin())->post(route('admin.service-components.save', [], false), [
            'root_id' => 23, 'child_id' => 116, 'service_id' => $menu, 'rows' => $rows,
        ])->assertRedirect();
    }

    private function placement(int $group): P
    {
        $menu = (int) PlatformService::query()->where('key', PlatformService::KEY_MENU)->value('id');

        return P::query()->where('platform_service_id', $menu)->where('child_id', 116)->where('option_group_id', $group)->where('item_type_key', '')->firstOrFail();
    }

    public function test_the_trades_settings_are_saved_on_the_placement_and_reach_the_merchants_form(): void
    {
        $style = OptionGroup::query()->where('name_ar', 'طراز الأثاث')->firstOrFail();
        $wood = OptionGroup::query()->where('name_ar', 'أنواع الأخشاب')->firstOrFail();

        $this->save([
            'style' => ['sort_order' => 20, 'show_on_page' => 0, 'display' => 'chips', 'multiple' => 0],
            'wood' => ['sort_order' => 10, 'show_on_page' => 1, 'display' => 'dropdown', 'multiple' => 1],
        ]);

        $this->assertFalse($this->placement($style->id)->show_on_page);
        $this->assertSame('chips', $this->placement($style->id)->display);
        $this->assertFalse($this->placement($style->id)->multiple, 'the style allows one choice');
        $this->assertSame(10, $this->placement($wood->id)->sort_order);
        $this->assertSame('dropdown', $this->placement($wood->id)->display);

        // The merchant's form hears it per group, with the order.
        $shop = User::query()->where('type', 'business')->where('category_child_id', 116)->orderBy('id')->firstOrFail();
        foreach ([$style->id, $wood->id] as $id) {
            DB::table('option_user')->updateOrInsert(['user_id' => $shop->id, 'option_id' => (int) DB::table('options')->where('group_id', $id)->value('id')], []);
        }
        Sanctum::actingAs($shop);
        $modifiers = collect($this->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data.modifiers'))->keyBy('group_id');

        $this->assertSame('chips', $modifiers[$style->id]['display']);
        $this->assertFalse($modifiers[$style->id]['multiple']);
        $this->assertSame('dropdown', $modifiers[$wood->id]['display']);
        $this->assertTrue($modifiers[$wood->id]['multiple']);
        $this->assertSame(20, $modifiers[$style->id]['descriptive_sort']);
        $this->assertLessThan($modifiers[$style->id]['descriptive_sort'], $modifiers[$wood->id]['descriptive_sort'], 'wood is placed first');
    }

    public function test_a_post_that_says_nothing_keeps_shown_auto_and_several(): void
    {
        $style = OptionGroup::query()->where('name_ar', 'طراز الأثاث')->firstOrFail();

        $this->save([]);

        $row = $this->placement($style->id);
        $this->assertTrue($row->show_on_page);
        $this->assertSame('auto', $row->display);
        $this->assertTrue($row->multiple);
    }

    public function test_the_shapes_screen_shows_what_the_components_screen_decided_and_links_to_it(): void
    {
        $kind = MenuDetailProfile::query()->firstOrCreate(
            ['code' => 'desc-test'],
            ['name_ar' => 'نوع الوصف للاختبار', 'uses_catalog' => false, 'sort_order' => 902, 'is_active' => true]
        );
        $line = OptionGroup::query()->where('name_ar', 'أثاث وتشطيب منزلي')->firstOrFail();
        $line->update(['menu_detail_profile_id' => $kind->id]);
        $this->save(['style' => ['display' => 'chips', 'multiple' => 0], 'wood' => ['display' => 'dropdown']]);

        $this->actingAs($this->admin())
            ->get(route('admin.menu-shapes.index', ['group_id' => $line->id, 'preview' => $kind->id], false))
            ->assertOk()
            ->assertSee('حقول وصفية')
            ->assertSee('طراز الأثاث')
            ->assertSee('أنواع الأخشاب')
            ->assertSee('فتح مكونات الخدمة')
            ->assertDontSee('name="describing[', false);   // nothing to edit here any more
    }

    public function test_one_form_one_save_carries_the_catalog_fields_and_the_catalog_switch(): void
    {
        $kind = MenuDetailProfile::query()->firstOrCreate(
            ['code' => 'desc-test'],
            ['name_ar' => 'نوع الوصف للاختبار', 'uses_catalog' => false, 'sort_order' => 902, 'is_active' => true]
        );
        $line = OptionGroup::query()->where('name_ar', 'أثاث وتشطيب منزلي')->firstOrFail();
        $line->update(['menu_detail_profile_id' => $kind->id]);
        $admin = $this->admin();

        $page = $this->actingAs($admin)
            ->get(route('admin.menu-shapes.index', ['group_id' => $line->id, 'preview' => $kind->id], false))
            ->assertOk()->getContent();
        $this->assertSame(0, substr_count($page, 'حفظ الحقول</button>'), 'no second save button beside the first');

        $this->actingAs($admin)->post(route('admin.menu-shapes.profiles.fields', $kind, false), ['group_id' => $line->id, 'uses_catalog_form' => 1, 'uses_catalog' => 1])->assertRedirect();
        $this->assertTrue($kind->fresh()->uses_catalog);

        $this->actingAs($admin)->post(route('admin.menu-shapes.profiles.fields', $kind, false), ['group_id' => $line->id, 'uses_catalog_form' => 1])->assertRedirect();
        $this->assertFalse($kind->fresh()->uses_catalog);

        $kind->update(['uses_catalog' => true]);
        $this->actingAs($admin)->post(route('admin.menu-shapes.profiles.fields', $kind, false), ['group_id' => $line->id])->assertRedirect();
        $this->assertTrue($kind->fresh()->uses_catalog, 'an older caller leaves the switch alone');
    }
}
