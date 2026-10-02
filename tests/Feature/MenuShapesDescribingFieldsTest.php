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
 * «اضف الحقول الوصفية الى حقول المنيو فى menu-shapes للاختيار منها» — المالك،
 * 2026-10-02: the descriptive option groups (طراز الأثاث، نظام التصنيف، أنواع
 * الأخشاب) are chosen per kind beside its catalog fields, drawn in the preview as
 * dropdowns, and handed to the app in the vocabulary. Rolls back.
 */
class MenuShapesDescribingFieldsTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::query()->where('type', 'admin')->first() ?: $this->markTestSkipped('No admin account to act as.');
    }

    /** A furniture kind on the furniture group, with two descriptive groups the trade made descriptive. */
    private function setUpKind(): array
    {
        $kind = MenuDetailProfile::query()->firstOrCreate(
            ['code' => 'desc-test'],
            ['name_ar' => 'نوع الوصف للاختبار', 'uses_catalog' => false, 'sort_order' => 902, 'is_active' => true]
        );
        $line = OptionGroup::query()->where('name_ar', 'أثاث وتشطيب منزلي')->firstOrFail();
        $line->update(['menu_detail_profile_id' => $kind->id]);
        $menu = (int) PlatformService::query()->where('key', PlatformService::KEY_MENU)->value('id');

        $style = OptionGroup::query()->where('name_ar', 'طراز الأثاث')->firstOrFail();
        $wood = OptionGroup::query()->where('name_ar', 'أنواع الأخشاب')->firstOrFail();
        foreach ([$style, $wood] as $g) {
            P::query()->updateOrCreate(
                ['platform_service_id' => $menu, 'child_id' => 116, 'option_group_id' => $g->id, 'item_type_key' => ''],
                ['usage' => P::USAGE_DESCRIPTIVE, 'is_active' => true]
            );
        }
        DB::table('menu_detail_profile_option_groups')->where('menu_detail_profile_id', $kind->id)->delete();

        return [$kind, $line, $style, $wood];
    }

    public function test_the_editor_lists_the_descriptive_groups_to_choose_from(): void
    {
        [$kind, $line, $style, $wood] = $this->setUpKind();

        $this->actingAs($this->admin())
            ->get(route('admin.menu-shapes.index', ['group_id' => $line->id, 'preview' => $kind->id], false))
            ->assertOk()
            ->assertSee('حقول وصفية')
            ->assertSee('طراز الأثاث')
            ->assertSee('أنواع الأخشاب');
    }

    public function test_the_chosen_groups_are_saved_in_order_drawn_in_the_preview_and_sent_to_the_app(): void
    {
        [$kind, $line, $style, $wood] = $this->setUpKind();

        // Wood first, then style — the admin's order, not the platform's.
        $this->actingAs($this->admin())->post(route('admin.menu-shapes.profiles.fields', $kind, false), [
            'group_id' => $line->id,
            'describing' => [
                $wood->id => ['enabled' => 1, 'sort_order' => 10],
                $style->id => ['enabled' => 1, 'sort_order' => 20],
            ],
        ])->assertRedirect();

        $this->assertSame([$wood->id, $style->id], MenuDetailProfile::describingGroupIds([$kind->id])[$kind->id]);

        $page = $this->actingAs($this->admin())
            ->get(route('admin.menu-shapes.index', ['group_id' => $line->id, 'preview' => $kind->id], false))
            ->assertOk()->getContent();
        $this->assertLessThan(strpos($page, 'ms-hint">طراز الأثاث'), strpos($page, 'ms-hint">أنواع الأخشاب'), 'the preview follows the chosen order');

        $shop = User::query()->where('type', 'business')->where('category_child_id', 116)->orderBy('id')->firstOrFail();
        Sanctum::actingAs($shop);
        $lines = collect($this->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data.lines'));
        $this->assertSame([$wood->id, $style->id], $lines->firstWhere('group_id', $line->id)['detail_profile']['descriptive_group_ids']);
    }

    public function test_unticking_everything_clears_the_choice(): void
    {
        [$kind, $line, , $wood] = $this->setUpKind();
        DB::table('menu_detail_profile_option_groups')->insert(['menu_detail_profile_id' => $kind->id, 'option_group_id' => $wood->id, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->admin())->post(route('admin.menu-shapes.profiles.fields', $kind, false), ['group_id' => $line->id])->assertRedirect();

        $this->assertSame([], MenuDetailProfile::describingGroupIds([$kind->id]));
    }

    public function test_one_form_one_save_carries_the_fields_the_descriptive_groups_and_the_catalog_switch(): void
    {
        [$kind, $line, , $wood] = $this->setUpKind();
        $admin = $this->admin();

        $page = $this->actingAs($admin)
            ->get(route('admin.menu-shapes.index', ['group_id' => $line->id, 'preview' => $kind->id], false))
            ->assertOk()->getContent();
        $this->assertSame(0, substr_count($page, 'حفظ الحقول</button>'), 'no second save button beside the first');

        // Ticked: the kind uses a catalog; the form carries the marker either way.
        $this->actingAs($admin)->post(route('admin.menu-shapes.profiles.fields', $kind, false), [
            'group_id' => $line->id, 'uses_catalog_form' => 1, 'uses_catalog' => 1,
            'describing' => [$wood->id => ['enabled' => 1, 'sort_order' => 10]],
        ])->assertRedirect();
        $this->assertTrue($kind->fresh()->uses_catalog);
        $this->assertSame([$wood->id], MenuDetailProfile::describingGroupIds([$kind->id])[$kind->id]);

        // Unticked: the same save turns it off.
        $this->actingAs($admin)->post(route('admin.menu-shapes.profiles.fields', $kind, false), [
            'group_id' => $line->id, 'uses_catalog_form' => 1,
        ])->assertRedirect();
        $this->assertFalse($kind->fresh()->uses_catalog);

        // A post without the marker (an older caller) leaves the switch alone.
        $kind->update(['uses_catalog' => true]);
        $this->actingAs($admin)->post(route('admin.menu-shapes.profiles.fields', $kind, false), ['group_id' => $line->id])->assertRedirect();
        $this->assertTrue($kind->fresh()->uses_catalog);
    }
}
