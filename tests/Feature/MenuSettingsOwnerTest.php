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
}
