<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\Image;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Following a project's progress: the request→approval flow with access tiers
 * (summary map+% vs. detailed evidence), a public project open to all, and the
 * push that goes out when a stage is completed.
 */
class ProjectFollowTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $type): User
    {
        $u = new User();
        $u->name = ucfirst($type) . ' ' . Str::random(4);
        $u->email = strtolower($type) . '-' . uniqid() . '@example.test';
        $u->phone = '0103' . random_int(1000000, 9999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    public function test_a_public_project_shows_the_summary_map_to_anyone_but_no_details(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS);
        $project = Project::create([
            'business_id' => $business->id,
            'title' => 'Public tower',
            'visibility' => Project::VISIBILITY_PUBLIC,
        ]);
        $task = $project->tasks()->create(['title' => 'Slab', 'progress' => 30]);
        $task->photos()->create(['image' => 'files/uploads/x.jpg', 'source' => Image::SOURCE_CAMERA]);

        Sanctum::actingAs($this->makeUser(User::TYPE_CLIENT));
        $res = $this->getJson("/api/v2/projects/{$project->id}")->assertOk();

        $res->assertJsonPath('data.access_level', 'summary');
        $res->assertJsonPath('data.timeline.tasks.0.progress', 30);
        // The coarse view carries the map + %s but never the evidence photos.
        $this->assertArrayNotHasKey('tasks', $res->json('data'));
        $this->assertStringNotContainsString('photos', json_encode($res->json('data')));
    }

    public function test_a_private_project_is_invisible_until_approved(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS);
        $project = Project::create(['business_id' => $business->id, 'title' => 'Private']);
        $project->tasks()->create(['title' => 'A', 'progress' => 10]);

        $follower = $this->makeUser(User::TYPE_CLIENT);
        Sanctum::actingAs($follower);

        // Invisible before any grant.
        $this->getJson("/api/v2/projects/{$project->id}")->assertNotFound();

        // The business grants detailed access directly.
        Sanctum::actingAs($business);
        $this->patchJson("/api/v2/business/projects/{$project->id}/followers/{$follower->id}", [
            'approve' => true,
            'access_level' => 'detailed',
        ])->assertOk()->assertJsonPath('data.follower.access_level', 'detailed');

        // Now the follower sees the detailed view.
        Sanctum::actingAs($follower);
        $this->getJson("/api/v2/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.access_level', 'detailed');
    }

    public function test_request_then_approve_summary_grants_only_the_coarse_view(): void
    {
        $business = $this->makeUser(User::TYPE_BUSINESS);
        $project = Project::create([
            'business_id' => $business->id,
            'title' => 'Villas',
            'visibility' => Project::VISIBILITY_PUBLIC,
        ]);

        $follower = $this->makeUser(User::TYPE_CLIENT);
        Sanctum::actingAs($follower);
        $this->postJson("/api/v2/projects/{$project->id}/follow")
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        // Business sees the request and approves it at summary level.
        Sanctum::actingAs($business);
        $this->getJson("/api/v2/business/projects/{$project->id}/followers")
            ->assertOk()
            ->assertJsonPath('data.followers.0.status', 'pending');
        $this->patchJson("/api/v2/business/projects/{$project->id}/followers/{$follower->id}", [
            'approve' => true,
        ])->assertOk();

        Sanctum::actingAs($follower);
        $this->getJson("/api/v2/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.access_level', 'summary');
    }

    public function test_completing_a_stage_notifies_the_contracted_customer(): void
    {
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

        $project = Project::create([
            'business_id' => $booking->business_id,
            'title' => 'Kitchen fit-out',
            'operation_type' => (new Booking())->getMorphClass(),
            'operation_id' => $booking->id,
        ]);
        $task = $project->tasks()->create(['title' => 'Install', 'requires_photo' => false]);

        Sanctum::actingAs($booking->business);
        $this->patchJson("/api/v2/business/projects/{$project->id}/tasks/{$task->id}/progress", [
            'status' => 'done',
        ])->assertOk();

        $notified = AppNotification::query()
            ->where('notifiable_type', Project::class)
            ->where('notifiable_id', $project->id)
            ->where('source_type', ProjectTask::class)
            ->where('source_id', $task->id)
            ->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $this->assertContains((int) $booking->user_id, $notified);
        $this->assertNotContains((int) $booking->business_id, $notified);
    }
}
