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
 * centre — none of which decide what a category child may sell. Those moved
 * out, because selling BETWEEN businesses is a different job from deciding
 * what a business is allowed to offer.
 *
 * ── «جمع الخدمات فى شجرة واحدة» — the owner, 2026-08-19 ──────────────────
 *
 * The drawer was tidy and the SERVICE was still scattered. Following one
 * service meant four top-level groups: «الخدمات والتسعير» to configure it,
 * «العمليات» for its bookings and units, «الجدولة والخطوط» for trip
 * reservations, «التوصيل والطاولات» for tables. Nothing on the screen said
 * they were one thing.
 *
 * The group is now «الخدمات» and holds a SECTION PER SERVICE — define,
 * configure, then one section for each of booking, menu, delivery, schedules,
 * training/clinics/projects, and pricing last. «العمليات» keeps only what
 * crosses every service: disputes and chats.
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

    /**
     * One tree, a section per service — and the platform questions bracket it.
     *
     * Define what a service IS, configure which child may sell it, then a
     * section for each service, and what it costs last. The order is the order
     * an admin actually walks.
     */
    public function test_the_services_tree_holds_a_section_per_service(): void
    {
        $group = $this->group('الخدمات');

        $sections = collect($group['children'])->where('type', 'section')->pluck('label')->all();

        $this->assertSame('تعريف الخدمات', $sections[0] ?? null, 'the tree no longer opens with what a service is');
        $this->assertSame('التسعير والرسوم', end($sections), 'pricing is no longer last');

        foreach (['الحجز', 'المنيو والطاولات', 'التوصيل', 'خطوط التشغيل'] as $service) {
            $this->assertContains($service, $sections, "«{$service}» has no section of its own");
        }
    }

    /**
     * And the scattered groups are gone rather than emptied.
     *
     * A top-level group left standing with one leaf in it is the scattering
     * this change removed, still on the screen.
     */
    public function test_the_groups_the_services_were_scattered_across_are_gone(): void
    {
        $labels = collect($this->menu())->pluck('label')->all();

        foreach (['الخدمات والتسعير', 'الجدولة والخطوط', 'التوصيل والطاولات'] as $old) {
            $this->assertNotContains($old, $labels, "«{$old}» is still a group of its own");
        }
    }

    /**
     * Every service-specific screen sits under its service, not beside it.
     *
     * This is the assertion the reorganisation exists for: a screen that
     * belongs to one service and is reachable from anywhere else is the old
     * scattering coming back one entry at a time.
     */
    public function test_no_service_screen_lives_outside_the_services_tree(): void
    {
        $inTree = collect($this->leaves($this->group('الخدمات')))->pluck('route');

        $serviceScreens = [
            'admin.bookings.index',
            'admin.bookable-items.index',
            'admin.bookable-allocations.index',
            'admin.menu-items.index',
            'admin.business-tables.index',
            'admin.delivery.drivers.index',
            'admin.trip-schedules.index',
            'admin.training-plans.index',
            'admin.clinic-appointments.index',
        ];

        foreach ($serviceScreens as $route) {
            $this->assertContains($route, $inTree->all(), "{$route} is not in the services tree");
        }

        foreach ($this->menu() as $group) {
            if (($group['label'] ?? null) === 'الخدمات') {
                continue;
            }

            $elsewhere = collect($this->leaves($group))->pluck('route')->intersect($serviceScreens);

            $this->assertEmpty(
                $elsewhere->all(),
                "«{$group['label']}» still lists a service screen: " . $elsewhere->implode('، ')
            );
        }
    }

    /**
     * The screen existed and no menu entry ever pointed at it, so the only way
     * to set fees for many children at once was to know the URL.
     */
    public function test_the_bulk_fee_screen_is_reachable_at_last(): void
    {
        $routes = collect($this->leaves($this->group('الخدمات')))->pluck('route');

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

        /*
         * «مخصصات الحجز» left this list on 2026-08-19: an allocation is a
         * booking before it is a partnership — seats an intermediary holds for
         * its own customers — so it belongs beside what it reserves, and
         * test_no_service_screen_lives_outside_the_services_tree now holds it
         * there.
         */
        foreach ([
            'admin.business-partnerships.index',
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
        $stayed = collect($this->leaves($this->group('الخدمات')))->pluck('route');

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
