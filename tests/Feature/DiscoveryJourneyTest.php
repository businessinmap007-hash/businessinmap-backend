<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The marketplace's front door, walked the way the app walks it.
 *
 * This test is why the front door exists. `discovery/filters` and
 * `discovery/businesses` both REQUIRE `child_id`, and until now no v2 endpoint
 * returned one — so a real client could not reach a single business, with 434
 * categories and 304 specialties sitting in the database. The same shape as the
 * BIM-11.1 address bug: a required parameter with no discovery path. Nothing
 * caught it because every existing test handed itself a child_id straight out of
 * the database, which the app cannot do.
 *
 * The rule, as in MenuOrderJourneyTest: every id the client uses comes out of a
 * previous API RESPONSE. Rolls back.
 */
class DiscoveryJourneyTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'secret-password';

    /**
     * Laravel caches the resolved user for the whole test method, so swapping
     * the Bearer header does NOT re-authenticate — the first identity sticks
     * silently. See MenuOrderJourneyTest.
     */
    private function actingWithToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function makeUser(string $type): User
    {
        $user = new User();
        $user->name = $type === User::TYPE_BUSINESS ? 'نشاط الرحلة' : 'عميل الرحلة';
        $user->email = 'disc-' . uniqid() . '@example.test';
        $user->phone = '0106' . random_int(1000000, 9999999);
        $user->password = self::PASSWORD;
        $user->type = $type;
        $user->api_token = Str::random(80);
        $user->save();

        return $user->fresh();
    }

    private function tokenFor(User $user): string
    {
        return $this->postJson('/api/v2/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertOk()->json('token');
    }

    public function test_the_app_can_get_from_launch_to_a_specialty_it_can_search(): void
    {
        // ── Screen 1: what does this marketplace sell?
        $categories = $this->getJson('/api/v2/categories')
            ->assertOk()
            ->json('data.categories');

        $this->assertNotEmpty($categories, 'an app that cannot list categories cannot open');
        $this->assertNotEmpty($categories[0]['name_ar']);

        // ── Screen 2: pick one, see its specialties. This is the id discovery
        // needs, and the whole reason this endpoint had to exist.
        $specialties = $this->getJson('/api/v2/categories/' . $categories[0]['id'] . '/specialties')
            ->assertOk()
            ->json('data.specialties');

        $this->assertNotEmpty($specialties, 'a category with no reachable specialties is a dead end');
        $this->assertArrayHasKey('businesses', $specialties[0], 'the app must know which specialties are dead ends before offering them');

        // ── Screen 3: the id flows straight into discovery, unmodified.
        $this->getJson('/api/v2/discovery/filters?child_id=' . $specialties[0]['id'])->assertOk();
        $this->getJson('/api/v2/discovery/businesses?child_id=' . $specialties[0]['id'])->assertOk();
    }

    public function test_the_subscription_pricing_is_not_leaked_to_the_app(): void
    {
        // per_month / per_year are the abandoned subscription pricing.
        $body = $this->getJson('/api/v2/categories')->assertOk()->getContent();

        $this->assertStringNotContainsString('per_month', $body);
        $this->assertStringNotContainsString('per_year', $body);
    }

    public function test_a_customer_can_find_a_real_business_and_book_it(): void
    {
        [$childId, $serviceId, $business] = $this->seedSellableBusiness();

        $client = $this->makeUser(User::TYPE_CLIENT);
        $token = $this->tokenFor($client);

        // Walk it: categories → specialties → filters → businesses. The client
        // learns the business id the same way the app would.
        $specialties = $this->getJson('/api/v2/categories/' . $this->rootOf($childId) . '/specialties?sellable=1')
            ->assertOk()
            ->json('data.specialties');

        $ids = array_column($specialties, 'id');
        $this->assertContains($childId, $ids, 'a specialty someone actually sells must be listed as sellable');

        $filters = $this->getJson('/api/v2/discovery/filters?child_id=' . $childId)->assertOk()->json('data');
        $this->assertNotEmpty($filters['services'] ?? [], 'the app must learn the service_id it has to book with');

        // `businesses` is a paginator, so the rows sit under .data.
        $found = $this->getJson('/api/v2/discovery/businesses?child_id=' . $childId . '&service_id=' . $serviceId)
            ->assertOk()
            ->json('data.businesses.data');

        $this->assertContains(
            (int) $business->id,
            array_column($found, 'id'),
            'a business selling this specialty must be findable'
        );

        // ── Book it, with ids the app actually holds.
        $booking = $this->actingWithToken($token)->postJson('/api/v2/bookings', [
            'business_id' => (int) $business->id,
            'service_id' => (int) $serviceId,
            'date' => now()->addDays(2)->toDateString(),
            'time' => '14:00',
            'quantity' => 1,
        ])->assertSuccessful()->json('data.booking');

        $this->assertNotNull($booking['id'] ?? null);
        $this->assertSame(Booking::STATUS_PENDING, $booking['status']);

        // ── The business sees it and can accept.
        $this->actingWithToken($this->tokenFor($business))
            ->postJson('/api/v2/bookings/' . $booking['id'] . '/accept')
            ->assertSuccessful();

        $this->assertSame(
            Booking::STATUS_ACCEPTED,
            Booking::query()->find($booking['id'])->status,
            'the restaurant accepting must actually move the booking'
        );
    }

    /** The root category a specialty hangs off. */
    private function rootOf(int $childId): int
    {
        return (int) DB::table('category_parent_child')->where('child_id', $childId)->value('parent_id');
    }

    /**
     * A business that genuinely sells something, seeded the way the merchant
     * would set it up. Reuses an existing sellable specialty so the row shapes
     * are the real ones.
     *
     * @return array{0:int,1:int,2:User}
     */
    private function seedSellableBusiness(): array
    {
        $template = DB::table('business_service_prices')->where('is_active', 1)->first();

        if (! $template) {
            $this->markTestSkipped('Needs at least one active business_service_prices row to mirror.');
        }

        $business = $this->makeUser(User::TYPE_BUSINESS);

        // Discovery matches on BOTH: the business's own classification
        // (users.category_child_id) and an active price row for that child.
        // A price alone is invisible — a business belongs to one specialty.
        $business->category_child_id = (int) $template->child_id;
        $business->save();

        $row = (array) $template;
        unset($row['id']);
        $row['business_id'] = $business->id;
        $row['created_at'] = now();
        $row['updated_at'] = now();

        DB::table('business_service_prices')->insert($row);

        return [(int) $template->child_id, (int) $template->service_id, $business->fresh()];
    }
}
