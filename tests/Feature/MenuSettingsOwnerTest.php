<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Owner menu settings: the restaurant owner toggles whether prices include the
 * service fee / tax, saved to business_menu_settings (scoped to the owner).
 */
class MenuSettingsOwnerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_edit_screen_renders_the_new_fields(): void
    {
        $owner = User::query()->where('type', 'business')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('business.menu-settings.edit', [], false))
            ->assertOk()
            ->assertSee('min_order_amount', false)
            ->assertSee('default_margin_percent', false);
    }

    public function test_owner_saves_inclusive_flags(): void
    {
        $owner = User::query()->where('type', 'business')->firstOrFail();
        $this->actingAs($owner);

        $this->put(route('business.menu-settings.update', [], false), [
            'prices_include_service' => 1,
            'prices_include_tax' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('business_menu_settings', [
            'business_id' => $owner->id,
            'prices_include_service' => 1,
            'prices_include_tax' => 1,
        ]);
    }

    public function test_unchecking_saves_false(): void
    {
        $owner = User::query()->where('type', 'business')->firstOrFail();
        $this->actingAs($owner);

        // First set both on, then submit with none checked → both off.
        $this->put(route('business.menu-settings.update', [], false), ['prices_include_service' => 1, 'prices_include_tax' => 1]);
        $this->put(route('business.menu-settings.update', [], false), [])->assertRedirect();

        $this->assertDatabaseHas('business_menu_settings', [
            'business_id' => $owner->id,
            'prices_include_service' => 0,
            'prices_include_tax' => 0,
        ]);
    }

    /** «حدٌّ أدنى للطلب، وهامش ربح افتراضى فوق السعر الإرشادى» — المالك، 2026-08-25. */
    public function test_owner_saves_min_order_and_default_margin(): void
    {
        $owner = User::query()->where('type', 'business')->firstOrFail();
        $this->actingAs($owner);

        $this->put(route('business.menu-settings.update', [], false), [
            'min_order_amount' => 50,
            'default_margin_percent' => 20,
        ])->assertRedirect();

        $this->assertDatabaseHas('business_menu_settings', [
            'business_id' => $owner->id,
            'min_order_amount' => 50,
            'default_margin_percent' => 20,
        ]);
    }

    public function test_leaving_them_blank_clears_them_back_to_no_limit(): void
    {
        $owner = User::query()->where('type', 'business')->firstOrFail();
        $this->actingAs($owner);

        $this->put(route('business.menu-settings.update', [], false), [
            'min_order_amount' => 50,
            'default_margin_percent' => 20,
        ]);
        $this->put(route('business.menu-settings.update', [], false), [])->assertRedirect();

        $this->assertDatabaseHas('business_menu_settings', [
            'business_id' => $owner->id,
            'min_order_amount' => null,
            'default_margin_percent' => null,
        ]);
    }

    /** «حدٌّ يستوجب ضمانًا» على طلبات المنيو/التوصيل — 2026-08-28، انظر OrderDepositTest. */
    public function test_owner_saves_and_clears_the_deposit_threshold(): void
    {
        $owner = User::query()->where('type', 'business')->firstOrFail();
        $this->actingAs($owner);

        $this->put(route('business.menu-settings.update', [], false), [
            'deposit_required_above' => 200,
        ])->assertRedirect();

        $this->assertDatabaseHas('business_menu_settings', [
            'business_id' => $owner->id,
            'deposit_required_above' => 200,
        ]);

        $this->put(route('business.menu-settings.update', [], false), [])->assertRedirect();

        $this->assertDatabaseHas('business_menu_settings', [
            'business_id' => $owner->id,
            'deposit_required_above' => null,
        ]);
    }
}
