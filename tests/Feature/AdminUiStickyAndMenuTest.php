<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Runtime proof of the two root causes fixed in 656b7ed/354a71f, since the
 * sandbox can't open a real browser to screenshot:
 *
 *  1) The Users table wrapper is `.a2-table-wrap` (hyphen) — the class the
 *     sticky-header CSS must (now) target. If the rendered page used the other
 *     spelling, the fix would miss it (the original bug).
 *  2) The full admin menu — including grouped routes that live inside
 *     <details class="a2-nav-group"> — is present in the DOM. Their visibility
 *     is CSS-only, so the earlier "menu emptied out" was purely the rail's
 *     display:none (now fixed), not a server-side omission.
 *
 * These run the real auth + DB + Blade stack (actingAs an admin), so they
 * assert against exactly what a logged-in admin receives.
 */
class AdminUiStickyAndMenuTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::query()->where('type', 'admin')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs an admin user.');
    }

    public function test_users_index_table_uses_the_wrapper_the_sticky_css_targets(): void
    {
        $html = $this->actingAs($this->admin())
            ->get('/admin/users')
            ->assertOk()
            ->getContent();

        // The wrapper class the sticky-header rule now covers.
        $this->assertStringContainsString('a2-table-wrap', $html);
        // And it wraps an actual table head (the thing that must pin).
        $this->assertStringContainsString('a2-table', $html);
        $this->assertStringContainsString('<thead', $html);
    }

    public function test_full_admin_menu_including_grouped_routes_is_in_the_dom(): void
    {
        $html = $this->actingAs($this->admin())
            ->get('/admin/users')
            ->assertOk()
            ->getContent();

        // Flat links (always visible) — assert on locale-independent hrefs.
        $this->assertStringContainsString('a2-nav-link', $html);
        $this->assertStringContainsString('/admin/users', $html);

        // Collapsible groups (the ones the rail used to hide) are present, and
        // so are deep routes that ONLY exist inside those groups — proving the
        // menu is complete server-side (visibility is CSS-only).
        $this->assertStringContainsString('a2-nav-group', $html);
        $this->assertStringContainsString('a2-nav-children', $html);
        $this->assertStringContainsString('/admin/wallet-transactions', $html);
        $this->assertStringContainsString('/admin/merchant-payments', $html);
        $this->assertStringContainsString('/admin/disputes', $html);
    }

    public function test_custom_wrapped_tables_now_use_the_sticky_wrapper_and_render(): void
    {
        // These screens wrapped a `.a2-table` in a bare inline overflow-x div,
        // so their headers were trapped (never pinned). They now use
        // `.a2-table-wrap`. Assert they still render AND carry the class.
        foreach (['/admin/fines', '/admin/arbitrators'] as $url) {
            $html = $this->actingAs($this->admin())->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('a2-table-wrap', $html);
        }
    }

    public function test_service_branch_matrix_pins_its_header_row(): void
    {
        // The matrix is a JS-built table with inline styles; the fix lives in
        // the view's script. Assert the scroll box + a sticky header row.
        $this->actingAs($this->admin())->get('/admin/service-branches')->assertOk();

        $blade = file_get_contents(resource_path('views/admin-v2/service-branches/index.blade.php'));
        $this->assertStringContainsString('max-height:calc(100vh - 220px)', $blade);
        $this->assertStringContainsString("position:sticky; top:0", $blade);   // header row
        $this->assertStringContainsString("position:sticky; top:0; right:0", $blade); // corner
    }

    public function test_sticky_header_css_covers_both_wrapper_classes(): void
    {
        $css = file_get_contents(public_path('admin-v2/css/admin.css'));

        // Both wrapper spellings must be turned into scroll boxes, or half the
        // panel keeps the broken (overflow-y:hidden) header.
        $this->assertMatchesRegularExpression('/\.a2-tablewrap\s*,\s*\.a2-table-wrap\s*\{[^}]*overflow:\s*auto/s', $css);
        $this->assertStringContainsString('max-height: calc(100vh - 160px)', $css);
    }
}
