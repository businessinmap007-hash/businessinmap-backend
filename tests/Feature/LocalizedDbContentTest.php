<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Slice D: DB-backed content (name/description/… held in `_ar`/`_en` columns)
 * is returned in the caller's language. The V2 resources now emit a single
 * localized field via the model's loc() (HasLocalizedFields), while keeping the
 * raw `_ar`/`_en` pair for edit screens.
 */
class LocalizedDbContentTest extends TestCase
{
    use DatabaseTransactions;

    private function business(): User
    {
        return User::query()->where('type', 'business')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs a business user.');
    }

    private function createSection(User $business): int
    {
        return $this->actingAs($business, 'sanctum')
            ->postJson('/api/v2/business/menu/sections', [
                'name_ar' => 'المشروبات',
                'name_en' => 'Beverages',
            ])
            ->assertCreated()
            ->json('data.id');
    }

    public function test_section_name_is_english_when_requested_in_english(): void
    {
        $business = $this->business();
        $id = $this->createSection($business);

        $row = collect(
            $this->actingAs($business, 'sanctum')
                ->withHeaders(['Accept-Language' => 'en'])
                ->getJson('/api/v2/business/menu/sections')
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $id);

        $this->assertSame('Beverages', $row['name']);
    }

    public function test_section_name_is_arabic_when_requested_in_arabic(): void
    {
        $business = $this->business();
        $id = $this->createSection($business);

        $row = collect(
            $this->actingAs($business, 'sanctum')
                ->withHeaders(['Accept-Language' => 'ar'])
                ->getJson('/api/v2/business/menu/sections')
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $id);

        $this->assertSame('المشروبات', $row['name']);
    }

    public function test_raw_columns_are_still_present_for_edit_screens(): void
    {
        $business = $this->business();
        $id = $this->createSection($business);

        $row = collect(
            $this->actingAs($business, 'sanctum')
                ->withHeaders(['Accept-Language' => 'en'])
                ->getJson('/api/v2/business/menu/sections')
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $id);

        $this->assertSame('المشروبات', $row['name_ar']);
        $this->assertSame('Beverages', $row['name_en']);
    }

    /** loc() falls back to Arabic when the requested locale's column is empty. */
    public function test_localized_field_falls_back_when_target_language_is_missing(): void
    {
        $business = $this->business();

        $id = $this->actingAs($business, 'sanctum')
            ->postJson('/api/v2/business/menu/sections', ['name_ar' => 'الحلويات'])
            ->assertCreated()
            ->json('data.id');

        $row = collect(
            $this->actingAs($business, 'sanctum')
                ->withHeaders(['Accept-Language' => 'en'])
                ->getJson('/api/v2/business/menu/sections')
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $id);

        // No English name was given, so the localized field falls back to Arabic.
        $this->assertSame('الحلويات', $row['name']);
        $this->assertNull($row['name_en']);
    }

    /** The customer menu-browse list (the primary "list by language") localizes. */
    public function test_customer_menu_browse_returns_names_in_the_requested_language(): void
    {
        $business = $this->business();

        $section = MenuSection::create([
            'business_id' => $business->id,
            'name_ar' => 'المشروبات', 'name_en' => 'Beverages',
            'is_active' => true, 'sort_order' => 1,
        ]);
        $item = MenuItem::create([
            'business_id' => $business->id,
            'menu_section_id' => $section->id,
            'name_ar' => 'شاي', 'name_en' => 'Tea',
            'base_price' => 15, 'is_active' => true, 'sort_order' => 1,
        ]);

        $en = $this->withHeaders(['Accept-Language' => 'en'])
            ->getJson("/api/v2/discovery/menu/{$business->id}")->assertOk();
        $sec = collect($en->json('data.sections'))->firstWhere('id', $section->id);
        $this->assertSame('Beverages', $sec['name']);
        $this->assertSame('Tea', collect($sec['items'])->firstWhere('id', $item->id)['name']);

        $ar = $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson("/api/v2/discovery/menu/{$business->id}")->assertOk();
        $secAr = collect($ar->json('data.sections'))->firstWhere('id', $section->id);
        $this->assertSame('المشروبات', $secAr['name']);
        $this->assertSame('شاي', collect($secAr['items'])->firstWhere('id', $item->id)['name']);
    }
}
