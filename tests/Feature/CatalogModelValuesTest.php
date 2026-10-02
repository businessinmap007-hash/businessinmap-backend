<?php

namespace Tests\Feature;

use App\Models\MenuDetailProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «ابدأ بشاشة إدخال قيم موديلات الكتالوج» — المالك، 2026-10-02: a detail
 * kind's catalog models as rows, the fields the MODEL answers as columns.
 * Rolls back.
 */
class CatalogModelValuesTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::query()->where('type', 'admin')->first() ?: $this->markTestSkipped('No admin account to act as.');
    }

    private function attr(string $code): int
    {
        return (int) DB::table('catalog_attributes')->where('code', $code)->value('id');
    }

    /** A phone model on the mobiles shelf, with nothing stated for the kind's fields. */
    private function phone(): int
    {
        $id = (int) DB::table('catalog_products')->where('bim_code', 'BIM-RT-MOBI-020')->value('id');
        $this->assertGreaterThan(0, $id, 'a seeded phone exists');
        DB::table('catalog_product_attribute_values')->where('product_id', $id)->whereIn('attribute_id', [$this->attr('processor'), $this->attr('ram_gb'), $this->attr('battery_mah')])->delete();

        // The kind as the owner set it — written here, so an admin re-tuning
        // «أشكال المنيو» cannot fail this file: colour is stated per unit.
        $mobiles = MenuDetailProfile::query()->where('code', 'mobiles')->firstOrFail();
        foreach (['processor', 'ram_gb', 'storage', 'battery_mah', 'color'] as $i => $code) {
            DB::table('menu_detail_profile_attributes')->updateOrInsert(
                ['menu_detail_profile_id' => $mobiles->id, 'catalog_attribute_id' => $this->attr($code)],
                ['sort_order' => ($i + 1) * 10, 'show_on_card' => false, 'per_item' => $code === 'color', 'is_filterable' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        return $id;
    }

    public function test_the_screen_lists_a_kinds_models_with_the_fields_the_model_answers(): void
    {
        $phone = $this->phone();
        $name = DB::table('catalog_products')->where('id', $phone)->value('name_ar');

        $page = $this->actingAs($this->admin())
            ->get(route('admin.catalog-model-values.index', ['profile' => 'mobiles', 'q' => $name], false))
            ->assertOk();

        $page->assertSee($name);
        $page->assertSee('البطارية');          // a model field…
        $page->assertDontSee('اللون');         // …but colour is stated per unit
    }

    public function test_a_kind_whose_every_field_is_per_unit_has_nothing_to_enter_here(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.catalog-model-values.index', ['profile' => 'cars'], false))
            ->assertOk()
            ->assertSee('يدخلها التاجر لكل وحدة');
    }

    public function test_figures_and_text_are_saved_and_a_blank_removes_the_value(): void
    {
        $phone = $this->phone();
        $battery = $this->attr('battery_mah');
        $cpu = $this->attr('processor');
        $admin = $this->actingAs($this->admin());

        $admin->post(route('admin.catalog-model-values.save', [], false), [
            'profile' => 'mobiles',
            'values' => [$phone => [$battery => '5,000', $cpu => 'Snapdragon 8 Gen 3']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $stored = DB::table('catalog_product_attribute_values')->where('product_id', $phone)->whereIn('attribute_id', [$battery, $cpu])->get()->keyBy('attribute_id');
        $this->assertEquals(5000, $stored[$battery]->value_number, 'a thousands separator is tolerated');
        $this->assertSame('Snapdragon 8 Gen 3', $stored[$cpu]->value_text_en);
        $this->assertNotNull($stored[$battery]->unit_id, 'the attribute\'s own unit is kept');

        // …and they are what the product page, card and search read.
        $specs = collect(app(\App\Services\Catalog\ProductSpecs::class)->forProducts([$phone])[$phone])->keyBy('code');
        $this->assertStringContainsString('5000', $specs['battery_mah']['value']);

        $admin->post(route('admin.catalog-model-values.save', [], false), [
            'profile' => 'mobiles',
            'values' => [$phone => [$battery => '']],
        ])->assertRedirect();
        $this->assertFalse(DB::table('catalog_product_attribute_values')->where('product_id', $phone)->where('attribute_id', $battery)->exists(), 'blank = no value');
        $this->assertTrue(DB::table('catalog_product_attribute_values')->where('product_id', $phone)->where('attribute_id', $cpu)->exists(), 'the others stay');
    }

    public function test_a_wrong_value_is_refused_and_a_per_unit_field_is_never_written_to_a_model(): void
    {
        $phone = $this->phone();
        $battery = $this->attr('battery_mah');
        $color = $this->attr('color'); // per-unit for mobiles

        $this->actingAs($this->admin())->post(route('admin.catalog-model-values.save', [], false), [
            'profile' => 'mobiles',
            'values' => [$phone => [$battery => 'كبيرة']],
        ])->assertSessionHasErrors('values');
        $this->assertFalse(DB::table('catalog_product_attribute_values')->where('product_id', $phone)->where('attribute_id', $battery)->exists());

        $white = (int) DB::table('catalog_attribute_options')->where('attribute_id', $color)->where('slug', 'white')->value('id');
        $this->actingAs($this->admin())->post(route('admin.catalog-model-values.save', [], false), [
            'profile' => 'mobiles',
            'values' => [$phone => [$color => $white]],
        ])->assertRedirect();
        $this->assertFalse(DB::table('catalog_product_attribute_values')->where('product_id', $phone)->where('attribute_id', $color)->exists(), 'colour is the merchant\'s, per unit');
    }

    public function test_a_choice_must_belong_to_its_own_field(): void
    {
        // Give a kind a model-level choice, then offer it an option of another attribute.
        $mobiles = MenuDetailProfile::query()->where('code', 'mobiles')->firstOrFail();
        $os = $this->attr('os');
        DB::table('catalog_attributes')->where('id', $os)->update(['data_type' => 'select']);
        $phone = $this->phone();
        $foreign = (int) DB::table('catalog_attribute_options')->where('attribute_id', $this->attr('fuel_type'))->value('id');

        $this->actingAs($this->admin())->post(route('admin.catalog-model-values.save', [], false), [
            'profile' => 'mobiles',
            'values' => [$phone => [$os => $foreign]],
        ])->assertSessionHasErrors('values');

        $this->assertNotNull($mobiles);
    }
}
