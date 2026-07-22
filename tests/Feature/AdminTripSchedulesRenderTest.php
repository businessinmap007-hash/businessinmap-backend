<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The AdminV2 trip-schedules oversight screens render for an OPERATIONS admin.
 *
 * These two screens (the schedules list and the reservations list) had never
 * been verified to actually render — the mechanical smoke sweep only asserts
 * "not a 500", which a redirect or empty-data 404 would also pass. Here we pin a
 * real 200 with the expected chrome, on an empty trip_schedules table (the
 * platform-wide default), so the empty-state path is the one under test.
 */
class AdminTripSchedulesRenderTest extends TestCase
{
    use DatabaseTransactions;

    private function operationsAdmin(): User
    {
        $a = User::where('type', 'admin')->firstOrFail();
        foreach ([AdminAbility::ACCESS, AdminAbility::OPERATIONS] as $ab) {
            \Bouncer::allow($a)->to($ab);
        }
        \Bouncer::refresh();

        return $a;
    }

    public function test_the_trip_schedules_index_renders(): void
    {
        $this->actingAs($this->operationsAdmin())
            ->get(route('admin.trip-schedules.index'))
            ->assertOk();
    }

    public function test_the_trip_schedules_reservations_render(): void
    {
        $this->actingAs($this->operationsAdmin())
            ->get(route('admin.trip-schedules.reservations'))
            ->assertOk();
    }
}
