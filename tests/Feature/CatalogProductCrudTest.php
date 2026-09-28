<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «ابني شاشة CRUD لإضافة موديلات ومواصفات جديدة» — المالك، 2026-09-28.
 * Browsing/inline-editing an existing catalog_products row already existed
 * (CatalogProductController@index); creating a brand-new model with its own
 * spec table never did — see [[menu-catalog-specs-link]] for the gap this
 * closes.
 */
class CatalogProductCrudTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $admin = User::query()->where('type', User::TYPE_ADMIN)->firstOrFail();

        foreach ([AdminAbility::ACCESS, AdminAbility::CATALOG] as $ability) {
            \Bouncer::allow($admin)->to($ability);
        }
        \Bouncer::refresh();

        return $admin;
    }

    private function attribute(string $code, string $dataType = 'text'): int
    {
        return (int) DB::table('catalog_attributes')->insertGetId([
            'code' => $code,
            'name_ar' => 'مواصفة ' . $code,
            'data_type' => $dataType,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** A brand-new model, with two specs, lands in the catalog. */
    public function test_a_new_model_can_be_created_with_specs(): void
    {
        $admin = $this->admin();
        $categoryId = (int) DB::table('product_categories')->value('id');
        $childId = (int) DB::table('product_category_children')->where('product_category_id', $categoryId)->value('id');
        $this->assertGreaterThan(0, $childId, 'fixture needs a real (category,child) pair');

        $cpu = $this->attribute('cpu_test');
        $ram = $this->attribute('ram_test', 'number');

        $response = $this->actingAs($admin)->post(route('admin.catalog-products.store'), [
            'product_category_id' => $categoryId,
            'product_category_child_id' => $childId,
            'name_ar' => 'لاب توب اختبار',
            'name_en' => 'Test Laptop',
            'is_active' => 1,
            'specs' => [
                ['attribute_id' => $cpu, 'value_text' => 'Intel i5', 'value_number' => ''],
                ['attribute_id' => $ram, 'value_text' => '', 'value_number' => '16'],
            ],
        ]);

        $response->assertRedirect();

        $product = DB::table('catalog_products')->where('name_ar', 'لاب توب اختبار')->first();
        $this->assertNotNull($product, 'the product was not created');
        $this->assertNotEmpty($product->bim_code);
        $this->assertSame($categoryId, (int) $product->product_category_id);

        $values = DB::table('catalog_product_attribute_values')->where('product_id', $product->id)->get()->keyBy('attribute_id');
        $this->assertSame('Intel i5', $values[$cpu]->value_text_ar);
        $this->assertEquals(16, $values[$ram]->value_number);
    }

    /** Editing adds a new spec and removes an old one, without duplicating the product. */
    public function test_editing_updates_specs_without_duplicating_the_product(): void
    {
        $admin = $this->admin();
        $categoryId = (int) DB::table('product_categories')->value('id');
        $childId = (int) DB::table('product_category_children')->where('product_category_id', $categoryId)->value('id');

        $storage = $this->attribute('storage_test');
        $screen = $this->attribute('screen_test');

        $this->actingAs($admin)->post(route('admin.catalog-products.store'), [
            'product_category_id' => $categoryId,
            'product_category_child_id' => $childId,
            'name_ar' => 'موبايل اختبار',
            'is_active' => 1,
            'specs' => [
                ['attribute_id' => $storage, 'value_text' => '128GB', 'value_number' => ''],
            ],
        ]);

        $product = DB::table('catalog_products')->where('name_ar', 'موبايل اختبار')->first();
        $storageValueId = (int) DB::table('catalog_product_attribute_values')
            ->where('product_id', $product->id)->where('attribute_id', $storage)->value('id');

        $this->actingAs($admin)->put(route('admin.catalog-products.update', $product->id), [
            'product_category_id' => $categoryId,
            'product_category_child_id' => $childId,
            'name_ar' => 'موبايل اختبار',
            'is_active' => 1,
            'remove_spec_ids' => [$storageValueId],
            'specs' => [
                ['attribute_id' => $screen, 'value_text' => '', 'value_number' => '6.5'],
            ],
        ])->assertRedirect();

        $this->assertSame(1, DB::table('catalog_products')->where('name_ar', 'موبايل اختبار')->count());

        $values = DB::table('catalog_product_attribute_values')->where('product_id', $product->id)->get();
        $this->assertCount(1, $values, 'the removed spec should be gone and the new one added');
        $this->assertSame($screen, (int) $values->first()->attribute_id);
    }

    /** A new attribute type can be created and immediately used by a product. */
    public function test_a_new_attribute_type_can_be_created(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.catalog-attributes.store'), [
            'code' => 'battery_capacity_test',
            'name_ar' => 'سعة البطارية',
            'name_en' => 'Battery capacity',
            'data_type' => 'number',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('catalog_attributes', ['code' => 'battery_capacity_test', 'data_type' => 'number']);
    }

    /** The create/edit screens themselves render for an authorized admin. */
    public function test_the_create_and_edit_screens_render(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.catalog-products.create'))->assertOk();

        $product = DB::table('catalog_products')->first();
        $this->actingAs($admin)->get(route('admin.catalog-products.edit', $product->id))->assertOk();

        $this->actingAs($admin)->get(route('admin.catalog-attributes.create'))->assertOk();
    }
}
