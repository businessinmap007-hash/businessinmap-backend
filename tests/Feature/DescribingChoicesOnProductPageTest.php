<?php

namespace Tests\Feature;

use App\Models\MenuDetailProfile;
use App\Models\OptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «قمت باضافة حقول وصفية فى اشكال المنيو ولم تعرض فى صفحة المنتج للعميل» —
 * المالك، 2026-10-03: what the merchant chose from the describing groups
 * (طراز الأثاث: مودرن، أنواع الأخشاب: زان) is a row of the product page's
 * «المواصفات». Rolls back.
 */
class DescribingChoicesOnProductPageTest extends TestCase
{
    use DatabaseTransactions;

    private function option(string $group, string $name): int
    {
        return (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $group)->where('o.name_ar', $name)->value('o.id');
    }

    /** A furniture factory with a bedroom that is modern and beech; returns [item id, kind, wood group id]. */
    private function bedroom(): array
    {
        $shop = User::query()->where('type', 'business')->where('category_child_id', 116)->where('category_id', 23)->orderBy('id')->firstOrFail();
        $bedroom = $this->option('أثاث وتشطيب منزلي', 'غرفة نوم');
        $modern = $this->option('طراز الأثاث', 'مودرن');
        $beech = $this->option('أنواع الأخشاب', 'زان');
        foreach ([$bedroom, $modern, $beech] as $id) {
            DB::table('option_user')->updateOrInsert(['user_id' => $shop->id, 'option_id' => $id], []);
        }
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');
        foreach (['أنواع الأخشاب', 'طراز الأثاث'] as $group) {
            DB::table('service_option_group_placements')->updateOrInsert(
                ['platform_service_id' => $menu, 'option_group_id' => (int) DB::table('option_groups')->where('name_ar', $group)->value('id'), 'child_id' => 116, 'item_type_key' => ''],
                ['usage' => 'descriptive', 'branches_as_sections' => 0, 'is_active' => 1, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        Sanctum::actingAs($shop);
        $id = (int) $this->postJson('/api/v2/business/menu/items', [
            'name_ar' => 'غرفة نوم للاختبار', 'base_price' => 40000,
            'line_option_id' => $bedroom, 'modifier_option_ids' => [$modern, $beech],
        ])->assertCreated()->json('data.id');

        $kind = MenuDetailProfile::query()->firstOrCreate(
            ['code' => 'dc-test'],
            ['name_ar' => 'نوع صفحة المنتج', 'uses_catalog' => false, 'sort_order' => 903, 'is_active' => true]
        );
        OptionGroup::query()->where('name_ar', 'أثاث وتشطيب منزلي')->update(['menu_detail_profile_id' => $kind->id]);
        DB::table('menu_detail_profile_option_groups')->where('menu_detail_profile_id', $kind->id)->delete();

        return [$id, $kind, (int) DB::table('option_groups')->where('name_ar', 'أنواع الأخشاب')->value('id')];
    }

    private function specs(int $itemId): array
    {
        $rows = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/discovery/menu-items/' . $itemId)->assertOk()->json('data.item.specs');

        return collect($rows)->pluck('value', 'name')->all();
    }

    public function test_a_kind_that_never_chose_lists_every_descriptive_group_of_the_trade(): void
    {
        [$id] = $this->bedroom();

        $specs = $this->specs($id);

        $this->assertSame('مودرن', $specs['طراز الأثاث']);
        $this->assertSame('زان', $specs['أنواع الأخشاب']);
    }

    public function test_a_kind_that_chose_lists_only_its_groups(): void
    {
        [$id, $kind, $wood] = $this->bedroom();
        DB::table('menu_detail_profile_option_groups')->insert(['menu_detail_profile_id' => $kind->id, 'option_group_id' => $wood, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()]);

        $specs = $this->specs($id);

        $this->assertSame('زان', $specs['أنواع الأخشاب']);
        $this->assertArrayNotHasKey('طراز الأثاث', $specs, 'the style was not ticked for this kind');
    }

    public function test_a_group_switched_off_for_the_product_page_is_not_listed(): void
    {
        [$id, $kind, $wood] = $this->bedroom();
        $style = (int) DB::table('option_groups')->where('name_ar', 'طراز الأثاث')->value('id');
        DB::table('menu_detail_profile_option_groups')->insert([
            ['menu_detail_profile_id' => $kind->id, 'option_group_id' => $wood, 'sort_order' => 10, 'show_on_page' => 1, 'display' => 'auto', 'created_at' => now(), 'updated_at' => now()],
            ['menu_detail_profile_id' => $kind->id, 'option_group_id' => $style, 'sort_order' => 20, 'show_on_page' => 0, 'display' => 'auto', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $specs = $this->specs($id);

        $this->assertArrayHasKey('أنواع الأخشاب', $specs);
        $this->assertArrayNotHasKey('طراز الأثاث', $specs, 'ticked for the kind, but switched off for the product page');
    }

    public function test_a_catalog_field_switched_off_for_the_product_page_is_not_listed_but_comes_back_when_on(): void
    {
        [, $kind] = $this->bedroom();
        $color = (int) DB::table('catalog_attributes')->where('code', 'color')->value('id');
        $brown = (int) DB::table('catalog_attribute_options')->where('attribute_id', $color)->where('slug', 'brown')->value('id');
        $bedroom = $this->option('أثاث وتشطيب منزلي', 'غرفة نوم');
        DB::table('menu_detail_profile_attributes')->where('menu_detail_profile_id', $kind->id)->delete();
        $row = ['menu_detail_profile_id' => $kind->id, 'catalog_attribute_id' => $color, 'sort_order' => 10, 'show_on_card' => 0, 'per_item' => 1, 'is_filterable' => 1, 'display' => 'auto', 'created_at' => now(), 'updated_at' => now()];
        DB::table('menu_detail_profile_attributes')->insert($row + ['show_on_page' => 0]);

        $id = (int) $this->postJson('/api/v2/business/menu/items', [
            'name_ar' => 'غرفة نوم بلون', 'base_price' => 30000, 'line_option_id' => $bedroom, 'attributes' => [$color => $brown],
        ])->assertCreated()->json('data.id');

        $this->assertArrayNotHasKey('اللون', $this->specs($id), 'switched off for the product page');

        DB::table('menu_detail_profile_attributes')->where('menu_detail_profile_id', $kind->id)->update(['show_on_page' => 1]);
        $this->assertArrayHasKey('اللون', $this->specs($id));
    }
}
