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
 * «حقل الوصف فى إضافة المنتج لا يظهر فى أى مكان، وأشكال المنيو ليس فيها اختيار الوصف» —
 * المالك، 2026-10-04. «الوصف» is a field a detail kind chooses like «اللون»: ticked for the
 * card it becomes the card's line, ticked for the page it shows there. A kind that never
 * chose it keeps the old behaviour — the page, never the card. Rolls back.
 */
class MenuDescriptionFieldTest extends TestCase
{
    use DatabaseTransactions;

    private function attr(string $code): int
    {
        return (int) DB::table('catalog_attributes')->where('code', $code)->value('id');
    }

    /** The cars kind with exactly the given fields, written here so live tuning can't fail the test. */
    private function carsKind(array $fields): array
    {
        $cars = MenuDetailProfile::query()->where('code', 'cars')->firstOrFail();
        $group = OptionGroup::query()->where('name_ar', 'نوع المركبة')->firstOrFail();
        $group->update(['menu_detail_profile_id' => $cars->id]);

        DB::table('menu_detail_profile_attributes')->where('menu_detail_profile_id', $cars->id)->delete();
        foreach ($fields as $i => [$code, $card, $page]) {
            DB::table('menu_detail_profile_attributes')->insert([
                'menu_detail_profile_id' => $cars->id, 'catalog_attribute_id' => $this->attr($code),
                'sort_order' => ($i + 1) * 10, 'show_on_card' => $card, 'show_on_page' => $page, 'per_item' => true,
                'is_filterable' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return [$cars, $group];
    }

    /** @return array<string,mixed> the item as the public menu lists it */
    private function publicItem(array $fields): array
    {
        $shop = User::query()->where('type', 'business')->where('category_child_id', 188)->orderBy('id')->first()
            ?: $this->markTestSkipped('No business stands on child #188 (معرض سيارات).');
        [, $group] = $this->carsKind($fields);
        $sedan = (int) DB::table('options')->where('group_id', $group->id)->where('name_ar', 'سيدان')->value('id');
        DB::table('option_user')->updateOrInsert(['user_id' => $shop->id, 'option_id' => $sedan], []);

        Sanctum::actingAs($shop);
        $id = (int) $this->withHeaders(['Accept-Language' => 'ar'])->postJson('/api/v2/business/menu/items', [
            'name_ar' => 'كورولا بوصف', 'base_price' => 400000, 'line_option_id' => $sedan,
            'description_ar' => 'نظيفة جدا وصيانة توكيل',
        ])->assertCreated()->json('data.id');

        $sections = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/discovery/menu/' . $shop->id)->assertOk()->json('data.sections');

        return collect($sections)->flatMap(fn ($s) => $s['items'])->firstWhere('id', $id);
    }

    public function test_the_description_is_a_field_the_admin_can_choose(): void
    {
        $row = DB::table('catalog_attributes')->where('code', 'description')->first();

        $this->assertNotNull($row, 'the field exists next to «اللون» and «عدد القطع»');
        $this->assertSame('الوصف', $row->name_ar);
    }

    public function test_ticked_for_the_card_the_description_is_the_cards_line(): void
    {
        $item = $this->publicItem([['model_year', false, true], ['description', true, true]]);

        $this->assertSame('نظيفة جدا وصيانة توكيل', $item['card_summary']);
        $this->assertSame('نظيفة جدا وصيانة توكيل', $item['description']);
    }

    public function test_unticked_for_the_page_the_description_is_not_sent_for_the_page(): void
    {
        $item = $this->publicItem([['model_year', false, true], ['description', true, false]]);

        $this->assertSame('', $item['description']);
        $this->assertSame('نظيفة جدا وصيانة توكيل', $item['card_summary']);
    }

    public function test_a_kind_that_dropped_the_field_shows_the_description_nowhere(): void
    {
        $item = $this->publicItem([['model_year', false, true]]);

        $this->assertSame('', $item['description']);
        $this->assertNull($item['card_summary']);
    }

    public function test_the_description_is_never_asked_again_per_unit_nor_filtered(): void
    {
        [$cars] = $this->carsKind([['description', true, true]]);

        $field = MenuDetailProfile::fieldsFor([$cars->id])[$cars->id][0];

        $this->assertFalse($field['per_item'], 'typed once, in the add-product form');
        $this->assertFalse($field['is_filterable']);
    }

    public function test_the_menu_shapes_preview_offers_the_field_and_shows_a_sample_of_it_on_the_card(): void
    {
        [$cars, $group] = $this->carsKind([['model_year', false, true], ['description', true, true]]);
        $admin = User::query()->where('type', 'admin')->first() ?: $this->markTestSkipped('No admin account to act as.');

        $this->actingAs($admin)
            ->get(route('admin.menu-shapes.index', ['group_id' => $group->id, 'preview' => $cars->id], false))
            ->assertOk()
            ->assertSee('الوصف')
            ->assertSee('وصف قصير للمنتج يكتبه التاجر.');
    }

    public function test_the_add_product_box_in_the_preview_follows_the_chosen_field(): void
    {
        $admin = User::query()->where('type', 'admin')->first() ?: $this->markTestSkipped('No admin account to act as.');

        [$cars, $group] = $this->carsKind([['model_year', false, true], ['description', false, true]]);
        $url = route('admin.menu-shapes.index', ['group_id' => $group->id, 'preview' => $cars->id], false);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('الوصف (عربي، اختياري)');

        DB::table('menu_detail_profile_attributes')->where('menu_detail_profile_id', $cars->id)->where('catalog_attribute_id', $this->attr('description'))->delete();
        $this->actingAs($admin)->get($url)->assertOk()->assertDontSee('الوصف (عربي، اختياري)');
    }

    public function test_every_existing_kind_has_the_field_so_no_form_lost_its_box(): void
    {
        $without = DB::table('menu_detail_profiles')->whereNotIn('id', DB::table('menu_detail_profile_attributes')->where('catalog_attribute_id', $this->attr('description'))->pluck('menu_detail_profile_id'))->count();

        $this->assertSame(0, $without);
    }

    public function test_a_kind_without_a_catalog_never_asks_the_description_per_unit_in_the_vocabulary(): void
    {
        $shop = User::query()->where('type', 'business')->where('category_child_id', 188)->orderBy('id')->first()
            ?: $this->markTestSkipped('No business stands on child #188 (معرض سيارات).');
        [$cars, $group] = $this->carsKind([['model_year', false, true], ['description', false, true]]);
        $cars->update(['uses_catalog' => false]);

        Sanctum::actingAs($shop);
        $line = collect($this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data.lines'))->firstWhere('group_id', $group->id);
        $fields = collect($line['detail_profile']['fields'])->keyBy('code');

        $this->assertTrue($fields['model_year']['per_item'], 'a kind with no catalog states every field per unit');
        $this->assertFalse($fields['description']['per_item'], 'except the description, which is the box below');
    }
}
