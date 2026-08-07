<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * «الخدمات والتسعير» had grown into a drawer for anything service-shaped:
 * seventeen entries in four sections, mixing the taxonomy configuration with
 * partnerships, offer boosts, follows, subscriptions and the notification
 * centre — none of which decide what a category child may sell.
 *
 * It is now the three questions an admin actually walks, in that order:
 * configure a child, define the service vocabulary, set what it costs. What
 * moved out got its own group, because selling BETWEEN businesses is a
 * different job from deciding what a business is allowed to offer.
 */
class AdminMenuShapeTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<int,array<string,mixed>> the menu as the blade builds it */
    private function menu(): array
    {
        $source = file_get_contents(resource_path('views/admin-v2/layouts/_partials/menu.blade.php'));

        preg_match('/\$menu = (\[.*?\n    \]);/s', $source, $m);

        $this->assertNotEmpty($m, 'the menu array could not be read');

        return eval("return {$m[1]};");
    }

    private function group(string $label): array
    {
        $group = collect($this->menu())->firstWhere('label', $label);

        $this->assertNotNull($group, "the «{$label}» group is gone");

        return $group;
    }

    /** @return array<int,array<string,mixed>> every leaf with a route */
    private function leaves(array $node): array
    {
        $out = [];

        foreach ($node['children'] ?? [] as $child) {
            if (($child['type'] ?? null) === 'section') {
                $out = array_merge($out, $this->leaves($child));

                continue;
            }

            $out[] = $child;
        }

        return $out;
    }

    /** A menu link to a route that does not exist is a 404 with a nice label. */
    public function test_every_menu_entry_points_at_a_route_that_exists(): void
    {
        foreach ($this->menu() as $group) {
            foreach (array_merge([$group], $this->leaves($group)) as $entry) {
                if (empty($entry['route'])) {
                    continue;
                }

                $this->assertTrue(
                    Route::has($entry['route']),
                    "«{$entry['label']}» points at the missing route {$entry['route']}"
                );
            }
        }
    }

    /** Three questions, three sections. */
    public function test_services_and_pricing_is_three_sections_of_three(): void
    {
        $group = $this->group('الخدمات والتسعير');

        $sections = collect($group['children'])->where('type', 'section');

        $this->assertCount(3, $sections);
        $this->assertSame(
            ['إعداد الابن', 'تعريف الخدمات', 'التسعير والرسوم'],
            $sections->pluck('label')->all()
        );

        $this->assertCount(9, $this->leaves($group), 'the drawer is filling up again');
    }

    /**
     * The screen existed and no menu entry ever pointed at it, so the only way
     * to set fees for many children at once was to know the URL.
     */
    public function test_the_bulk_fee_screen_is_reachable_at_last(): void
    {
        $routes = collect($this->leaves($this->group('الخدمات والتسعير')))->pluck('route');

        $this->assertContains('admin.category-child-service-fees.bulk.edit', $routes->all());

        $admin = User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin account to act as.');
        }

        $this->actingAs($admin)
            ->get(route('admin.category-child-service-fees.bulk.edit', [], false))
            ->assertOk();
    }

    /** A retired screen must not keep a door in the menu. */
    public function test_the_retired_matrix_is_not_offered_anywhere(): void
    {
        foreach ($this->menu() as $group) {
            foreach ($this->leaves($group) as $entry) {
                $this->assertNotSame(
                    'admin.service-catalog-matrix.index',
                    $entry['route'] ?? null,
                    'the retired matrix is still in the menu'
                );
            }
        }
    }

    /** Commercial and marketing work moved out, and kept every screen. */
    public function test_the_commercial_screens_moved_out_intact(): void
    {
        $routes = collect($this->leaves($this->group('العروض والشراكات')))->pluck('route')->all();

        foreach ([
            'admin.business-partnerships.index',
            'admin.bookable-allocations.index',
            'admin.commercial-offers.index',
            'admin.offer-performance.index',
            'admin.offer-boost-packages.index',
            'admin.offer-follows.index',
            'admin.business-offers-subscriptions.form',
            'admin.notification-center.index',
        ] as $route) {
            $this->assertContains($route, $routes, "{$route} was lost in the move");
        }

        // And none of them stayed behind.
        $stayed = collect($this->leaves($this->group('الخدمات والتسعير')))->pluck('route');

        $this->assertEmpty(
            $stayed->intersect($routes)->all(),
            'a commercial screen is listed in both groups'
        );
    }

    /** The whole sidebar still renders for a real admin. */
    public function test_the_sidebar_renders(): void
    {
        $admin = User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin account to act as.');
        }

        $html = $this->actingAs($admin)
            ->get(route('admin.child-workbench.index', [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('إعداد الابن', $html);
        $this->assertStringContainsString('طاولة عمل الابن', $html);
    }
}
