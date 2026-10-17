<?php

namespace Tests\Feature;

use App\Models\BusinessDepositPolicy;
use App\Models\TripParcel;
use App\Models\TripReservation;
use App\Models\TripSchedule;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Owner rules of 2026-10-15 for trips: an off-app passenger is named and stays out of the ratings until the fee is
 * paid; a no-show's held deposit goes back by itself once the trip is full; attendance needs the customer first and
 * then the carrier; a trip leaves full — or the carrier acknowledges leaving short; parcels ride with the car and are
 * handed over against a code. Rolls back.
 */
class TripOfflinePassengersAndParcelsTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;
    private int $originGov;
    private int $destGov;

    protected function setUp(): void
    {
        parent::setUp();

        $govs = \App\Models\Governorate::query()->orderBy('id')->limit(2)->pluck('id');
        if ($govs->count() < 2) {
            $this->markTestSkipped('Needs 2 governorates.');
        }
        $this->originGov = (int) $govs[0];
        $this->destGov = (int) $govs[1];

        $this->business = $this->makeUser('business', 'carrier');
        Wallet::updateOrCreate(['user_id' => $this->business->id], ['balance' => 100, 'locked_balance' => 0, 'status' => Wallet::STATUS_ACTIVE]);
        BusinessDepositPolicy::query()->where('business_id', $this->business->id)->delete();
    }

    private function makeUser(string $type, string $tag, float $balance = 0): User
    {
        $u = new User();
        $u->name = $tag.' '.Str::random(4);
        $u->email = strtolower($tag).'-'.uniqid().'@example.test';
        $u->phone = '01'.random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);
        $u->save();

        if ($balance > 0) {
            Wallet::updateOrCreate(['user_id' => $u->id], ['balance' => $balance, 'locked_balance' => 0, 'status' => Wallet::STATUS_ACTIVE]);
        }

        return $u;
    }

    private function schedule(int $capacity, float $depositPerUnit = 0): TripSchedule
    {
        return TripSchedule::create([
            'business_id' => $this->business->id,
            'mode' => TripSchedule::MODE_PASSENGER,
            'origin_governorate_id' => $this->originGov,
            'destination_governorate_id' => $this->destGov,
            'schedule_pattern' => TripSchedule::PATTERN_ON_DEMAND,
            'capacity' => $capacity,
            'capacity_unit' => 'seat',
            'price' => 100,
            'deposit_per_unit' => $depositPerUnit > 0 ? $depositPerUnit : null,
            'status' => TripSchedule::STATUS_ACTIVE,
        ]);
    }

    private function reserve(User $client, TripSchedule $leg, int $units = 1): int
    {
        Sanctum::actingAs($client);

        return (int) $this->postJson("/api/v2/schedules/{$leg->id}/reserve", ['units' => $units])
            ->assertCreated()->json('data.reservation.id');
    }

    private function wallet(User $u): Wallet
    {
        return Wallet::query()->where('user_id', $u->id)->firstOrFail();
    }

    // ---- 1. a no-show's deposit goes back once the trip is full -------------------------------------------------

    public function test_a_held_no_show_deposit_goes_back_by_itself_when_an_off_app_passenger_fills_the_trip(): void
    {
        $leg = $this->schedule(capacity: 2, depositPerUnit: 50);
        $absent = $this->makeUser('client', 'absent', 300);
        $other = $this->makeUser('client', 'other', 300);

        $absentRes = $this->reserve($absent, $leg);
        $this->reserve($other, $leg);

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/schedules/reservations/{$absentRes}/confirm")->assertOk();
        $this->postJson("/api/v2/business/schedules/reservations/{$absentRes}/no-show")->assertOk();

        // the seat is free again; the deposit is HELD, nothing returns yet
        $this->assertEqualsWithDelta(50.0, (float) $this->wallet($absent)->locked_balance, 0.001);

        // not full yet: the carrier must not be able to keep it either way — an off-app passenger takes the seat
        $this->postJson("/api/v2/business/schedules/{$leg->id}/block", [
            'units' => 1, 'passenger_name' => 'Hassan', 'passenger_phone' => '01000000000',
        ])->assertCreated()->assertJsonPath('data.reservation.passenger_name', 'Hassan');

        $this->assertEqualsWithDelta(0.0, (float) $this->wallet($absent)->locked_balance, 0.001);
        $this->assertEqualsWithDelta(300.0, (float) $this->wallet($absent)->balance, 0.001);
    }

    // ---- 2. off-app passengers: named, outside the ratings until the fee is paid -----------------------------

    public function test_an_off_app_passenger_needs_a_name_and_is_not_counted_until_the_fee_is_paid(): void
    {
        $leg = $this->schedule(capacity: 4);

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/schedules/{$leg->id}/block", ['units' => 1])->assertStatus(422);

        $id = (int) $this->postJson("/api/v2/business/schedules/{$leg->id}/block", ['units' => 1, 'passenger_name' => 'Hassan'])
            ->assertCreated()->assertJsonPath('data.reservation.rating_visible', false)->json('data.reservation.id');

        // the seat cannot be completed (and so cannot be counted) before the fee is paid
        $this->postJson("/api/v2/business/schedules/reservations/{$id}/complete")->assertStatus(422);

        $this->postJson("/api/v2/business/schedules/reservations/{$id}/rating-visibility")->assertOk()
            ->assertJsonPath('data.reservation.rating_visible', true);

        $this->postJson("/api/v2/business/schedules/reservations/{$id}/complete")->assertOk()
            ->assertJsonPath('data.reservation.status', 'completed');
    }

    // ---- 3. attendance: the customer first, then the carrier; a full attended trip closes ------------------

    public function test_attendance_needs_the_customer_first_and_a_full_attended_trip_closes_booking(): void
    {
        $leg = $this->schedule(capacity: 1);
        $client = $this->makeUser('client', 'rider');
        $late = $this->makeUser('client', 'late');

        $id = $this->reserve($client, $leg);

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/schedules/reservations/{$id}/confirm")->assertOk();

        // the carrier cannot confirm before the customer did
        $this->postJson("/api/v2/business/schedules/reservations/{$id}/attendance")->assertStatus(422);

        Sanctum::actingAs($client);
        $this->postJson("/api/v2/schedules/reservations/{$id}/attendance")->assertOk();

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/schedules/reservations/{$id}/attendance")->assertOk();

        // full + attended: nobody can add or reserve, and the search says «مكتملة»
        $this->postJson("/api/v2/business/schedules/{$leg->id}/block", ['units' => 1, 'passenger_name' => 'x'])->assertStatus(422);

        Sanctum::actingAs($late);
        $this->postJson("/api/v2/schedules/{$leg->id}/reserve", ['units' => 1])->assertStatus(422);

        $row = collect($this->getJson('/api/v2/search/schedules?'.http_build_query([
            'origin_governorate_id' => $this->originGov,
            'destination_governorate_id' => $this->destGov,
        ]))->json('data.results'))->firstWhere('schedule.id', $leg->id);

        $this->assertTrue((bool) ($row['is_full'] ?? false));
        $this->assertTrue((bool) ($row['boarding_closed'] ?? false));
    }

    // ---- 3b. the run leaves full or by the carrier's own word ----------------------------------------------

    public function test_a_run_starts_full_and_attended_or_with_the_carriers_acknowledgement(): void
    {
        $leg = $this->schedule(capacity: 2);
        $leg->syncStops([['label' => 'A', 'address' => 'a'], ['label' => 'B', 'address' => 'b']]);

        $client = $this->makeUser('client', 'rider');
        $this->reserve($client, $leg);

        Sanctum::actingAs($this->business);

        $this->postJson("/api/v2/business/schedules/{$leg->id}/runs", ['passenger_count' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('acknowledge_partial');

        $this->postJson("/api/v2/business/schedules/{$leg->id}/runs", ['passenger_count' => 1, 'acknowledge_partial' => true])
            ->assertCreated();
    }

    // ---- 4. parcels -------------------------------------------------------------------------------------------

    public function test_a_parcel_is_priced_by_the_driver_and_handed_over_only_against_the_code(): void
    {
        $leg = $this->schedule(capacity: 3);
        $sender = $this->makeUser('client', 'sender');

        Sanctum::actingAs($sender);
        $id = (int) $this->postJson("/api/v2/schedules/{$leg->id}/parcels", [
            'description' => 'صندوق ملابس في شنطة السيارة',
            'receiver_name' => 'Omar',
            'receiver_phone' => '01012345678',
        ])->assertCreated()->assertJsonPath('data.parcel.status', 'requested')->json('data.parcel.id');

        // a receiver is either an account or a name and a phone
        $this->postJson("/api/v2/schedules/{$leg->id}/parcels", ['description' => 'x'])->assertStatus(422);

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/schedules/parcels/{$id}/price", ['price' => 150, 'seats' => 1])->assertOk()
            ->assertJsonPath('data.parcel.status', 'priced');

        // priced seats are not held until the sender accepts
        Sanctum::actingAs($sender);
        $this->assertSame(3, $this->remaining($leg));
        $accepted = $this->postJson("/api/v2/schedules/parcels/{$id}/accept")->assertOk()->assertJsonPath('data.parcel.status', 'accepted');
        $this->assertSame(2, $this->remaining($leg));

        // the sender holds ONE code: the QR/code the driver scans or types to take the parcel
        $pickup = (string) $accepted->json('data.parcel.handover_code');
        $this->assertSame('pickup', $accepted->json('data.parcel.handover_kind'));
        $this->assertMatchesRegularExpression('/^\d{4}[a-z]{2}$/', $pickup);

        Sanctum::actingAs($this->business);

        // a wrong code takes nothing; the right one (scanned or typed, any case) loads it
        $this->postJson("/api/v2/business/schedules/parcels/{$id}/load", ['code' => '0000zz'])->assertStatus(422);
        $loaded = $this->postJson("/api/v2/business/schedules/parcels/{$id}/load", ['code' => strtoupper($pickup)])->assertOk();
        $this->assertNull($loaded->json('data.parcel.handover_code'), 'the carrier is never shown the code');

        // the code is spent: the sender's ONE code is now the delivery code — a new one the driver never saw
        $code = $this->currentCode($sender, $id);
        $this->assertMatchesRegularExpression('/^\d{4}[a-z]{2}$/', $code);
        $this->assertNotSame($pickup, $code);
        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/schedules/parcels/{$id}/deliver", ['code' => $pickup])->assertStatus(422);

        $this->postJson("/api/v2/business/schedules/parcels/{$id}/deliver", ['code' => '0000zz'])->assertStatus(422);
        $this->postJson("/api/v2/business/schedules/parcels/{$id}/deliver", ['code' => strtoupper($code), 'cash_collected' => true])->assertOk()
            ->assertJsonPath('data.parcel.status', 'delivered')
            ->assertJsonPath('data.parcel.payer', 'receiver');

        // the receiver paid in cash at delivery: recorded, never touching a wallet
        $this->assertNotNull(TripParcel::query()->find($id)->paid_at);
    }

    public function test_wrong_codes_lock_the_handover_after_five_tries(): void
    {
        $leg = $this->schedule(capacity: 3);
        $sender = $this->makeUser('client', 'sender');

        Sanctum::actingAs($sender);
        $id = (int) $this->postJson("/api/v2/schedules/{$leg->id}/parcels", [
            'description' => 'x', 'receiver_name' => 'Omar', 'receiver_phone' => '01012345678',
        ])->assertCreated()->json('data.parcel.id');

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/schedules/parcels/{$id}/price", ['price' => 10])->assertOk();
        Sanctum::actingAs($sender);
        $pickup = (string) $this->postJson("/api/v2/schedules/parcels/{$id}/accept")->assertOk()->json('data.parcel.handover_code');

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/schedules/parcels/{$id}/load", ['code' => $pickup])->assertOk();
        $code = $this->currentCode($sender, $id);

        Sanctum::actingAs($this->business);
        foreach (range(1, TripParcel::MAX_CODE_ATTEMPTS) as $_) {
            $this->postJson("/api/v2/business/schedules/parcels/{$id}/deliver", ['code' => '9999xx'])->assertStatus(422);
        }

        // even the right code no longer works until the sender asks for a new one
        $this->postJson("/api/v2/business/schedules/parcels/{$id}/deliver", ['code' => $code])->assertStatus(422);

        Sanctum::actingAs($sender);
        $this->postJson("/api/v2/schedules/parcels/{$id}/code")->assertOk();
        $fresh = $this->currentCode($sender, $id);
        $this->assertNotSame($code, $fresh);
        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/schedules/parcels/{$id}/deliver", ['code' => $fresh])->assertOk();
    }

    public function test_a_parcel_rides_in_the_run_is_settled_with_it_and_can_be_disputed(): void
    {
        $leg = $this->schedule(capacity: 3);
        $leg->syncStops([['label' => 'A', 'address' => 'a'], ['label' => 'B', 'address' => 'b']]);
        $sender = $this->makeUser('client', 'sender');

        Sanctum::actingAs($sender);
        $id = (int) $this->postJson("/api/v2/schedules/{$leg->id}/parcels", [
            'description' => 'x', 'payer' => 'sender', 'receiver_name' => 'Omar', 'receiver_phone' => '01012345678',
        ])->assertCreated()->assertJsonPath('data.parcel.payer', 'sender')->json('data.parcel.id');

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/schedules/parcels/{$id}/price", [
            'price' => 60, 'expected_arrival_at' => now()->addHours(3)->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.parcel.expected_arrival_at', fn ($v) => $v !== null);

        Sanctum::actingAs($sender);
        $pickup = (string) $this->postJson("/api/v2/schedules/parcels/{$id}/accept")->json('data.parcel.handover_code');

        Sanctum::actingAs($this->business);
        // the sender pays in cash when handing it over
        $this->postJson("/api/v2/business/schedules/parcels/{$id}/load", ['code' => $pickup, 'cash_collected' => true])->assertOk();
        $this->assertNotNull(TripParcel::query()->find($id)->paid_at);

        // the run takes the parcel with it and shows it
        $run = $this->postJson("/api/v2/business/schedules/{$leg->id}/runs", ['passenger_count' => 1, 'acknowledge_partial' => true])->assertCreated();
        $runId = (int) $run->json('data.run.id');
        $this->assertSame($id, (int) $run->json('data.run.parcels.0.id'));

        // the run ends with the parcel still on the car: it is settled as returned, not silently lost
        $this->postJson("/api/v2/business/schedules/runs/{$runId}/arrive")->assertOk();
        $this->postJson("/api/v2/business/schedules/runs/{$runId}/advance")->assertOk();
        $this->postJson("/api/v2/business/schedules/runs/{$runId}/arrive")->assertOk();
        $this->postJson("/api/v2/business/schedules/runs/{$runId}/advance")->assertOk();

        $this->assertSame('returned', TripParcel::query()->find($id)->status);

        // a paid service: the sender may dispute it
        Sanctum::actingAs($sender);
        $this->postJson("/api/v2/schedules/parcels/{$id}/disputes", ['reason_code' => 'no_show'])->assertOk();
    }

    /** The ONE code the sender sees on his parcel right now (pickup while accepted, delivery once loaded). */
    private function currentCode(User $sender, int $id): string
    {
        Sanctum::actingAs($sender);
        $row = collect($this->getJson('/api/v2/schedules/my-parcels')->assertOk()->json('data.data'))->firstWhere('id', $id);

        return (string) ($row['handover_code'] ?? '');
    }

    private function remaining(TripSchedule $leg): int
    {
        return (int) app(\App\Services\Schedules\TripReservationService::class)->remainingCapacityFor([$leg->id])[$leg->id];
    }
}
