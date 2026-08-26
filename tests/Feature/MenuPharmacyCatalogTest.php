<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * «الصيدلية لها قائمة بكل الادوية والاسعار اسمها قاموس الادوية قم بعمل المنيو
 *  الخاص بها» — المالك، 2026-08-26.
 *
 * Not the bulk «تعبئة الرفوف» table — the dictionary is 25,065 rows, found by
 * `Medicine::scopeSearch()`, the same search a doctor's typeahead uses. Adding
 * one turns it into an ordinary `MenuItem` every other screen already knows
 * how to edit.
 */
class MenuPharmacyCatalogTest extends TestCase
{
    use DatabaseTransactions;

    private const PHARMACY_CHILD_ID = 215;

    private function pharmacy(): User
    {
        return User::query()->where('type', 'business')->where('category_child_id', self::PHARMACY_CHILD_ID)
            ->orderBy('id')->first() ?: $this->markTestSkipped('No pharmacy business to test with.');
    }

    public function test_the_screen_is_open_to_a_pharmacy_and_closed_elsewhere(): void
    {
        $this->actingAs($this->pharmacy())
            ->get(route('business.menu.pharmacy.index'))
            ->assertOk();

        $stranger = User::query()->where('type', 'business')
            ->where('category_child_id', '!=', self::PHARMACY_CHILD_ID)
            ->where('category_child_id', '>', 0)
            ->orderBy('id')->first() ?: $this->markTestSkipped('Needs a non-pharmacy business.');

        $this->actingAs($stranger)
            ->get(route('business.menu.pharmacy.index'))
            ->assertForbidden();
    }

    public function test_search_finds_a_real_drug_and_carries_its_reference_price(): void
    {
        $medicine = Medicine::query()->whereNotNull('price_egp')->firstOrFail();
        $term = substr((string) $medicine->name, 0, 6);

        $response = $this->actingAs($this->pharmacy())
            ->getJson(route('business.menu.pharmacy.search', ['q' => $term]))
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($response, 'search returned nothing for a real drug name');
        $this->assertTrue(
            collect($response)->contains(fn ($row) => (int) $row['id'] === (int) $medicine->id),
            'the searched drug was not among the results'
        );
    }

    public function test_adding_a_drug_creates_an_ordinary_menu_item(): void
    {
        $business = $this->pharmacy();
        $medicine = Medicine::query()->whereNotNull('manufacturer')->firstOrFail();

        $this->actingAs($business)
            ->post(route('business.menu.pharmacy.store'), [
                'medicine_id' => $medicine->id,
                'base_price' => 55.5,
                'quantity' => 30,
            ])
            ->assertRedirect();

        $item = MenuItem::query()->where('business_id', $business->id)->where('medicine_id', $medicine->id)->first();

        $this->assertNotNull($item, 'the drug was never added');
        $this->assertSame((string) $medicine->name, $item->name_en);
        $this->assertSame((string) $medicine->name, $item->name_ar, 'name_ar should carry the commercial name, not stay unset');
        $this->assertNotSame($medicine->name_ar, $item->name_ar, 'the dictionary\'s phonetic name_ar leaked into the display name');
        $this->assertSame(55.5, (float) $item->base_price);
        $this->assertSame(30, (int) $item->available_quantity);
        $this->assertSame($medicine->manufacturer, $item->brand_name);
        $this->assertTrue((bool) $item->is_active);
    }

    public function test_adding_the_same_drug_twice_updates_rather_than_duplicates(): void
    {
        $business = $this->pharmacy();
        $medicine = Medicine::query()->whereNotNull('price_egp')->firstOrFail();

        $this->actingAs($business)->post(route('business.menu.pharmacy.store'), [
            'medicine_id' => $medicine->id, 'base_price' => 40,
        ]);

        $this->actingAs($business)->post(route('business.menu.pharmacy.store'), [
            'medicine_id' => $medicine->id, 'base_price' => 60,
        ]);

        $this->assertSame(
            1,
            MenuItem::query()->where('business_id', $business->id)->where('medicine_id', $medicine->id)->count()
        );
        $this->assertSame(
            60.0,
            (float) MenuItem::query()->where('business_id', $business->id)->where('medicine_id', $medicine->id)->value('base_price')
        );
    }

    public function test_the_customer_facing_heading_is_the_one_dictionary_section(): void
    {
        $business = $this->pharmacy();
        $medicine = Medicine::query()->whereNotNull('price_egp')->firstOrFail();

        $this->actingAs($business)->post(route('business.menu.pharmacy.store'), [
            'medicine_id' => $medicine->id, 'base_price' => 33,
        ]);

        $this->get('/api/v2/discovery/menu/' . $business->id)
            ->assertOk()
            ->assertJsonFragment(['name' => 'قاموس الأدوية'])
            ->assertJsonFragment(['name' => (string) $medicine->name]);
    }

    public function test_a_stranger_cannot_add_to_someone_elses_pharmacy(): void
    {
        $stranger = User::query()->where('type', 'business')
            ->where('category_child_id', '!=', self::PHARMACY_CHILD_ID)
            ->where('category_child_id', '>', 0)
            ->orderBy('id')->first() ?: $this->markTestSkipped('Needs a non-pharmacy business.');

        $medicine = Medicine::query()->whereNotNull('price_egp')->firstOrFail();

        $this->actingAs($stranger)
            ->post(route('business.menu.pharmacy.store'), ['medicine_id' => $medicine->id, 'base_price' => 20])
            ->assertForbidden();

        $this->assertSame(
            0,
            MenuItem::query()->where('business_id', $stranger->id)->where('medicine_id', $medicine->id)->count()
        );
    }
}
