<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\User;
use App\Services\MerchantOfferingVocabulary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * v2 business menu CRUD: business-only guard, per-business scoping, foreign
 * section rejection, and default-variant switching. Rolls back.
 */
class MenuCrudApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = User::query()->where('type', 'business')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs a business user.');
    }

    public function test_client_cannot_manage_menu(): void
    {
        $client = User::query()->where('type', '!=', 'business')->orderBy('id')->firstOrFail();

        $this->actingAs($client, 'sanctum')->getJson('/api/v2/business/menu/sections')->assertForbidden();
        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v2/business/menu/sections', ['name_ar' => 'قسم'])->assertForbidden();
    }

    public function test_business_creates_section_and_item(): void
    {
        $section = $this->actingAs($this->business, 'sanctum')
            ->postJson('/api/v2/business/menu/sections', ['name_ar' => 'المشروبات'])
            ->assertCreated()->json('data');

        $this->assertDatabaseHas('menu_sections', ['id' => $section['id'], 'business_id' => $this->business->id]);

        $item = $this->actingAs($this->business, 'sanctum')
            ->postJson('/api/v2/business/menu/items', [
                'name_ar' => 'شاي', 'base_price' => 15, 'menu_section_id' => $section['id'],
            ])->assertCreated()->json('data');

        $this->assertDatabaseHas('menu_items', ['id' => $item['id'], 'business_id' => $this->business->id]);
    }

    public function test_sale_units_lists_the_shared_vocabulary(): void
    {
        $units = $this->actingAs($this->business, 'sanctum')
            ->getJson('/api/v2/business/menu/sale-units')->assertOk()->json('data.units');

        $this->assertNotEmpty($units, 'the shared catalog_units vocabulary must not be empty');
        $this->assertArrayHasKey('code', $units[0]);
        $this->assertArrayHasKey('label', $units[0]);
    }

    public function test_item_stores_and_returns_its_sale_unit(): void
    {
        $units = $this->actingAs($this->business, 'sanctum')
            ->getJson('/api/v2/business/menu/sale-units')->json('data.units');
        $unit = collect($units)->firstWhere('code', 'kg') ?? $units[0];

        $item = $this->actingAs($this->business, 'sanctum')
            ->postJson('/api/v2/business/menu/items', [
                'name_ar' => 'بصل بالكيلو', 'base_price' => 20, 'sale_unit' => $unit['code'],
            ])->assertCreated()->json('data');

        $this->assertSame($unit['code'], $item['sale_unit']);
        $this->assertSame($unit['label'], $item['sale_unit_label']);
    }

    public function test_item_rejects_a_section_owned_by_another_business(): void
    {
        $otherBiz = User::query()->where('type', 'business')->where('id', '!=', $this->business->id)->first();
        if (! $otherBiz) {
            $this->markTestSkipped('Needs a second business.');
        }

        $foreign = MenuSection::create(['business_id' => $otherBiz->id, 'name_ar' => 'قسم غريب', 'is_active' => true]);

        $this->actingAs($this->business, 'sanctum')
            ->postJson('/api/v2/business/menu/items', [
                'name_ar' => 'صنف', 'base_price' => 10, 'menu_section_id' => $foreign->id,
            ])->assertStatus(422);
    }

    public function test_another_business_cannot_delete_my_item(): void
    {
        $otherBiz = User::query()->where('type', 'business')->where('id', '!=', $this->business->id)->first();
        if (! $otherBiz) {
            $this->markTestSkipped('Needs a second business.');
        }

        $item = MenuItem::create([
            'business_id' => $this->business->id, 'name_ar' => 'صنفي', 'base_price' => 20, 'is_active' => true,
        ]);

        $this->actingAs($otherBiz, 'sanctum')
            ->deleteJson("/api/v2/business/menu/items/{$item->id}")->assertNotFound();
    }

    public function test_display_mode_defaults_to_list_and_can_be_switched_to_grid(): void
    {
        $default = $this->actingAs($this->business, 'sanctum')
            ->getJson('/api/v2/business/menu/display-mode')->assertOk()->json('data.display_mode');
        $this->assertSame('list', $default);

        $this->actingAs($this->business, 'sanctum')
            ->putJson('/api/v2/business/menu/display-mode', ['display_mode' => 'grid'])
            ->assertOk()
            ->assertJsonPath('data.display_mode', 'grid');

        $this->assertDatabaseHas('business_menu_settings', [
            'business_id' => $this->business->id,
            'display_mode' => 'grid',
        ]);

        $read = $this->actingAs($this->business, 'sanctum')
            ->getJson('/api/v2/business/menu/display-mode')->assertOk()->json('data.display_mode');
        $this->assertSame('grid', $read);
    }

    public function test_display_mode_rejects_an_unknown_value(): void
    {
        $this->actingAs($this->business, 'sanctum')
            ->putJson('/api/v2/business/menu/display-mode', ['display_mode' => 'carousel'])
            ->assertStatus(422);
    }

    public function test_the_customer_menu_reflects_the_chosen_display_mode(): void
    {
        MenuItem::create([
            'business_id' => $this->business->id, 'name_ar' => 'صنف', 'base_price' => 10, 'is_active' => true,
        ]);

        $this->actingAs($this->business, 'sanctum')
            ->putJson('/api/v2/business/menu/display-mode', ['display_mode' => 'grid'])
            ->assertOk();

        $this->getJson('/api/v2/discovery/menu/' . $this->business->id)
            ->assertOk()
            ->assertJsonPath('data.business.menu_display_mode', 'grid');
    }

    /** A business whose vocabulary actually has a `line` group to sell under. */
    private function businessWithLineVocabulary(): array
    {
        $vocabulary = app(MerchantOfferingVocabulary::class);

        foreach (User::query()->where('type', 'business')->orderBy('id')->cursor() as $candidate) {
            $lines = $vocabulary->for((int) $candidate->id, (int) $candidate->category_child_id, (int) $candidate->category_id)['lines'];

            if ($lines->isNotEmpty()) {
                $group = $lines->first();

                return [$candidate, (int) $group->first()->id, (int) $group->first()->group_id];
            }
        }

        $this->markTestSkipped('Needs a business whose specialty has a `line` option group.');
    }

    public function test_vocabulary_lists_the_businesss_own_line_and_modifier_groups(): void
    {
        [$business] = $this->businessWithLineVocabulary();

        $data = $this->actingAs($business, 'sanctum')
            ->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data');

        $this->assertNotEmpty($data['lines'], 'a business with a line group must see it here');
        $this->assertArrayHasKey('group_id', $data['lines'][0]);
        $this->assertArrayHasKey('options', $data['lines'][0]);
        $this->assertArrayHasKey('id', $data['lines'][0]['options'][0]);
    }

    /**
     * A closed brand vocabulary exists for appliance children (child 88) —
     * see the 2026-09-09 migration. Its group must come back flagged
     * `is_brand` so the item form can single it out as its own dropdown
     * instead of a generic modifier chip, and an ordinary modifier group
     * (not named "ماركات...") must not be flagged.
     */
    public function test_a_brand_named_group_is_flagged_as_the_brand_vocabulary(): void
    {
        $business = User::query()->where('category_child_id', 88)->first();

        if (! $business) {
            $this->markTestSkipped('Needs a business on the appliance child (88).');
        }

        $modifiers = collect(
            $this->actingAs($business, 'sanctum')
                ->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data.modifiers')
        );

        $brandGroup = $modifiers->firstWhere('group_name', 'ماركات الأجهزة الكهربائية');
        $this->assertNotNull($brandGroup, 'the appliance brand group must be in this business\'s modifiers');
        $this->assertTrue($brandGroup['is_brand']);
        $this->assertTrue(collect($brandGroup['options'])->pluck('name_ar')->contains('كريازي'));

        $nonBrand = $modifiers->first(fn ($g) => $g['group_name'] !== 'ماركات الأجهزة الكهربائية');
        if ($nonBrand) {
            $this->assertFalse($nonBrand['is_brand']);
        }
    }

    public function test_item_with_a_line_option_grows_its_own_section(): void
    {
        [$business, $lineOptionId, $groupId] = $this->businessWithLineVocabulary();

        $item = $this->actingAs($business, 'sanctum')
            ->postJson('/api/v2/business/menu/items', [
                'name_ar' => 'صنف مربوط بفرع', 'base_price' => 30, 'line_option_id' => $lineOptionId,
            ])->assertCreated()->json('data');

        $this->assertSame($lineOptionId, $item['line_option']['id']);
        $this->assertNotNull($item['menu_section_id'], 'a line option must grow a section automatically');

        $this->assertDatabaseHas('menu_sections', [
            'id' => $item['menu_section_id'],
            'business_id' => $business->id,
            'option_group_id' => $groupId,
        ]);

        // Same group, a second item — must land in the SAME section, not a duplicate.
        $second = $this->actingAs($business, 'sanctum')
            ->postJson('/api/v2/business/menu/items', [
                'name_ar' => 'صنف تاني نفس الفرع', 'base_price' => 40, 'line_option_id' => $lineOptionId,
            ])->assertCreated()->json('data');

        $this->assertSame($item['menu_section_id'], $second['menu_section_id']);
    }

    public function test_a_hand_picked_section_is_not_overridden_by_the_line_options_group(): void
    {
        [$business, $lineOptionId] = $this->businessWithLineVocabulary();

        $manualSection = MenuSection::create([
            'business_id' => $business->id, 'name_ar' => 'قسم كتبته يدويًا', 'is_active' => true,
        ]);

        $item = $this->actingAs($business, 'sanctum')
            ->postJson('/api/v2/business/menu/items', [
                'name_ar' => 'صنف بقسم يدوي',
                'base_price' => 30,
                'menu_section_id' => $manualSection->id,
                'line_option_id' => $lineOptionId,
            ])->assertCreated()->json('data');

        $this->assertSame($manualSection->id, $item['menu_section_id']);
    }

    public function test_a_foreign_line_option_is_ignored_not_rejected(): void
    {
        [$business] = $this->businessWithLineVocabulary();

        $item = $this->actingAs($business, 'sanctum')
            ->postJson('/api/v2/business/menu/items', [
                'name_ar' => 'صنف بخيار غريب', 'base_price' => 30, 'line_option_id' => 999999999,
            ])->assertCreated()->json('data');

        $this->assertNull($item['line_option']);
        $this->assertNull($item['menu_section_id']);
    }

    public function test_second_default_variant_unsets_the_first(): void
    {
        $item = MenuItem::create([
            'business_id' => $this->business->id, 'name_ar' => 'برجر', 'base_price' => 50, 'is_active' => true,
        ]);

        $v1 = $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/business/menu/items/{$item->id}/variants", [
                'type' => 'size', 'name_ar' => 'صغير', 'price' => 50, 'is_default' => true,
            ])->assertCreated()->json('data.id');

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/business/menu/items/{$item->id}/variants", [
                'type' => 'size', 'name_ar' => 'كبير', 'price' => 70, 'is_default' => true,
            ])->assertCreated();

        $this->assertDatabaseHas('menu_item_variants', ['id' => $v1, 'is_default' => 0]);
    }
}
