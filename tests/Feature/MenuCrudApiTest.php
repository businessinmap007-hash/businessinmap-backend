<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\User;
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
