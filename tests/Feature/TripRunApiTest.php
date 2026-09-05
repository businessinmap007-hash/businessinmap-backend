<?php

namespace Tests\Feature;

use App\Models\BusinessStaff;
use App\Models\TripSchedule;
use App\Models\User;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Live execution of a trip leg: start a run (manifest for freight/
 * distribution, a headcount for passenger/limousine), cycle its stops one at
 * a time, and reconcile the cargo at the end. See TripRunService.
 */
class TripRunApiTest extends TestCase
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

        $this->business = User::query()->where('type', 'business')->orderBy('id')->firstOrFail();
    }

    private function makeUser(string $type, string $tag): User
    {
        $u = new User();
        $u->name = $tag.' '.Str::random(4);
        $u->email = strtolower($tag).'-'.uniqid().'@example.test';
        $u->phone = '01'.random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    private function schedule(string $mode, ?int $businessId = null): TripSchedule
    {
        return TripSchedule::create([
            'business_id' => $businessId ?? $this->business->id,
            'mode' => $mode,
            'origin_governorate_id' => $this->originGov,
            'destination_governorate_id' => $this->destGov,
            'schedule_pattern' => TripSchedule::PATTERN_ON_DEMAND,
            'status' => TripSchedule::STATUS_ACTIVE,
        ]);
    }

    private function addStops(TripSchedule $schedule, array $labels): void
    {
        $schedule->syncStops(array_map(fn ($label) => ['label' => $label, 'address' => $label.' street'], $labels));
    }

    public function test_a_run_cannot_start_without_stops(): void
    {
        Sanctum::actingAs($this->business);
        $leg = $this->schedule(TripSchedule::MODE_PASSENGER);

        $this->postJson("/api/v2/business/schedules/{$leg->id}/runs", ['passenger_count' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('stops');
    }

    public function test_passenger_run_requires_a_headcount_and_forbids_a_manifest(): void
    {
        Sanctum::actingAs($this->business);
        $leg = $this->schedule(TripSchedule::MODE_PASSENGER);
        $this->addStops($leg, ['A', 'B']);

        $this->postJson("/api/v2/business/schedules/{$leg->id}/runs", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('passenger_count');

        $this->postJson("/api/v2/business/schedules/{$leg->id}/runs", [
            'passenger_count' => 12,
            'manifest' => [['label' => 'x', 'assigned_qty' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('manifest');
    }

    public function test_freight_run_requires_a_manifest_and_forbids_a_headcount(): void
    {
        Sanctum::actingAs($this->business);
        $leg = $this->schedule(TripSchedule::MODE_DISTRIBUTION);
        $this->addStops($leg, ['Shop 1', 'Shop 2']);

        $this->postJson("/api/v2/business/schedules/{$leg->id}/runs", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('manifest');

        $this->postJson("/api/v2/business/schedules/{$leg->id}/runs", [
            'manifest' => [['label' => 'صابون', 'assigned_qty' => 10]],
            'passenger_count' => 3,
        ])->assertStatus(422)->assertJsonValidationErrors('passenger_count');
    }

    public function test_passenger_run_walks_its_stops_and_auto_completes_with_no_reconciliation(): void
    {
        Sanctum::actingAs($this->business);
        $leg = $this->schedule(TripSchedule::MODE_PASSENGER);
        $this->addStops($leg, ['Start', 'End']);

        $run = $this->postJson("/api/v2/business/schedules/{$leg->id}/runs", ['passenger_count' => 8])
            ->assertCreated()
            ->assertJsonPath('data.run.status', 'in_progress')
            ->assertJsonPath('data.run.mode', TripSchedule::MODE_PASSENGER)
            ->assertJsonPath('data.run.stops.0.status', 'heading')
            ->assertJsonPath('data.run.stops.1.status', 'pending')
            ->json('data.run');

        $runId = $run['id'];

        // Can't advance before arriving.
        $this->postJson("/api/v2/business/schedules/runs/{$runId}/advance")->assertStatus(422);

        $this->postJson("/api/v2/business/schedules/runs/{$runId}/arrive")
            ->assertOk()
            ->assertJsonPath('data.run.stops.0.status', 'arrived');

        $this->postJson("/api/v2/business/schedules/runs/{$runId}/advance")
            ->assertOk()
            ->assertJsonPath('data.run.stops.0.status', 'done')
            ->assertJsonPath('data.run.stops.1.status', 'heading')
            ->assertJsonPath('data.run.status', 'in_progress');

        $this->postJson("/api/v2/business/schedules/runs/{$runId}/arrive")->assertOk();

        $this->postJson("/api/v2/business/schedules/runs/{$runId}/advance")
            ->assertOk()
            ->assertJsonPath('data.run.stops.1.status', 'done')
            ->assertJsonPath('data.run.status', 'completed');
    }

    public function test_freight_run_awaits_reconciliation_then_completes_with_computed_remaining(): void
    {
        Sanctum::actingAs($this->business);
        $leg = $this->schedule(TripSchedule::MODE_FREIGHT);
        $this->addStops($leg, ['Stop 1', 'Stop 2']);

        $run = $this->postJson("/api/v2/business/schedules/{$leg->id}/runs", [
            'manifest' => [
                ['label' => 'كرتونة أ', 'unit' => 'كرتونة', 'assigned_qty' => 10],
                ['label' => 'كرتونة ب', 'unit' => 'كرتونة', 'assigned_qty' => 5],
            ],
        ])->assertCreated()->json('data.run');

        $runId = $run['id'];
        $itemA = $run['manifest_items'][0]['id'];
        $itemB = $run['manifest_items'][1]['id'];

        $this->postJson("/api/v2/business/schedules/runs/{$runId}/arrive")->assertOk();
        $this->postJson("/api/v2/business/schedules/runs/{$runId}/advance")->assertOk();
        $this->postJson("/api/v2/business/schedules/runs/{$runId}/arrive")->assertOk();

        // Last stop done → awaiting reconciliation, NOT completed yet.
        $this->postJson("/api/v2/business/schedules/runs/{$runId}/advance")
            ->assertOk()
            ->assertJsonPath('data.run.status', 'awaiting_reconciliation');

        // Over-reporting one line is rejected.
        $this->postJson("/api/v2/business/schedules/runs/{$runId}/reconcile", [
            'items' => [
                ['manifest_item_id' => $itemA, 'delivered_qty' => 8, 'returned_qty' => 5],
                ['manifest_item_id' => $itemB, 'delivered_qty' => 5, 'returned_qty' => 0],
            ],
        ])->assertStatus(422);

        // A partial delivery + a full return, reconciled correctly.
        $this->postJson("/api/v2/business/schedules/runs/{$runId}/reconcile", [
            'items' => [
                ['manifest_item_id' => $itemA, 'delivered_qty' => 7, 'returned_qty' => 1],
                ['manifest_item_id' => $itemB, 'delivered_qty' => 0, 'returned_qty' => 5],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.run.status', 'completed')
            ->assertJsonPath('data.run.manifest_items.0.delivered_qty', 7)
            ->assertJsonPath('data.run.manifest_items.0.remaining_qty', 2)
            ->assertJsonPath('data.run.manifest_items.1.remaining_qty', 0);
    }

    public function test_a_delegated_staff_member_can_run_the_trip(): void
    {
        $agent = $this->makeUser(User::TYPE_CLIENT, 'Driver');
        BusinessStaff::create([
            'business_id' => $this->business->id,
            'user_id' => $agent->id,
            'capabilities' => [BusinessCapability::SCHEDULES],
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->business);
        $leg = $this->schedule(TripSchedule::MODE_PASSENGER);
        $this->addStops($leg, ['A']);

        Sanctum::actingAs($agent);
        $runId = $this->postJson("/api/v2/business/schedules/{$leg->id}/runs", ['passenger_count' => 4])
            ->assertCreated()
            ->json('data.run.id');

        $this->postJson("/api/v2/business/schedules/runs/{$runId}/arrive")->assertOk();
        $this->postJson("/api/v2/business/schedules/runs/{$runId}/advance")
            ->assertOk()
            ->assertJsonPath('data.run.status', 'completed');
    }

    public function test_a_stranger_business_gets_404_on_someone_elses_run(): void
    {
        Sanctum::actingAs($this->business);
        $leg = $this->schedule(TripSchedule::MODE_PASSENGER);
        $this->addStops($leg, ['A', 'B']);
        $runId = $this->postJson("/api/v2/business/schedules/{$leg->id}/runs", ['passenger_count' => 2])
            ->assertCreated()->json('data.run.id');

        $other = $this->makeUser(User::TYPE_BUSINESS, 'Rival');
        Sanctum::actingAs($other);

        $this->postJson("/api/v2/business/schedules/{$leg->id}/runs", ['passenger_count' => 1])->assertNotFound();
        $this->getJson("/api/v2/business/schedules/runs/{$runId}")->assertNotFound();
        $this->postJson("/api/v2/business/schedules/runs/{$runId}/arrive")->assertNotFound();
    }
}
