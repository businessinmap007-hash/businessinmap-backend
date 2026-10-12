<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Image;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The contracted customer following build/manufacturing progress on the project
 * the business linked to their operation — read-only, party-only.
 */
class CustomerProjectFollowTest extends TestCase
{
    use DatabaseTransactions;

    private Booking $booking;
    private User $client;
    private User $business;

    protected function setUp(): void
    {
        parent::setUp();

        $booking = Booking::withTrashed()
            ->whereNotNull('user_id')->whereNotNull('business_id')
            ->whereColumn('user_id', '!=', 'business_id')
            ->first();

        if ($booking && $booking->trashed()) {
            $booking->restore();
        }

        if (! $booking || ! $booking->user || ! $booking->business) {
            $this->markTestSkipped('Needs a booking with a client and a business.');
        }

        $this->booking = $booking;
        $this->client = $booking->user;
        $this->business = $booking->business;
    }

    private function makeBusiness(): User
    {
        $u = new User();
        $u->name = 'Follow Shop ' . Str::random(4);
        $u->email = 'follow-' . uniqid() . '@example.test';
        $u->phone = '0102' . random_int(1000000, 9999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_BUSINESS;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    public function test_a_business_links_an_operation_and_the_customer_follows_progress(): void
    {
        // Business links a project to its own booking, with an evidenced stage.
        Sanctum::actingAs($this->business);
        $projectId = $this->postJson('/api/v2/business/projects', [
            'title' => 'Bedroom set build',
            'operation_type' => 'booking',
            'operation_id' => $this->booking->id,
        ])->assertCreated()
            ->assertJsonPath('data.project.operation.type', 'booking')
            ->assertJsonPath('data.project.operation.id', (int) $this->booking->id)
            ->json('data.project.id');

        $project = Project::find($projectId);
        $task = $project->tasks()->create(['title' => 'Assembly', 'progress' => 40]);
        $task->photos()->create(['image' => 'files/uploads/test-evidence.jpg', 'source' => Image::SOURCE_CAMERA]);

        // The contracted customer reads the progress — read only.
        Sanctum::actingAs($this->client);
        $res = $this->getJson("/api/v2/operations/booking/{$this->booking->id}/project")->assertOk();

        $res->assertJsonPath('data.project.id', (int) $projectId);
        $res->assertJsonPath('data.tasks.0.title', 'Assembly');
        $res->assertJsonPath('data.tasks.0.photos.0.is_camera', true);
        $this->assertSame('camera', $res->json('data.tasks.0.photos.0.source'));
    }

    public function test_a_booking_says_whether_it_has_a_timeline_so_the_button_shows_only_then(): void
    {
        Sanctum::actingAs($this->client);
        $this->getJson("/api/v2/bookings/{$this->booking->id}")->assertOk()->assertJsonPath('data.booking.has_project', false);

        Sanctum::actingAs($this->business);
        $this->postJson('/api/v2/business/projects', [
            'title' => 'Bedroom set build',
            'operation_type' => 'booking',
            'operation_id' => $this->booking->id,
        ])->assertCreated();

        Sanctum::actingAs($this->client);
        $this->getJson("/api/v2/bookings/{$this->booking->id}")->assertOk()->assertJsonPath('data.booking.has_project', true);

        // and in the list the customer reads
        $row = collect($this->getJson('/api/v2/bookings?scope=client&per_page=100')->assertOk()->json('data.bookings.data'))
            ->firstWhere('id', (int) $this->booking->id);
        $this->assertTrue($row === null || $row['has_project'] === true);
    }

    public function test_a_stranger_cannot_read_the_operations_progress(): void
    {
        Sanctum::actingAs($this->business);
        $this->postJson('/api/v2/business/projects', [
            'title' => 'Private build',
            'operation_type' => 'booking',
            'operation_id' => $this->booking->id,
        ])->assertCreated();

        $stranger = $this->makeBusiness();
        Sanctum::actingAs($stranger);
        $this->getJson("/api/v2/operations/booking/{$this->booking->id}/project")->assertNotFound();
    }

    public function test_an_operation_with_no_linked_project_returns_null(): void
    {
        Sanctum::actingAs($this->client);
        $this->getJson("/api/v2/operations/booking/{$this->booking->id}/project")
            ->assertOk()
            ->assertJsonPath('data.project', null);
    }

    public function test_a_business_cannot_link_an_operation_it_does_not_own(): void
    {
        // A different business tries to pin its project to this booking.
        $other = $this->makeBusiness();
        Sanctum::actingAs($other);

        $this->postJson('/api/v2/business/projects', [
            'title' => 'Hijack',
            'operation_type' => 'booking',
            'operation_id' => $this->booking->id,
        ])->assertStatus(422)->assertJsonValidationErrors('operation_id');
    }
}
