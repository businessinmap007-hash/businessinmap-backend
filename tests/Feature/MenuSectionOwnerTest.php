<?php

namespace Tests\Feature;

use App\Models\MenuSection;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsMenu;
use Tests\TestCase;

/**
 * Owner-panel menu sections: create a section, assign it to a menu item, and the
 * assignment is scoped to the owner's own sections.
 */
class MenuSectionOwnerTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsMenu;

    private function owner(): User
    {
        return User::query()->where('type', 'business')->firstOrFail();
    }

    public function test_owner_creates_a_section(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->post(route('business.menu-sections.store', [], false), [
            'name_ar' => 'حلويات', 'sort_order' => 3, 'is_active' => 1,
        ])->assertRedirect(route('business.menu-sections.index'));

        $this->assertDatabaseHas('menu_sections', ['business_id' => $owner->id, 'name_ar' => 'حلويات']);
    }

    public function test_item_can_be_assigned_to_own_section(): void
    {
        $owner = $this->owner();
        $section = $this->seedSection($owner->id, 'مقبلات');
        $this->actingAs($owner);

        $this->post(route('business.menu.store', [], false), [
            'name_ar' => 'سلطة', 'base_price' => 20, 'menu_section_id' => $section->id, 'is_active' => 1,
        ])->assertRedirect(route('business.menu.index'));

        $this->assertDatabaseHas('menu_items', ['business_id' => $owner->id, 'name_ar' => 'سلطة', 'menu_section_id' => $section->id]);
    }

    public function test_cannot_assign_another_business_section(): void
    {
        $owner = $this->owner();
        $other = User::query()->where('type', 'business')->where('id', '!=', $owner->id)->firstOrFail();
        $foreignSection = $this->seedSection($other->id, 'قسم غريب');

        $this->actingAs($owner);

        $this->post(route('business.menu.store', [], false), [
            'name_ar' => 'صنف', 'base_price' => 15, 'menu_section_id' => $foreignSection->id, 'is_active' => 1,
        ])->assertStatus(302)->assertSessionHasErrors('menu_section_id');

        $this->assertDatabaseMissing('menu_items', ['name_ar' => 'صنف', 'menu_section_id' => $foreignSection->id]);
    }
}
