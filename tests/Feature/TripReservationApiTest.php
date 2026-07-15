<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\RatingOutcomeEvent;
use App\Models\TripReservation;
use App\Models\TripSchedule;
use App\Models\User;
use App\Models\UserOperationRating;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Reservation lifecycle on trip legs: reserve (atomic capacity), confirm,
 * complete (→ rating for both parties), cancel (→ release + conditional
 * cancel-ledger). Rolls back.
 */
class TripReservationApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;
    private User $client;
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

        $this->business = User::query()->where('type', 'business')->orderBy('id')->firstOrFail();
        $this->client = User::query()->where('type', '!=', 'business')->orderBy('id')->first()
            ?? User::query()->where('type', 'business')->orderBy('id')->skip(1)->first();

        if (! $this->client || (int) $this->client->id === (int) $this->business->id) {
            $this->markTestSkipped('Needs a distinct client user.');
        }

        // Clean rating slate for deterministic assertions.
        foreach ([$this->business->id, $this->client->id] as $uid) {
            UserOperationRating::query()->where('user_id', $uid)->delete();
            RatingOutcomeEvent::query()->where('ratee_user_id', $uid)->delete();
        }
    }

    private function schedule(int $capacity = 2, float $price = 100, float $depositPerUnit = 0): TripSchedule
    {
        return TripSchedule::create([
            'business_id' => $this->business->id,
            'mode' => TripSchedule::MODE_PASSENGER,
            'origin_governorate_id' => $this->originGov,
            'destination_governorate_id' => $this->destGov,
            'schedule_pattern' => TripSchedule::PATTERN_ON_DEMAND,
            'capacity' => $capacity,
            'capacity_unit' => 'seat',
            'price' => $price,
            'deposit_per_unit' => $depositPerUnit > 0 ? $depositPerUnit : null,
            'status' => TripSchedule::STATUS_ACTIVE,
        ]);
    }

    private function fundClientWallet(float $balance): void
    {
        \App\Models\Wallet::updateOrCreate(
            ['user_id' => $this->client->id],
            ['balance' => $balance, 'locked_balance' => 0, 'status' => \App\Models\Wallet::STATUS_ACTIVE]
        );
    }

    private function successCount(int $userId, string $role): int
    {
        return (int) UserOperationRating::query()
            ->where('user_id', $userId)->where('role', $role)->value('success_count');
    }

    private function cancelledCount(int $userId, string $role): int
    {
        return (int) UserOperationRating::query()
            ->where('user_id', $userId)->where('role', $role)->value('cancelled_count');
    }

    private function searchRow(int $scheduleId): array
    {
        $search = $this->getJson('/api/v2/search/schedules?'.http_build_query([
            'origin_governorate_id' => $this->originGov,
            'destination_governorate_id' => $this->destGov,
        ]));

        return collect($search->json('data.results'))->firstWhere('schedule.id', $scheduleId) ?? [];
    }

    public function test_client_reserves_and_capacity_is_held(): void
    {
        $leg = $this->schedule(capacity: 2, price: 100);

        Sanctum::actingAs($this->client);
        $res = $this->postJson("/api/v2/schedules/{$leg->id}/reserve", ['units' => 1]);

        $res->assertCreated();
        $this->assertSame('pending', $res->json('data.reservation.status'));
        $this->assertSame(100.0, (float) $res->json('data.reservation.total_price'));

        // Search now shows one seat left.
        $search = $this->getJson('/api/v2/search/schedules?'.http_build_query([
            'origin_governorate_id' => $this->originGov,
            'destination_governorate_id' => $this->destGov,
        ]));
        $row = collect($search->json('data.results'))->firstWhere('schedule.id', $leg->id);
        $this->assertSame(1, $row['remaining_capacity']);
    }

    public function test_overbooking_is_rejected(): void
    {
        $leg = $this->schedule(capacity: 1);

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/schedules/{$leg->id}/reserve", ['units' => 1])->assertCreated();
        $this->postJson("/api/v2/schedules/{$leg->id}/reserve", ['units' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('units');
    }

    public function test_cannot_reserve_your_own_schedule(): void
    {
        $leg = $this->schedule();

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/schedules/{$leg->id}/reserve", ['units' => 1])->assertStatus(422);
    }

    public function test_full_lifecycle_completes_and_rates_both_parties(): void
    {
        $leg = $this->schedule();

        Sanctum::actingAs($this->client);
        $rid = (int) $this->postJson("/api/v2/schedules/{$leg->id}/reserve", ['units' => 1])->json('data.reservation.id');

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/schedules/reservations/{$rid}/confirm")
            ->assertOk()->assertJsonPath('data.reservation.status', 'confirmed');

        $this->postJson("/api/v2/business/schedules/reservations/{$rid}/complete")
            ->assertOk()->assertJsonPath('data.reservation.status', 'completed');

        // Both sides earned a success in the universal rating.
        $this->assertSame(1, $this->successCount($this->business->id, UserOperationRating::ROLE_BUSINESS));
        $this->assertSame(1, $this->successCount($this->client->id, UserOperationRating::ROLE_CLIENT));
    }

    public function test_cancel_after_confirm_ledgers_a_cancel_and_releases_capacity(): void
    {
        $leg = $this->schedule(capacity: 1);

        Sanctum::actingAs($this->client);
        $rid = (int) $this->postJson("/api/v2/schedules/{$leg->id}/reserve", ['units' => 1])->json('data.reservation.id');

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/schedules/reservations/{$rid}/confirm")->assertOk();

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/schedules/reservations/{$rid}/cancel")->assertOk();

        $this->assertSame(TripReservation::STATUS_CANCELLED, TripReservation::query()->whereKey($rid)->value('status'));
        $this->assertSame(1, $this->cancelledCount($this->business->id, UserOperationRating::ROLE_BUSINESS));

        // Capacity released → the seat is bookable again.
        $this->postJson("/api/v2/schedules/{$leg->id}/reserve", ['units' => 1])->assertCreated();
    }

    public function test_cancel_while_pending_has_no_rating_impact(): void
    {
        $leg = $this->schedule();

        Sanctum::actingAs($this->client);
        $rid = (int) $this->postJson("/api/v2/schedules/{$leg->id}/reserve", ['units' => 1])->json('data.reservation.id');
        $this->postJson("/api/v2/schedules/reservations/{$rid}/cancel")->assertOk();

        $this->assertSame(0, $this->cancelledCount($this->business->id, UserOperationRating::ROLE_BUSINESS));
        $this->assertSame(0, $this->cancelledCount($this->client->id, UserOperationRating::ROLE_CLIENT));
    }

    public function test_carrier_offline_block_reduces_available_seats(): void
    {
        // A 14-seat microbus.
        $leg = $this->schedule(capacity: 14, price: 50);

        // The driver sold 7 seats off-app and blocks them.
        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/schedules/{$leg->id}/block", ['units' => 7])
            ->assertCreated()
            ->assertJsonPath('data.reservation.source', 'offline')
            ->assertJsonPath('data.reservation.status', 'blocked');

        // Search now shows 7 seats left.
        $this->assertSame(7, $this->searchRow($leg->id)['remaining_capacity']);

        // A customer books 3 in-app → 4 left; blocking 5 more would overflow.
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/schedules/{$leg->id}/reserve", ['units' => 3])->assertCreated();
        $this->assertSame(4, $this->searchRow($leg->id)['remaining_capacity']);

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/schedules/{$leg->id}/block", ['units' => 5])
            ->assertStatus(422)->assertJsonValidationErrors('units');
    }

    public function test_offline_block_release_restores_capacity_without_rating(): void
    {
        $leg = $this->schedule(capacity: 1);

        Sanctum::actingAs($this->business);
        $rid = (int) $this->postJson("/api/v2/business/schedules/{$leg->id}/block", ['units' => 1])
            ->json('data.reservation.id');
        $this->assertSame(0, $this->searchRow($leg->id)['remaining_capacity']);

        // Releasing the offline hold frees the seat and leaves reputation untouched.
        $this->postJson("/api/v2/business/schedules/reservations/{$rid}/reject")->assertOk();
        $this->assertSame(1, $this->searchRow($leg->id)['remaining_capacity']);
        $this->assertSame(0, $this->cancelledCount($this->business->id, UserOperationRating::ROLE_BUSINESS));
    }

    public function test_a_carrier_cannot_block_seats_on_a_foreign_schedule(): void
    {
        $leg = $this->schedule();

        $otherBusiness = User::query()->where('type', 'business')->where('id', '!=', $this->business->id)->first();
        if (! $otherBusiness) {
            $this->markTestSkipped('Needs a second business.');
        }

        Sanctum::actingAs($otherBusiness);
        $this->postJson("/api/v2/business/schedules/{$leg->id}/block", ['units' => 1])->assertNotFound();
    }

    public function test_deposit_is_held_on_reserve_and_released_on_cancel(): void
    {
        $this->fundClientWallet(300);
        $leg = $this->schedule(capacity: 5, price: 100, depositPerUnit: 50);

        Sanctum::actingAs($this->client);
        $res = $this->postJson("/api/v2/schedules/{$leg->id}/reserve", ['units' => 2]);
        $res->assertCreated();
        $this->assertSame(100.0, (float) $res->json('data.reservation.deposit_held'));
        $rid = (int) $res->json('data.reservation.id');

        // 100 moved from balance to locked.
        $wallet = \App\Models\Wallet::query()->where('user_id', $this->client->id)->first();
        $this->assertSame(200.0, (float) $wallet->balance);
        $this->assertSame(100.0, (float) $wallet->locked_balance);

        // Cancelling returns the held deposit.
        $this->postJson("/api/v2/schedules/reservations/{$rid}/cancel")->assertOk();
        $wallet->refresh();
        $this->assertSame(300.0, (float) $wallet->balance);
        $this->assertSame(0.0, (float) $wallet->locked_balance);
    }

    public function test_reserve_fails_when_wallet_cannot_cover_the_deposit(): void
    {
        $this->fundClientWallet(40); // needs 100
        $leg = $this->schedule(capacity: 5, price: 100, depositPerUnit: 50);

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/schedules/{$leg->id}/reserve", ['units' => 2])->assertStatus(422);

        // Nothing was reserved (transaction rolled back), so capacity is intact.
        $this->assertSame(0, TripReservation::query()->where('trip_schedule_id', $leg->id)->count());
    }

    public function test_reserving_notifies_the_carrier(): void
    {
        $leg = $this->schedule();

        Sanctum::actingAs($this->client);
        $rid = (int) $this->postJson("/api/v2/schedules/{$leg->id}/reserve", ['units' => 1])->json('data.reservation.id');

        $this->assertTrue(
            AppNotification::query()
                ->where('user_id', $this->business->id)
                ->where('notifiable_type', TripReservation::class)
                ->where('notifiable_id', $rid)
                ->exists()
        );
    }

    public function test_carrier_cannot_touch_a_reservation_that_is_not_theirs(): void
    {
        $leg = $this->schedule();

        Sanctum::actingAs($this->client);
        $rid = (int) $this->postJson("/api/v2/schedules/{$leg->id}/reserve", ['units' => 1])->json('data.reservation.id');

        $otherBusiness = User::query()->where('type', 'business')->where('id', '!=', $this->business->id)->first();
        if (! $otherBusiness) {
            $this->markTestSkipped('Needs a second business.');
        }

        Sanctum::actingAs($otherBusiness);
        $this->postJson("/api/v2/business/schedules/reservations/{$rid}/confirm")->assertNotFound();
    }
}
