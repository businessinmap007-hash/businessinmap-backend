<?php

namespace Tests\Feature;

use App\Models\TripReservation;
use App\Models\TripSchedule;
use App\Models\User;
use App\Services\Requests\RideRequestKind;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «راجع فاضي» (owner, 2026-10-18): a limousine driver going back empty offers the way home at a lower price he sets;
 * the customer asking for that ride is shown the offers and books one with the ordinary trip reservation. Rolls back.
 */
class EmptyReturnTest extends TestCase
{
    use DatabaseTransactions;

    private object $a;
    private object $b;

    protected function setUp(): void
    {
        parent::setUp();

        if (RideRequestKind::tradeId() <= 0) {
            $this->markTestSkipped('Needs the limousine trade.');
        }

        $cities = DB::table('cities')->whereNotNull('latitude')->orderBy('id')->limit(400)->get();
        $this->a = $cities->first();
        $this->b = $cities->first(fn ($c) => (int) $c->governorate_id !== (int) $this->a->governorate_id);

        if (! $this->b) {
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

    private function driver(string $tag = 'drv'): User
    {
        return $this->user(User::TYPE_BUSINESS, $tag, ['category_id' => 5, 'category_child_id' => RideRequestKind::tradeId(), 'governorate_id' => $this->b->governorate_id, 'city_id' => $this->b->id]);
    }

    /** a return from b to a: the driver took somebody to b and goes home to a */
    private function payload(array $extra = []): array
    {
        return $extra + [
            'origin_city_id' => $this->b->id,
            'destination_city_id' => $this->a->id,
            'departs_at' => now()->addHours(3)->format('Y-m-d H:i:s'),
            'price' => 30,
            'seats' => 3,
        ];
    }

    private function reference(): float
    {
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'q'));

        return (float) $this->getJson('/api/v2/empty-returns/quote?origin_city_id=' . $this->b->id . '&destination_city_id=' . $this->a->id)
            ->assertOk()->assertJsonPath('data.known', true)->json('data.reference_price');
    }

