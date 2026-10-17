<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Requests\RideRequestKind;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The requests-and-offers core, with a limousine ride as its first kind (owner, 2026-10-17): the customer asks for a
 * ride with no map — a city each way, a starting price from the distance — the limousine drivers of the origin's
 * governorate answer with their own prices, he picks one and it becomes an ordinary booking. Rolls back.
 */
class RideRequestsTest extends TestCase
{
    use DatabaseTransactions;

    private object $cairo;        // a city with coordinates
    private object $cairo2;       // another city of the same governorate
    private object $far;          // a city of ANOTHER governorate, with coordinates

    protected function setUp(): void
    {
        parent::setUp();

        if (RideRequestKind::tradeId() <= 0) {
            $this->markTestSkipped('Needs the limousine trade.');
        }

        $cities = DB::table('cities')->whereNotNull('latitude')->orderBy('id')->limit(400)->get();
        $this->cairo = $cities->first();
        $this->cairo2 = $cities->first(fn ($c) => (int) $c->governorate_id === (int) $this->cairo->governorate_id && $c->id !== $this->cairo->id);
        $this->far = $cities->first(fn ($c) => (int) $c->governorate_id !== (int) $this->cairo->governorate_id);

        if (! $this->cairo2 || ! $this->far) {
            $this->markTestSkipped('Needs cities in two governorates.');
        }
    }

    private function user(string $type, string $tag, array $extra = []): User
    {
        $u = new User();
        $u->forceFill($extra + [
            'name' => $tag . ' Name ' . Str::random(4),
            'email' => strtolower($tag) . '-' . uniqid() . '@example.test',
            'phone' => '010' . random_int(10000000, 99999999),
            'password' => 'secret-password',
            'type' => $type,
            'api_token' => Str::random(80),
        ])->save();

        return $u;
    }

    private function driver(string $tag, ?int $governorateId = null, ?int $childId = null): User
    {
        return $this->user(User::TYPE_BUSINESS, $tag, [
            'category_id' => 5,
            'category_child_id' => $childId ?? RideRequestKind::tradeId(),
            'governorate_id' => $governorateId ?? $this->cairo->governorate_id,
            'city_id' => $this->cairo->id,
            'latitude' => $this->cairo->latitude,
            'longitude' => $this->cairo->longitude,
        ]);
    }

    private function ride(array $extra = []): array
    {
        return $extra + [
            'kind' => 'ride',
            'origin' => ['governorate_id' => $this->cairo->governorate_id, 'city_id' => $this->cairo->id, 'label' => 'Tahrir'],
            'destination' => ['governorate_id' => $this->far->governorate_id, 'city_id' => $this->far->id, 'label' => 'Station'],
            'passengers' => 2,
        ];
    }

