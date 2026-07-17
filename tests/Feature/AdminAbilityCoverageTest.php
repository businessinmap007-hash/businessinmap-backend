<?php

namespace Tests\Feature;

use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * BIM-14.1 — the invariant that keeps the panel closed.
 *
 * `admin.v2` answers "are you an admin?". A `can:` ability answers "which
 * admin?". Every route needs both, and the pairing is what this test defends:
 * without it, the next route someone adds is open to anyone who can reach the
 * panel, silently, and nobody finds out until it matters.
 *
 * No allowlist by construction: login, logout and the payment callback sit
 * outside the `admin.v2` group, so filtering on that middleware excludes them
 * without anyone having to remember to.
 */
class AdminAbilityCoverageTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<int, RoutingRoute> */
    private function guardedAdminRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RoutingRoute $r) => in_array('admin.v2', $r->gatherMiddleware(), true)
        ));
    }

    private function abilitiesOf(RoutingRoute $route): array
    {
        $abilities = [];

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'can:')) {
                $abilities[] = explode(',', substr($middleware, 4))[0];
            }
        }

        return $abilities;
    }

    public function test_every_admin_route_requires_an_ability(): void
    {
        $unguarded = [];

        foreach ($this->guardedAdminRoutes() as $route) {
            if ($this->abilitiesOf($route) === []) {
                $unguarded[] = $route->getName() ?: $route->uri();
            }
        }

        $this->assertSame(
            [],
            $unguarded,
            "These AdminV2 routes are reachable by any admin — give each one a can: ability:\n  "
                . implode("\n  ", $unguarded)
        );
    }

    public function test_the_admin_surface_is_actually_covered(): void
    {
        // Guards the guard: if the filter above ever stopped matching, the
        // coverage test would pass by checking nothing at all.
        $this->assertGreaterThan(300, count($this->guardedAdminRoutes()));
    }

    public function test_every_ability_used_on_a_route_is_a_declared_one(): void
    {
        $used = [];

        foreach ($this->guardedAdminRoutes() as $route) {
            foreach ($this->abilitiesOf($route) as $ability) {
                $used[$ability] = true;
            }
        }

        // A typo in a can: string fails closed — silently denying instead of
        // erroring — so an undeclared ability must be caught here.
        $this->assertSame(
            [],
            array_values(array_diff(array_keys($used), AdminAbility::ALL)),
            'abilities used on a route but not declared in AdminAbility::ALL'
        );
    }

    public function test_every_declared_ability_is_actually_used(): void
    {
        $used = [];

        foreach ($this->guardedAdminRoutes() as $route) {
            foreach ($this->abilitiesOf($route) as $ability) {
                $used[$ability] = true;
            }
        }

        // A declared-but-unused ability is a permission that looks meaningful
        // when granted and controls nothing.
        $this->assertSame(
            [],
            array_values(array_diff(AdminAbility::ALL, array_keys($used))),
            'abilities declared but guarding no route'
        );
    }

    public function test_money_moving_actions_require_the_money_ability(): void
    {
        // The point of the whole exercise: these live on dispute, guarantee and
        // booking screens, but they move somebody's money, so their own domain
        // ability must not be enough on its own.
        $moneyMovers = [
            'admin.disputes.resolve.release-business',
            'admin.disputes.resolve.refund-client',
            'admin.disputes.resolve.split',
            'admin.guarantees.unlock-to-balance',
            'admin.bookings.deposit.release',
            'admin.bookings.deposit.refund',
            'admin.bookings.deposit.freeze',
            'admin.wallet-ops.recharge',
            'admin.payment-settings.update',
        ];

        foreach ($moneyMovers as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, $name . ' should exist');
            $this->assertContains(
                AdminAbility::MONEY,
                $this->abilitiesOf($route),
                $name . ' moves money and must require ' . AdminAbility::MONEY
            );
        }
    }

    public function test_dispute_triage_does_not_require_the_money_ability(): void
    {
        // The other half: a support agent must be able to work the queue.
        foreach (['admin.disputes.index', 'admin.disputes.show', 'admin.disputes.under-review', 'admin.disputes.resolve.no-action'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, $name . ' should exist');
            $this->assertNotContains(
                AdminAbility::MONEY,
                $this->abilitiesOf($route),
                $name . ' is triage — requiring ' . AdminAbility::MONEY . ' would defeat the split'
            );
        }
    }
}
