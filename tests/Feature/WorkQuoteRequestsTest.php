<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «اطلب عرض سعر» (the fourth kind on the requests core): a job for a workshop or a factory to make to order. The makers of
 * the named trade answer with a price and the days they need; the maker may be of any governorate when the customer says
 * «nationwide». The picked quote becomes an appointment at the maker at that price. Rolls back.
 */
class WorkQuoteRequestsTest extends TestCase
{
    use DatabaseTransactions;

    private int $aluminium;
    private int $furnitureShop;
    private object $city;
    private object $farCity;

    protected function setUp(): void
    {
        parent::setUp();

        $ids = DB::table('category_children_master')->whereIn('name_ar', ['ألمونتال', 'ورشة أثاث ونجارة'])->pluck('id', 'name_ar');

        if (! isset($ids['ألمونتال'], $ids['ورشة أثاث ونجارة'])) {
            $this->markTestSkipped('Needs the aluminium factory and the furniture workshop trades.');
        }

        $this->aluminium = (int) $ids['ألمونتال'];
        $this->furnitureShop = (int) $ids['ورشة أثاث ونجارة'];

        $cities = DB::table('cities')->orderBy('id')->limit(400)->get();
        $this->city = $cities->first();
        $this->farCity = $cities->first(fn ($c) => (int) $c->governorate_id !== (int) $this->city->governorate_id);

        if (! $this->farCity) {
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

    private function maker(string $tag, int $trade, ?object $city = null): User
    {
        $city ??= $this->city;

        return $this->user(User::TYPE_BUSINESS, $tag, ['category_id' => 23, 'category_child_id' => $trade, 'governorate_id' => $city->governorate_id, 'city_id' => $city->id]);
    }

    private function job(array $extra = []): array
    {
        return $extra + [
            'kind' => 'work_quote',
            'trade_child_id' => $this->aluminium,
            'what' => 'Kitchen in aluminium',
            'details' => 'Eight metres of units with a sink corner and two glass doors',
            'governorate_id' => $this->city->governorate_id,
            'city_id' => $this->city->id,
        ];
    }

    public function test_the_makers_to_pick_from_are_listed_and_a_maker_is_told_which_kind_he_answers(): void
    {
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'cust'));
        $ids = collect($this->getJson('/api/v2/service-requests/work-trades')->assertOk()->json('data.trades'))->pluck('id');
        $this->assertTrue($ids->contains($this->aluminium));
        $this->assertTrue($ids->contains($this->furnitureShop));

        Sanctum::actingAs($this->maker('alu', $this->aluminium));
        $this->getJson('/api/v2/business/service-requests/kinds')->assertOk()->assertJsonPath('data.kinds', ['work_quote']);
    }

