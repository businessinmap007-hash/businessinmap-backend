<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Requests\HomeServiceRequestKind;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «أحتاج فنيًا» (the third kind on the requests-and-offers core): the customer names the trade and the job, only the
 * craftsmen OF THAT TRADE in his governorate are told and may answer with a quote, and the one he picks becomes a visit
 * appointment at the quoted price. Rolls back.
 */
class HomeServiceRequestsTest extends TestCase
{
    use DatabaseTransactions;

    private int $plumber;
    private int $electrician;
    private object $city;
    private object $otherCity;
    private object $farCity;

    protected function setUp(): void
    {
        parent::setUp();

        $ids = DB::table('category_children_master')->whereIn('name_ar', ['سباك', 'كهربائي'])->pluck('id', 'name_ar');

        if (! isset($ids['سباك'], $ids['كهربائي'])) {
            $this->markTestSkipped('Needs the plumber and electrician trades.');
        }

        $this->plumber = (int) $ids['سباك'];
        $this->electrician = (int) $ids['كهربائي'];

        $cities = DB::table('cities')->orderBy('id')->limit(400)->get();
        $this->city = $cities->first();
        $this->otherCity = $cities->first(fn ($c) => (int) $c->governorate_id === (int) $this->city->governorate_id && $c->id !== $this->city->id);
        $this->farCity = $cities->first(fn ($c) => (int) $c->governorate_id !== (int) $this->city->governorate_id);

        if (! $this->otherCity || ! $this->farCity) {
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

    private function craftsman(string $tag, int $trade, ?object $city = null): User
    {
        $city ??= $this->city;

        return $this->user(User::TYPE_BUSINESS, $tag, ['category_id' => 5, 'category_child_id' => $trade, 'governorate_id' => $city->governorate_id, 'city_id' => $city->id]);
    }

    private function job(array $extra = []): array
    {
        return $extra + [
            'kind' => 'home_service',
            'trade_child_id' => $this->plumber,
            'governorate_id' => $this->city->governorate_id,
            'city_id' => $this->city->id,
            'details' => 'The kitchen tap leaks under the sink',
        ];
    }

    public function test_the_trades_to_pick_from_are_listed_and_a_craftsman_is_told_which_kind_he_answers(): void
    {
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'cust'));
        $trades = collect($this->getJson('/api/v2/service-requests/home-trades')->assertOk()->json('data.trades'));
        $this->assertTrue($trades->pluck('id')->contains($this->plumber));
        $this->assertTrue($trades->pluck('id')->contains($this->electrician));

        Sanctum::actingAs($this->craftsman('plumb', $this->plumber));
        $this->getJson('/api/v2/business/service-requests/kinds')->assertOk()->assertJsonPath('data.kinds', ['home_service']);
    }

    public function test_only_the_plumbers_of_the_governorate_are_told_and_may_answer_an_electrician_or_a_far_plumber_may_not(): void
    {
        $near = $this->craftsman('near', $this->plumber, $this->city);
        $same = $this->craftsman('same', $this->plumber, $this->otherCity);
        $electrician = $this->craftsman('elec', $this->electrician);
        $far = $this->craftsman('far', $this->plumber, $this->farCity);

        $client = $this->user(User::TYPE_CLIENT, 'cust');
        Sanctum::actingAs($client);
        $id = $this->postJson('/api/v2/service-requests', $this->job())->assertCreated()->json('data.request.id');

        $told = fn (User $u) => AppNotification::where('user_id', $u->id)->where('actor_id', $client->id)->exists();
        $this->assertTrue($told($near));
        $this->assertTrue($told($same));
        $this->assertFalse($told($electrician));
        $this->assertFalse($told($far));

        foreach ([$electrician, $far] as $stranger) {
            Sanctum::actingAs($stranger);
            $this->getJson('/api/v2/business/service-requests?kind=home_service')->assertOk()->assertJsonPath('data.total', 0);
            $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 150])->assertStatus(422)->assertJsonValidationErrors('request');
        }

        // the plumber sees it, with the customer's first name only and no address
        Sanctum::actingAs($near);
        $row = $this->getJson('/api/v2/business/service-requests?kind=home_service')->assertOk()->assertJsonPath('data.total', 1)->json('data.data.0');
        $this->assertSame($id, $row['id']);
        $this->assertArrayNotHasKey('address', $row);
    }

    public function test_the_quote_he_picks_becomes_a_visit_booking_at_that_price_and_the_others_are_turned_down(): void
    {
        $a = $this->craftsman('a', $this->plumber);
        $b = $this->craftsman('b', $this->plumber);

        $client = $this->user(User::TYPE_CLIENT, 'cust');
        Sanctum::actingAs($client);
        $id = $this->postJson('/api/v2/service-requests', $this->job(['max_price' => 300]))->assertCreated()->json('data.request.id');

        Sanctum::actingAs($a);
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 250, 'message' => 'Tomorrow morning'])->assertSuccessful();
        Sanctum::actingAs($b);
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['accepts_budget' => true])->assertSuccessful();

        Sanctum::actingAs($client);
        $detail = $this->getJson("/api/v2/service-requests/{$id}")->assertOk();
        $offers = $detail->json('data.request.offers');
        $this->assertCount(2, $offers);
        $chosen = collect($offers)->firstWhere('price', 250.0);

        $done = $this->postJson("/api/v2/service-requests/{$id}/offers/{$chosen['id']}/accept")->assertOk();
        $booking = Booking::findOrFail($done->json('data.booking_id'));

        $this->assertSame($a->id, (int) $booking->business_id);
        $this->assertSame($client->id, (int) $booking->user_id);
        $this->assertEquals(250.0, (float) $booking->price);
        $this->assertSame('accepted', $booking->status);
        $this->assertSame('The kitchen tap leaks under the sink', $booking->notes);
        $this->assertSame('rejected', DB::table('service_request_offers')->where('service_request_id', $id)->where('business_id', $b->id)->value('status'));
    }

    public function test_a_trade_that_does_not_come_home_and_a_city_of_another_governorate_are_refused(): void
    {
        $other = (int) DB::table('category_children_master')->where('name_ar', 'معرض سيارات')->value('id');
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'cust'));

        $this->postJson('/api/v2/service-requests', $this->job(['trade_child_id' => $other ?: 999999]))->assertStatus(422)->assertJsonValidationErrors('trade_child_id');
        $this->postJson('/api/v2/service-requests', $this->job(['city_id' => $this->farCity->id]))->assertStatus(422)->assertJsonValidationErrors('city_id');
        $this->postJson('/api/v2/service-requests', $this->job(['details' => 'x']))->assertStatus(422)->assertJsonValidationErrors('details');
        $this->assertSame(0, ServiceRequest::where('kind', 'home_service')->count());
    }

    public function test_a_craftsman_on_a_break_is_not_told(): void
    {
        $resting = $this->craftsman('rest', $this->plumber);
        $resting->forceFill(['requests_paused_at' => now()])->save();

        $client = $this->user(User::TYPE_CLIENT, 'cust');
        Sanctum::actingAs($client);
        $this->postJson('/api/v2/service-requests', $this->job())->assertCreated();

        $this->assertFalse(AppNotification::where('user_id', $resting->id)->where('actor_id', $client->id)->exists());
        $this->assertCount(0, app(HomeServiceRequestKind::class)->eligible(ServiceRequest::where('requester_id', $client->id)->first())->where('id', $resting->id));
    }
}
