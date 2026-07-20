<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The attributes axis has to be reachable from the panel.
 *
 * Reported by the owner: the sidebar's «خيارات التصنيفات الفرعية» opened «Bulk
 * Services + Fees» — the services screen — and so did «ربط الخدمات بالتصنيفات».
 * Two menu entries, one destination. The cause was a closure in the route file:
 *
 *   Route::get('bulk/edit', fn () => redirect()->route('admin.categories.services-bulk.index'))
 *
 * so CategoryChildOptionController@bulkEdit (239 lines) and its 409-line view
 * were unreachable, and a screen that manages ATTRIBUTES silently became a
 * screen that manages SERVICES. Those are different axes (blueprint §3.1) and
 * must never share a destination.
 *
 * Nothing failed while this was broken — the page rendered fine, just the wrong
 * page. Only a human clicking the link could catch it. That is what this file
 * replaces. Rolls back.
 */
class AdminOptionsScreensTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $admin = User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin account to act as.');
        }

        return $admin;
    }

    public function test_the_child_options_bulk_editor_is_not_a_redirect_to_the_services_screen(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/category-child-options/bulk/edit');

        $response->assertOk();
        // Through __(): panel labels are translated now, so a hardcoded Arabic
        // literal only matches when the suite happens to run under 'ar'.
        $response->assertSee(__('تعديل خيارات الأقسام الفرعية دفعة واحدة'), false);
        $response->assertDontSee('Bulk Services + Fees', false);
    }

    public function test_the_options_editor_actually_offers_the_attributes(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/category-child-options/bulk/edit');

        // تقسيط is the whole reason the attributes axis exists. A screen that
        // renders without it is not managing attributes.
        $response->assertSee('تقسيط', false);
        $response->assertSee('أنماط خدمة وتجارية', false);
        // Amenities are a real attribute; capacity/class are NOT (they moved to
        // bookable_items), so the options screen must not offer them.
        $response->assertSee('مرافق ومعدات', false);
        $response->assertDontSee('سعة القاعة', false);
    }

    public function test_the_two_sidebar_entries_lead_to_different_screens(): void
    {
        $options = $this->actingAs($this->admin())->get(route('admin.category-child-options.bulk.edit', [], false));
        $services = $this->actingAs($this->admin())->get(route('admin.categories.services-bulk.index', [], false));

        $options->assertOk();
        $services->assertOk();

        $this->assertNotSame(
            $options->getContent(),
            $services->getContent(),
            'two menu entries that render the same page mean one of them is lying about where it goes'
        );
    }

    public function test_the_per_child_options_editor_still_opens(): void
    {
        $childId = \Illuminate\Support\Facades\DB::table('category_children_master')->value('id');

        $this->actingAs($this->admin())
            ->get('/admin/category-child-options/' . $childId)
            ->assertOk()
            ->assertDontSee('Bulk Services + Fees', false);
    }
}