    public function test_by_default_only_the_makers_of_the_trade_in_his_governorate_are_told_and_may_answer(): void
    {
        $near = $this->maker('near', $this->aluminium);
        $other = $this->maker('furn', $this->furnitureShop);
        $far = $this->maker('far', $this->aluminium, $this->farCity);

        $client = $this->user(User::TYPE_CLIENT, 'cust');
        Sanctum::actingAs($client);
        $id = $this->postJson('/api/v2/service-requests', $this->job())->assertCreated()->json('data.request.id');

        $told = fn (User $u) => AppNotification::where('user_id', $u->id)->where('actor_id', $client->id)->exists();
        $this->assertTrue($told($near));
        $this->assertFalse($told($other));
        $this->assertFalse($told($far));

        Sanctum::actingAs($far);
        $this->getJson('/api/v2/business/service-requests?kind=work_quote')->assertOk()->assertJsonPath('data.total', 0);
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 9000])->assertStatus(422)->assertJsonValidationErrors('request');
    }

    public function test_nationwide_lets_a_factory_of_another_governorate_see_and_answer_it(): void
    {
        $near = $this->maker('near', $this->aluminium);
        $far = $this->maker('far', $this->aluminium, $this->farCity);

        $client = $this->user(User::TYPE_CLIENT, 'cust');
        Sanctum::actingAs($client);
        $id = $this->postJson('/api/v2/service-requests', $this->job(['nationwide' => true]))->assertCreated()->json('data.request.id');

        $this->assertTrue(AppNotification::where('user_id', $far->id)->where('actor_id', $client->id)->exists());
        $this->assertTrue(AppNotification::where('user_id', $near->id)->where('actor_id', $client->id)->exists());

        Sanctum::actingAs($far);
        $this->getJson('/api/v2/business/service-requests?kind=work_quote')->assertOk()->assertJsonPath('data.total', 1);
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 9000, 'eta_minutes' => 20 * 1440])->assertSuccessful();
    }

    public function test_the_quote_he_picks_becomes_an_appointment_at_that_price_with_the_days_carried_along(): void
    {
        $a = $this->maker('a', $this->aluminium);
        $b = $this->maker('b', $this->aluminium);

        $client = $this->user(User::TYPE_CLIENT, 'cust');
        Sanctum::actingAs($client);
        $id = $this->postJson('/api/v2/service-requests', $this->job(['quantity' => 1, 'unit' => 'kitchen', 'deadline' => now()->addDays(40)->toDateString(), 'max_price' => 20000]))->assertCreated()->json('data.request.id');

        Sanctum::actingAs($a);
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 18000, 'eta_minutes' => 21 * 1440, 'message' => 'Includes fitting'])->assertSuccessful();
        Sanctum::actingAs($b);
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 16500, 'eta_minutes' => 30 * 1440])->assertSuccessful();

        Sanctum::actingAs($client);
        $offers = collect($this->getJson("/api/v2/service-requests/{$id}")->assertOk()->json('data.request.offers'));
        $this->assertCount(2, $offers);
        $chosen = $offers->firstWhere('price', 18000.0);

        $done = $this->postJson("/api/v2/service-requests/{$id}/offers/{$chosen['id']}/accept")->assertOk();
        $booking = Booking::findOrFail($done->json('data.booking_id'));

        $this->assertSame($a->id, (int) $booking->business_id);
        $this->assertEquals(18000.0, (float) $booking->price);
        $this->assertSame(21, (int) data_get($booking->meta, 'work_quote.days'));
        $this->assertSame('Kitchen in aluminium', data_get($booking->meta, 'work_quote.what'));
        $this->assertSame('rejected', DB::table('service_request_offers')->where('service_request_id', $id)->where('business_id', $b->id)->value('status'));
    }

    public function test_a_trade_that_does_not_work_to_order_a_short_spec_and_a_past_deadline_are_refused(): void
    {
        $shop = (int) DB::table('category_children_master')->where('name_ar', 'معرض سيارات')->value('id');
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'cust'));

        $this->postJson('/api/v2/service-requests', $this->job(['trade_child_id' => $shop ?: 999999]))->assertStatus(422)->assertJsonValidationErrors('trade_child_id');
        $this->postJson('/api/v2/service-requests', $this->job(['details' => 'too short']))->assertStatus(422)->assertJsonValidationErrors('details');
        $this->postJson('/api/v2/service-requests', $this->job(['deadline' => now()->subDays(2)->toDateString()]))->assertStatus(422)->assertJsonValidationErrors('deadline');
    }

    public function test_a_ride_offer_still_cannot_claim_more_than_a_day_of_travel_time(): void
    {
        $client = $this->user(User::TYPE_CLIENT, 'cust');
        $driver = $this->user(User::TYPE_BUSINESS, 'drv', ['category_id' => 13, 'category_child_id' => (int) DB::table('category_children_master')->where('name_ar', 'خدمة ليموزين')->value('id'), 'governorate_id' => $this->city->governorate_id, 'city_id' => $this->city->id]);

        Sanctum::actingAs($client);
        $id = $this->postJson('/api/v2/service-requests', [
            'kind' => 'ride', 'passengers' => 1,
            'origin' => ['governorate_id' => $this->city->governorate_id, 'city_id' => $this->city->id],
            'destination' => ['governorate_id' => $this->farCity->governorate_id, 'city_id' => $this->farCity->id],
            'distance_km' => 100,
        ])->assertCreated()->json('data.request.id');

        Sanctum::actingAs($driver);
        $this->postJson("/api/v2/business/service-requests/{$id}/offers", ['price' => 100, 'eta_minutes' => 20 * 1440])->assertStatus(422)->assertJsonValidationErrors('eta_minutes');
    }
}