    public function test_the_quote_gives_the_reference_fare_and_half_of_it_as_the_starting_price(): void
    {
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'q'));

        $r = $this->getJson('/api/v2/empty-returns/quote?origin_city_id=' . $this->b->id . '&destination_city_id=' . $this->a->id)->assertOk();

        $this->assertGreaterThan(0, $r->json('data.reference_price'));
        $this->assertEqualsWithDelta($r->json('data.reference_price') / 2, $r->json('data.suggested_price'), 1.0);
    }

    public function test_a_limousine_driver_publishes_and_it_is_one_ride_leg_flagged_as_an_empty_return(): void
    {
        $driver = $this->driver();
        Sanctum::actingAs($driver);

        $id = $this->postJson('/api/v2/business/empty-returns', $this->payload())
            ->assertCreated()->assertJsonPath('data.empty_return.price', 30)->assertJsonPath('data.empty_return.seats', 3)->json('data.empty_return.id');

        $leg = TripSchedule::findOrFail($id);
        $this->assertSame('limousine', $leg->mode);
        $this->assertTrue($leg->is_return_leg);
        $this->assertSame(1, (int) $leg->capacity);
        $this->assertTrue((bool) data_get($leg->meta, 'empty_return'));

        $this->getJson('/api/v2/business/empty-returns')->assertOk()->assertJsonPath('data.count', 1);
    }

    public function test_only_a_limousine_driver_may_publish(): void
    {
        Sanctum::actingAs($this->user(User::TYPE_BUSINESS, 'shop', ['category_id' => 5, 'category_child_id' => (int) DB::table('category_children_master')->where('id', '!=', RideRequestKind::tradeId())->value('id')]));

        $this->postJson('/api/v2/business/empty-returns', $this->payload())->assertStatus(422)->assertJsonValidationErrors('driver');
    }

    public function test_the_price_may_not_exceed_the_reference_fare_and_the_time_must_be_ahead(): void
    {
        $reference = $this->reference();
        Sanctum::actingAs($this->driver());

        $this->postJson('/api/v2/business/empty-returns', $this->payload(['price' => $reference + 50]))->assertStatus(422)->assertJsonValidationErrors('price');
        $this->postJson('/api/v2/business/empty-returns', $this->payload(['departs_at' => now()->subHour()->format('Y-m-d H:i:s')]))->assertStatus(422)->assertJsonValidationErrors('departs_at');
        $this->postJson('/api/v2/business/empty-returns', $this->payload(['departs_at' => now()->addDays(30)->format('Y-m-d H:i:s')]))->assertStatus(422)->assertJsonValidationErrors('departs_at');
        $this->postJson('/api/v2/business/empty-returns', $this->payload(['destination_city_id' => $this->b->id]))->assertStatus(422);
    }

    public function test_a_customer_on_that_line_sees_it_with_the_saving_and_books_it_and_then_it_is_no_longer_offered(): void
    {
        $reference = $this->reference();
        $driver = $this->driver();
        Sanctum::actingAs($driver);
        $id = $this->postJson('/api/v2/business/empty-returns', $this->payload(['price' => round($reference / 2)]))->assertCreated()->json('data.empty_return.id');

        $client = $this->user(User::TYPE_CLIENT, 'cust');
        Sanctum::actingAs($client);

        $list = $this->getJson('/api/v2/empty-returns?origin_city_id=' . $this->b->id . '&destination_city_id=' . $this->a->id)->assertOk();
        $row = collect($list->json('data.results'))->firstWhere('id', $id);
        $this->assertNotNull($row);
        $this->assertTrue($row['exact_match']);
        $this->assertSame(1, $row['remaining']);
        $this->assertGreaterThanOrEqual(40, $row['saving_percent']);
        $this->assertSame($driver->id, $row['driver']['id']);

        $this->postJson("/api/v2/schedules/{$id}/reserve", ['units' => 1])->assertCreated();
        $this->assertDatabaseHas('trip_reservations', ['trip_schedule_id' => $id, 'client_id' => $client->id]);

        // the car is taken: another customer no longer sees it
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'cust2'));
        $again = $this->getJson('/api/v2/empty-returns?origin_city_id=' . $this->b->id . '&destination_city_id=' . $this->a->id)->assertOk();
        $this->assertNull(collect($again->json('data.results'))->firstWhere('id', $id));
    }

    public function test_the_other_direction_and_an_offer_whose_time_has_passed_are_not_listed(): void
    {
        $driver = $this->driver();
        Sanctum::actingAs($driver);
        $id = $this->postJson('/api/v2/business/empty-returns', $this->payload())->assertCreated()->json('data.empty_return.id');

        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'cust'));
        $opposite = $this->getJson('/api/v2/empty-returns?origin_city_id=' . $this->a->id . '&destination_city_id=' . $this->b->id)->assertOk();
        $this->assertNull(collect($opposite->json('data.results'))->firstWhere('id', $id));

        // the departure went by more than the grace ago
        TripSchedule::whereKey($id)->update(['trip_date' => now()->subDay()->toDateString(), 'departure_time' => '08:00:00']);
        $past = $this->getJson('/api/v2/empty-returns?origin_city_id=' . $this->b->id . '&destination_city_id=' . $this->a->id)->assertOk();
        $this->assertNull(collect($past->json('data.results'))->firstWhere('id', $id));
    }

    public function test_the_driver_takes_it_down_unless_somebody_holds_it(): void
    {
        $driver = $this->driver();
        Sanctum::actingAs($driver);
        $free = $this->postJson('/api/v2/business/empty-returns', $this->payload())->assertCreated()->json('data.empty_return.id');
        $held = $this->postJson('/api/v2/business/empty-returns', $this->payload(['departs_at' => now()->addHours(5)->format('Y-m-d H:i:s')]))->assertCreated()->json('data.empty_return.id');

        $client = $this->user(User::TYPE_CLIENT, 'cust');
        Sanctum::actingAs($client);
        $this->postJson("/api/v2/schedules/{$held}/reserve", ['units' => 1])->assertCreated();

        Sanctum::actingAs($driver);
        $this->deleteJson("/api/v2/business/empty-returns/{$free}")->assertOk();
        $this->assertSame('cancelled', TripSchedule::find($free)->status);

        $this->deleteJson("/api/v2/business/empty-returns/{$held}")->assertStatus(422);
        $this->assertSame('active', TripSchedule::find($held)->status);
        $this->assertSame(1, TripReservation::where('trip_schedule_id', $held)->count());
    }
}