    public function test_the_distance_and_the_suggested_price_come_from_the_cities_and_a_same_city_ride_needs_the_customers_word(): void
    {
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'cust'));

        $far = $this->postJson('/api/v2/service-requests/quote', ['origin_city_id' => $this->cairo->id, 'destination_city_id' => $this->far->id])
            ->assertOk()->assertJsonPath('data.known', true)->assertJsonPath('data.source', 'cities');
        $this->assertGreaterThan(5, $far->json('data.distance_km'));
        $this->assertGreaterThanOrEqual((float) config('bim.rides.min_fare'), $far->json('data.suggested_price'));

        // one city both ways: nothing to measure — he is asked how far
        $this->postJson('/api/v2/service-requests/quote', ['origin_city_id' => $this->cairo->id, 'destination_city_id' => $this->cairo->id])
            ->assertOk()->assertJsonPath('data.needs_distance', true)->assertJsonPath('data.suggested_price', null);

        $this->postJson('/api/v2/service-requests/quote', ['origin_city_id' => $this->cairo->id, 'destination_city_id' => $this->cairo->id, 'distance_km' => 12])
            ->assertOk()->assertJsonPath('data.source', 'manual')->assertJsonPath('data.distance_km', 12);
    }

    public function test_a_ride_is_posted_with_the_suggested_price_and_the_drivers_of_the_governorate_are_told(): void
    {
        $near = $this->driver('near');
        $other = $this->driver('otherGov', (int) $this->far->governorate_id);
        $notDriver = $this->driver('shop', null, (int) DB::table('category_children_master')->where('id', '!=', RideRequestKind::tradeId())->value('id'));
        $customer = $this->user(User::TYPE_CLIENT, 'cust');

        Sanctum::actingAs($customer);
        $res = $this->postJson('/api/v2/service-requests', $this->ride())->assertCreated()
            ->assertJsonPath('data.request.status', 'open')
            ->assertJsonPath('data.request.kind', 'ride')
            ->assertJsonPath('data.request.params.passengers', 2);

        $this->assertSame($res->json('data.request.suggested_price'), $res->json('data.request.budget'));

        $id = (int) $res->json('data.request.id');
        $told = fn (User $u) => AppNotification::query()->where('user_id', $u->id)->where('source_type', 'service_request_new')->where('source_id', $id)->count();
        $this->assertSame(1, $told($near));
        $this->assertSame(0, $told($other), 'another governorate is not asked');
        $this->assertSame(0, $told($notDriver), 'another trade is not asked');

        // a same-city ride without a distance is refused, with it it goes through
        $same = $this->ride(['destination' => ['governorate_id' => $this->cairo->governorate_id, 'city_id' => $this->cairo->id]]);
        $this->postJson('/api/v2/service-requests', $same)->assertStatus(422)->assertJsonValidationErrors('distance_km');
        $this->postJson('/api/v2/service-requests', $same + ['distance_km' => 8, 'price' => 90])->assertCreated()->assertJsonPath('data.request.budget', 90);
    }

    public function test_drivers_answer_with_their_own_prices_and_the_customer_sees_them_all_but_a_driver_sees_only_his(): void
    {
        $a = $this->driver('A');
        $b = $this->driver('B');
        $outsider = $this->driver('C', (int) $this->far->governorate_id);
        $customer = $this->user(User::TYPE_CLIENT, 'cust');

        Sanctum::actingAs($customer);
        $id = (int) $this->postJson('/api/v2/service-requests', $this->ride(['price' => 200]))->assertCreated()->json('data.request.id');

        Sanctum::actingAs($a);
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 240, 'eta_minutes' => 5, 'message' => 'on my way'])->assertCreated()
            ->assertJsonPath('data.request.my_offer.price', 240);

        Sanctum::actingAs($b);
        // «أقبل سعرك»: the customer's own price as it stands
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['accepts_budget' => true])->assertCreated()
            ->assertJsonPath('data.request.my_offer.price', 200)->assertJsonPath('data.request.my_offer.accepts_budget', true);

        // a driver sees the request, his own offer, and only the customer's first name — never the others' prices
        $list = $this->getJson('/api/v2/business/service-requests')->assertOk();
        $row = collect($list->json('data.data'))->firstWhere('id', $id);
        $this->assertSame(200.0, (float) $row['my_offer']['price']);
        $this->assertSame(2, $row['offers_count']);
        $this->assertSame('cust', $row['requester']['first_name']);
        $this->assertStringNotContainsString('240', json_encode($row));

        // a driver of another governorate cannot answer, and an offer needs a price
        Sanctum::actingAs($outsider);
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 150])->assertStatus(422);
        Sanctum::actingAs($b);
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", [])->assertStatus(422)->assertJsonValidationErrors('price');

        // answering again replaces the offer instead of piling up a second one
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 190])->assertCreated();
        $this->assertSame(2, DB::table('service_request_offers')->where('service_request_id', $id)->count());

        // the customer sees both, cheapest first, with who made them
        Sanctum::actingAs($customer);
        $show = $this->getJson("/api/v2/service-requests/{$id}")->assertOk();
        $prices = collect($show->json('data.request.offers'))->pluck('price')->map(fn ($p) => (float) $p)->all();
        $this->assertSame([190.0, 240.0], $prices);
        $this->assertSame($b->name, $show->json('data.request.offers.0.business.name'));
        $this->assertSame(2, $show->json('data.request.offers_count'));
    }

    public function test_choosing_an_offer_makes_a_booking_and_closes_the_request(): void
    {
        $a = $this->driver('A');
        $b = $this->driver('B');
        $customer = $this->user(User::TYPE_CLIENT, 'cust');

        Sanctum::actingAs($customer);
        $id = (int) $this->postJson('/api/v2/service-requests', $this->ride())->assertCreated()->json('data.request.id');

        Sanctum::actingAs($a);
        $offerA = (int) DB::table('service_request_offers')->insertGetId(['service_request_id' => $id, 'business_id' => $a->id, 'price' => 250, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($b);
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 230])->assertCreated();
        $offerB = (int) DB::table('service_request_offers')->where('business_id', $b->id)->value('id');

        // somebody else's request is not his to settle
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'stranger'));
        $this->postJson("/api/v2/service-requests/{$id}/offers/{$offerB}/accept")->assertNotFound();

        Sanctum::actingAs($customer);
        $done = $this->postJson("/api/v2/service-requests/{$id}/offers/{$offerB}/accept")->assertOk()->assertJsonPath('data.request.status', 'accepted');
        $bookingId = (int) $done->json('data.booking_id');

        $booking = Booking::findOrFail($bookingId);
        $this->assertSame((int) $customer->id, (int) $booking->user_id);
        $this->assertSame((int) $b->id, (int) $booking->business_id);
        $this->assertSame('accepted', $booking->status);
        $this->assertEqualsWithDelta(230.0, (float) $booking->price, 0.001);
        $this->assertStringStartsWith('مشوار:', $booking->title());

        // the others are turned down; a second pick is refused
        $this->assertSame('rejected', DB::table('service_request_offers')->where('id', $offerA)->value('status'));
        $this->assertSame('accepted', DB::table('service_request_offers')->where('id', $offerB)->value('status'));
        $this->postJson("/api/v2/service-requests/{$id}/offers/{$offerA}/accept")->assertStatus(422);
        $this->assertSame(1, AppNotification::query()->where('user_id', $b->id)->where('source_type', 'service_request_accepted')->count());

        // from here it is an ordinary booking: it shows in both lists, and attendance is by both, the customer first
        $mine = collect($this->getJson('/api/v2/bookings?scope=my&per_page=50')->assertOk()->json('data.bookings.data'))->firstWhere('id', $bookingId);
        $this->assertStringStartsWith('مشوار:', $mine['title']);

        Sanctum::actingAs($b);
        $this->postJson("/api/v2/bookings/{$bookingId}/business-confirm")->assertStatus(422);
        Sanctum::actingAs($customer);
        $this->postJson("/api/v2/bookings/{$bookingId}/client-confirm")->assertOk();
        Sanctum::actingAs($b);
        $this->postJson("/api/v2/bookings/{$bookingId}/business-confirm")->assertOk();
    }

    public function test_the_customer_may_change_the_price_cancel_and_a_cancelled_or_expired_request_takes_no_offers(): void
    {
        $a = $this->driver('A');
        $customer = $this->user(User::TYPE_CLIENT, 'cust');

        Sanctum::actingAs($customer);
        $id = (int) $this->postJson('/api/v2/service-requests', $this->ride())->assertCreated()->json('data.request.id');

        $this->postJson("/api/v2/service-requests/{$id}/price", ['price' => 333])->assertOk()->assertJsonPath('data.request.budget', 333);
        $this->postJson("/api/v2/service-requests/{$id}/price", ['price' => 0])->assertStatus(422);

        Sanctum::actingAs($a);
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 300])->assertCreated();

        Sanctum::actingAs($customer);
        $this->postJson("/api/v2/service-requests/{$id}/cancel")->assertOk()->assertJsonPath('data.request.status', 'cancelled');
        $this->assertSame('rejected', DB::table('service_request_offers')->where('service_request_id', $id)->value('status'));
        $this->postJson("/api/v2/service-requests/{$id}/cancel")->assertStatus(422);

        Sanctum::actingAs($a);
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 100])->assertStatus(422);

        // an immediate ride that ran out of time reads as expired and takes no offers either
        Sanctum::actingAs($customer);
        $late = (int) $this->postJson('/api/v2/service-requests', $this->ride())->assertCreated()->json('data.request.id');
        ServiceRequest::query()->whereKey($late)->update(['expires_at' => now()->subMinute()]);

        $this->getJson("/api/v2/service-requests/{$late}")->assertOk()->assertJsonPath('data.request.status', 'expired');
        Sanctum::actingAs($a);
        $this->postJson("/api/v2/business/service-requests/{$late}/offers", ['price' => 100])->assertStatus(422);
        $this->assertNull(collect($this->getJson('/api/v2/business/service-requests')->json('data.data'))->firstWhere('id', $late));
    }
}
