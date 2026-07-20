<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Renders every parameter-free admin GET screen and asserts none of them blow
 * up. Most of the 173 admin-v2 blades had no coverage at all, so a mechanical
 * edit across them (wrapping labels in __()) could break a template that no
 * test ever loads. This is the net for that: a Blade syntax error, an
 * undefined variable, or a bad __() call surfaces as a 500 here.
 *
 * It asserts "not a server error" rather than 200 — a screen may legitimately
 * redirect or 404 on an empty dev database.
 */
class AdminV2ScreensSmokeTest extends TestCase
{
    use DatabaseTransactions;

    private function superAdmin(): User
    {
        $admin = User::query()
            ->where('type', 'admin')
            ->orderBy('id')
            ->first();

        return $admin ?: $this->markTestSkipped('Needs an admin user.');
    }

    public function test_every_parameter_free_admin_screen_renders(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        $failures = [];
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            // Parameter-free admin screens only — a bound {id} would need a
            // fixture per screen, which is a different test's job.
            if (! str_starts_with($uri, 'admin') || str_contains($uri, '{')) {
                continue;
            }

            // Logout would end the session for every screen after it.
            if (str_contains($uri, 'logout')) {
                continue;
            }

            $checked++;

            $response = $this->get('/' . ltrim($uri, '/'));

            if ($response->getStatusCode() >= 500) {
                $failures[] = $uri . ' -> ' . $response->getStatusCode();
            }
        }

        $this->assertGreaterThan(50, $checked, 'Expected to sweep the admin panel.');
        $this->assertSame([], $failures, 'Admin screens returning a server error.');
    }
}
