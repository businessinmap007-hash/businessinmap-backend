<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * «صفحة user عند فتحها تعمل loading لوقت طويل».
 *
 * It ran **1,045 queries** and 1.9 seconds to show fifty users, and shipped
 * 918KB. Three causes, all of them the same mistake in different clothes —
 * work proportional to the TAXONOMY rather than to the page being shown:
 *
 *  - two loops over all 337 children, one query each (674 queries);
 *  - `Schema::hasColumn` called inside one of those loops — 337 round trips to
 *    information_schema, 923ms, half the page, asking an unchanging question;
 *  - 380KB of options JSON shipped so a filter dropdown could cascade without
 *    a round trip, on every visit, for a filter most visits never touch.
 *
 * The edit form was worse still — 1,399 queries and 3.1 seconds to open ONE
 * user, building the grouped map for all 337 children to show the one he has.
 *
 * And underneath both, two view composers that run for EVERY view — the layout,
 * every partial, every component — each issuing its own query. A page built
 * from fourteen views paid twenty-eight Category queries and a dozen COUNTs for
 * one unchanging list and one badge.
 *
 * Now: list 12 queries / 227ms, edit 16 / 242ms, show 26 / 45ms. These tests
 * hold the SHAPE, not the numbers — a budget that cannot be exceeded, and pages
 * that no longer carry the map.
 */
class AdminUsersPagePerformanceTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $admin = User::query()->orderBy('id')->firstOrFail();
        Bouncer::allow($admin)->to(AdminAbility::ACCESS);
        Bouncer::allow($admin)->to(AdminAbility::USERS);
        Bouncer::refresh();

        return $admin;
    }

    /**
     * A budget, not a measurement.
     *
     * 120 is generous against the 32 it takes today and still an order of
     * magnitude under the 1,045 it used to. Anything that reintroduces a
     * per-child query trips it long before a human notices the page is slow.
     */
    public function test_the_users_page_stays_inside_a_query_budget(): void
    {
        $this->assertWithinBudget(route('admin.users.index'), 120, 'the list');
    }

    /**
     * The edit form was the worse of the two: **1,399 queries and 3.1 seconds**
     * to open ONE user, because it built the grouped option map for all 337
     * children — four queries each — to show the one the user actually has.
     */
    public function test_the_edit_form_stays_inside_a_query_budget(): void
    {
        $business = User::query()->where('type', 'business')->firstOrFail();

        $this->assertWithinBudget(route('admin.users.edit', $business->id), 120, 'the edit form');
    }

    public function test_the_user_page_stays_inside_a_query_budget(): void
    {
        $business = User::query()->where('type', 'business')->firstOrFail();

        $this->assertWithinBudget(route('admin.users.show', $business->id), 120, 'the user page');
    }

    private function assertWithinBudget(string $url, int $budget, string $what): void
    {
        $admin = $this->admin();

        $count = 0;
        DB::listen(function () use (&$count) { $count++; });

        $this->actingAs($admin)->get($url)->assertOk();

        $this->assertLessThan(
            $budget,
            $count,
            "{$what} ran {$count} queries — something is querying per child, or per view, again"
        );
    }

    /** The taxonomy must not ride along in the HTML. */
    public function test_neither_page_ships_the_whole_option_catalog(): void
    {
        $business = User::query()->where('type', 'business')->firstOrFail();

        foreach ([
            route('admin.users.index'),
            route('admin.users.edit', $business->id),
        ] as $url) {
            $html = $this->actingAs($this->admin())->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('optionCatalog', $html, "the whole map is back on {$url}");
            $this->assertStringNotContainsString('serviceCatalog', $html);

            // …and the cascade still has its door.
            $this->assertStringContainsString('CATALOG_URL', $html);
            $this->assertStringContainsString(json_encode(route('admin.users.catalog', [], false)), $html);
        }
    }

    /** The edit form needs the GROUPED shape; the filter needs the flat one. */
    public function test_the_catalog_answers_in_both_shapes(): void
    {
        $childId = (int) DB::table('category_child_option')->value('child_id');

        if (! $childId) {
            $this->markTestSkipped('No child carries an option.');
        }

        $body = $this->actingAs($this->admin())
            ->getJson(route('admin.users.catalog', ['child_id' => $childId]))
            ->assertOk()
            ->json();

        foreach (['options', 'groups', 'ungrouped', 'services'] as $key) {
            $this->assertArrayHasKey($key, $body, "«{$key}» is missing — one of the two screens needs it");
        }

        $this->assertNotEmpty($body['groups'], 'the edit form would show no option groups');
    }

    /** What the eager map used to answer, the endpoint answers now. */
    public function test_the_catalog_endpoint_answers_for_one_child(): void
    {
        $childId = (int) DB::table('category_child_option')->value('child_id');

        if (! $childId) {
            $this->markTestSkipped('No child carries an option.');
        }

        $body = $this->actingAs($this->admin())
            ->getJson(route('admin.users.catalog', ['child_id' => $childId]))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('options', $body);
        $this->assertArrayHasKey('services', $body);
        $this->assertNotEmpty($body['options'], 'the child carries options and the endpoint returned none');
    }

    /** No child named, nothing to say — and no error. */
    public function test_the_catalog_endpoint_is_empty_without_a_child(): void
    {
        $this->actingAs($this->admin())
            ->getJson(route('admin.users.catalog'))
            ->assertOk()
            ->assertExactJson(['options' => [], 'groups' => [], 'ungrouped' => [], 'services' => []]);
    }

    /** «catalog» must not be read as a user id by the numeric show route. */
    public function test_the_catalog_route_is_not_swallowed_by_the_show_route(): void
    {
        $this->assertSame(
            'admin.users.catalog',
            app('router')->getRoutes()->match(
                \Illuminate\Http\Request::create(route('admin.users.catalog', [], false), 'GET')
            )->getName()
        );
    }

    /**
     * A new endpoint is a new door, and this one hands out taxonomy.
     *
     * The panel bounces a non-admin to the login screen rather than answering
     * 403, so what is asserted is that no catalog comes back — the status code
     * is the panel's business, the leak is ours.
     */
    public function test_the_catalog_endpoint_is_closed_to_a_non_admin(): void
    {
        $outsider = User::query()->where('type', 'client')->firstOrFail();

        $response = $this->actingAs($outsider)
            ->getJson(route('admin.users.catalog', ['child_id' => 1]));

        $this->assertNotSame(200, $response->getStatusCode(), 'a customer read the admin catalog');
        $this->assertStringNotContainsString('"options"', (string) $response->getContent());
    }
}
