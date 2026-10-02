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
 * «اضافة منتج بتفتح السعر والتفاصيل والاختيار من الموبايلات وليس من نوم سفرة
 * انترية» + «اريد زر اضافة حقل» — المالك، 2026-10-02: a kind with no catalog
 * (furniture) makes the merchant name the item and state every field himself,
 * and the admin can invent a field right from «أشكال المنيو». Rolls back.
 */
class NoCatalogKindTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::query()->where('type', 'admin')->first() ?: $this->markTestSkipped('No admin account to act as.');
    }

    /** «آثاث» as the owner set it: a kind of its own on the furniture group, with no catalog. */
    private function furniture(): array
    {
        $kind = MenuDetailProfile::query()->firstOrCreate(
            ['code' => 'fur-test'],
            ['name_ar' => 'آثاث للاختبار', 'uses_catalog' => false, 'sort_order' => 900, 'is_active' => true]
        );
        $kind->update(['uses_catalog' => false]);

        $group = OptionGroup::query()->where('name_ar', 'أثاث وتشطيب منزلي')->firstOrFail();
        $group->update(['menu_detail_profile_id' => $kind->id]);

        $color = (int) DB::table('catalog_attributes')->where('code', 'color')->value('id');
        DB::table('menu_detail_profile_attributes')->where('menu_detail_profile_id', $kind->id)->delete();
        DB::table('menu_detail_profile_attributes')->insert([
            'menu_detail_profile_id' => $kind->id, 'catalog_attribute_id' => $color, 'sort_order' => 10,
            'show_on_card' => false, 'per_item' => false, 'is_filterable' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$kind, $group, $color];
    }

    public function test_a_kind_without_a_catalog_says_so_and_every_field_is_stated_per_item(): void
    {
        [, $group, $color] = $this->furniture();
        $shop = User::query()->where('type', 'business')->where('category_child_id', 116)->where('category_id', 23)->orderBy('id')->firstOrFail();
        Sanctum::actingAs($shop);

        $line = collect($this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data.lines'))
            ->firstWhere('group_id', $group->id);

        $this->assertTrue($line['detailed']);
        $this->assertFalse($line['detail_profile']['uses_catalog'], 'a client must not open the catalog picker for this');
        $fields = collect($line['detail_profile']['fields'])->keyBy('id');
        $this->assertTrue($fields[$color]['per_item'], 'with no catalog there is no master to state it: the merchant does');
    }

    public function test_a_bedroom_with_its_own_fields_is_saved_without_any_catalog_product(): void
    {
        [, $group, $color] = $this->furniture();
        $shop = User::query()->where('type', 'business')->where('category_child_id', 116)->where('category_id', 23)->orderBy('id')->firstOrFail();
        $bedroom = (int) DB::table('options')->where('group_id', $group->id)->where('name_ar', 'غرفة نوم')->value('id');
        DB::table('option_user')->updateOrInsert(['user_id' => $shop->id, 'option_id' => $bedroom], []);
        $walnut = (int) DB::table('catalog_attribute_options')->where('attribute_id', $color)->where('slug', 'brown')->value('id');
        Sanctum::actingAs($shop);

        $res = $this->postJson('/api/v2/business/menu/items', [
            'name_ar' => 'غرفة نوم ماستر', 'base_price' => 52000, 'line_option_id' => $bedroom,
            'attributes' => [$color => $walnut],
        ])->assertCreated();

        $this->assertNull($res->json('data.catalog_product_id'));
        $this->assertSame($walnut, $res->json('data.attributes.0.option_id'), 'the field is stored although it is not flagged per-unit in the table');
    }

    public function test_the_admin_can_switch_a_kinds_catalog_on_and_off(): void
    {
        [$kind] = $this->furniture();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.menu-shapes.profiles.settings', $kind, false), ['uses_catalog' => 1])->assertRedirect();
        $this->assertTrue($kind->fresh()->uses_catalog);

        $this->actingAs($admin)->post(route('admin.menu-shapes.profiles.settings', $kind, false), [])->assertRedirect();
        $this->assertFalse($kind->fresh()->uses_catalog, 'unticked = no catalog');
    }

    public function test_a_new_kind_has_no_catalog_unless_the_admin_says_so(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.menu-shapes.profiles.store', [], false), ['name_ar' => 'ألواح بديل الخشب', 'name_en' => 'WPC boards'])->assertRedirect();
        $this->assertFalse(MenuDetailProfile::query()->where('code', 'wpc-boards')->value('uses_catalog'));

        $this->actingAs($admin)->post(route('admin.menu-shapes.profiles.store', [], false), ['name_ar' => 'ساعات', 'name_en' => 'Watches', 'uses_catalog' => 1])->assertRedirect();
        $this->assertTrue((bool) MenuDetailProfile::query()->where('code', 'watches')->value('uses_catalog'));
    }

    public function test_the_admin_adds_a_field_right_from_the_kind_a_number_a_text_and_a_choice(): void
    {
        [$kind] = $this->furniture();
        $admin = $this->actingAs($this->admin());
        $add = fn (array $d) => $admin->post(route('admin.menu-shapes.profiles.attributes', $kind, false), $d)->assertRedirect()->assertSessionHasNoErrors();

        $add(['name_ar' => 'سمك اللوح', 'name_en' => 'Board thickness', 'data_type' => 'number', 'unit' => 'ملم']);
        $add(['name_ar' => 'نوع الفرش', 'name_en' => 'Upholstery', 'data_type' => 'select', 'options' => "جلد\nقماش\nكتان\n"]);
        $add(['name_ar' => 'ملاحظة التصنيع', 'data_type' => 'text']);

        $fields = collect(MenuDetailProfile::fieldsFor([$kind->id])[$kind->id])->keyBy('name');

        $this->assertSame('number', $fields['سمك اللوح']['data_type']);
        $this->assertSame('ملم', $fields['سمك اللوح']['unit']);
        $this->assertSame(['جلد', 'قماش', 'كتان'], array_column($fields['نوع الفرش']['options'], 'name'));
        $this->assertSame('text', $fields['ملاحظة التصنيع']['data_type']);
        $this->assertTrue($fields['سمك اللوح']['per_item'], 'a kind with no catalog: the merchant states it');
        $this->assertTrue($fields['سمك اللوح']['is_filterable']);

        // The same name twice is the same field — switched on, not duplicated.
        $add(['name_ar' => 'سمك اللوح', 'data_type' => 'number']);
        $this->assertSame(1, DB::table('catalog_attributes')->where('name_ar', 'سمك اللوح')->count());
    }

    public function test_a_choice_field_needs_options(): void
    {
        [$kind] = $this->furniture();

        $this->actingAs($this->admin())->post(route('admin.menu-shapes.profiles.attributes', $kind, false), ['name_ar' => 'لون الخشب', 'data_type' => 'select', 'options' => "\n  \n"])
            ->assertSessionHasErrors('options');

        $this->assertSame(0, DB::table('catalog_attributes')->where('name_ar', 'لون الخشب')->count());
    }
}
