<?php

namespace Tests\Feature;

use App\Models\Apply;
use App\Models\JobFollow;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The second Jobs slice: a business accepts an applicant (with a counter
 * dashboard), and a user follows a job field to be notified live when a
 * vacancy is posted there. The follow → notification path is asserted by the
 * app_notifications row the dispatcher writes (Firebase is skipped in tests
 * but the in-app notification is always created). Rolls back.
 */
class JobFollowsAndApprovalApiTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT_CATEGORY_ID = 23; // مصانع
    private const CHILD_A = 8;            // اكسسوار
    private const CHILD_B = 34;           // طوب

    private function business(): User
    {
        return User::query()->forceCreate([
            'name' => 'Test Business '.uniqid(),
            'phone' => '01'.random_int(100000000, 999999999),
            'email' => 'biz'.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'api_token' => \Illuminate\Support\Str::random(60),
            'type' => 'business',
        ]);
    }

    private function client(): User
    {
        return User::query()->forceCreate([
            'name' => 'Test Client '.uniqid(),
            'phone' => '01'.random_int(100000000, 999999999),
            'email' => 'client'.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'api_token' => \Illuminate\Support\Str::random(60),
            'type' => 'client',
        ]);
    }

    private function job(User $business, int $childId = self::CHILD_A): Post
    {
        return Post::create([
            'type' => 'job', 'user_id' => $business->id, 'is_active' => true,
            'category_id' => self::ROOT_CATEGORY_ID, 'category_child_id' => $childId,
            'title' => 'وظيفة', 'body' => 'وصف',
        ]);
    }

    // ─────────────────── approval + counters ───────────────────

    public function test_the_posting_business_can_accept_an_applicant_and_it_notifies_them(): void
    {
        $business = $this->business();
        $applicant = $this->client();
        $post = $this->job($business);
        $apply = Apply::create(['post_id' => $post->id, 'user_id' => $applicant->id]);

        $response = $this->actingAs($business, 'sanctum')
            ->postJson("/api/v2/jobs/{$post->id}/applicants/{$apply->id}/approve");

        $response->assertOk();
        $this->assertNotNull($response->json('data.approved_at'));
        $this->assertNotNull($apply->fresh()->approved_at);

        // The applicant got an in-app notification.
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $applicant->id,
            'source_type' => 'job_application_approved',
            'source_id' => $apply->id,
        ]);
    }

    public function test_a_stranger_business_cannot_accept_an_applicant(): void
    {
        $business = $this->business();
        $other = $this->business();
        $applicant = $this->client();
        $post = $this->job($business);
        $apply = Apply::create(['post_id' => $post->id, 'user_id' => $applicant->id]);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/v2/jobs/{$post->id}/applicants/{$apply->id}/approve")
            ->assertStatus(403);

        $this->assertNull($apply->fresh()->approved_at);
    }

    public function test_approval_is_idempotent_and_does_not_double_notify(): void
    {
        $business = $this->business();
        $applicant = $this->client();
        $post = $this->job($business);
        $apply = Apply::create(['post_id' => $post->id, 'user_id' => $applicant->id]);

        $this->actingAs($business, 'sanctum')->postJson("/api/v2/jobs/{$post->id}/applicants/{$apply->id}/approve")->assertOk();
        $this->actingAs($business, 'sanctum')->postJson("/api/v2/jobs/{$post->id}/applicants/{$apply->id}/approve")->assertOk();

        $this->assertSame(1, DB::table('app_notifications')
            ->where('source_type', 'job_application_approved')->where('source_id', $apply->id)->count());
    }

    public function test_an_apply_from_another_job_cannot_be_approved_via_this_job(): void
    {
        $business = $this->business();
        $applicant = $this->client();
        $postA = $this->job($business, self::CHILD_A);
        $postB = $this->job($business, self::CHILD_B);
        $applyOnB = Apply::create(['post_id' => $postB->id, 'user_id' => $applicant->id]);

        // Try to approve B's application through A's URL.
        $this->actingAs($business, 'sanctum')
            ->postJson("/api/v2/jobs/{$postA->id}/applicants/{$applyOnB->id}/approve")
            ->assertStatus(404);
    }

    public function test_stats_counts_jobs_applicants_and_approved(): void
    {
        $business = $this->business();
        $c1 = $this->client();
        $c2 = $this->client();

        $post1 = $this->job($business, self::CHILD_A);
        $post2 = $this->job($business, self::CHILD_B);
        Apply::create(['post_id' => $post1->id, 'user_id' => $c1->id]);
        $a2 = Apply::create(['post_id' => $post1->id, 'user_id' => $c2->id]);
        Apply::create(['post_id' => $post2->id, 'user_id' => $c1->id]);
        $this->actingAs($business, 'sanctum')->postJson("/api/v2/jobs/{$post1->id}/applicants/{$a2->id}/approve")->assertOk();

        $response = $this->actingAs($business, 'sanctum')->getJson('/api/v2/jobs/mine/stats');
        $response->assertOk();
        $response->assertJsonPath('data.jobs_posted', 2);
        $response->assertJsonPath('data.applicants_total', 3);
        $response->assertJsonPath('data.approved_total', 1);
    }

    public function test_close_stops_public_listing_and_new_applications(): void
    {
        $business = $this->business();
        $client = $this->client();
        $post = $this->job($business);

        $this->actingAs($business, 'sanctum')->postJson("/api/v2/jobs/{$post->id}/close")->assertOk();
        $this->assertFalse((bool) $post->fresh()->is_active);

        $this->actingAs($client, 'sanctum')->postJson("/api/v2/jobs/{$post->id}/apply")->assertStatus(422);

        $ids = collect($this->getJson('/api/v2/jobs')->json('data.data'))->pluck('id');
        $this->assertFalse($ids->contains($post->id));
    }

    // ─────────────────── follows + notification ───────────────────

    public function test_a_user_can_follow_a_specialty_and_unfollow(): void
    {
        $follower = $this->client();

        $created = $this->actingAs($follower, 'sanctum')->postJson('/api/v2/jobs/follows', [
            'category_child_id' => self::CHILD_A,
        ]);
        $created->assertCreated();
        $id = $created->json('data.follow.id');

        $this->assertDatabaseHas('job_follows', [
            'id' => $id, 'user_id' => $follower->id, 'category_child_id' => self::CHILD_A,
        ]);

        $this->actingAs($follower, 'sanctum')->deleteJson("/api/v2/jobs/follows/{$id}")->assertOk();
        $this->assertDatabaseMissing('job_follows', ['id' => $id]);
    }

    public function test_following_needs_a_category_or_a_specialty(): void
    {
        $follower = $this->client();
        $this->actingAs($follower, 'sanctum')->postJson('/api/v2/jobs/follows', [])->assertStatus(422);
    }

    public function test_posting_a_job_notifies_a_specialty_follower(): void
    {
        $business = $this->business();
        $follower = $this->client();

        JobFollow::create(['user_id' => $follower->id, 'category_id' => self::ROOT_CATEGORY_ID, 'category_child_id' => self::CHILD_A, 'is_active' => true]);

        $this->actingAs($business, 'sanctum')->postJson('/api/v2/jobs', [
            'category_id' => self::ROOT_CATEGORY_ID,
            'category_child_id' => self::CHILD_A,
            'title' => 'مدير حسابات',
            'body' => 'وصف',
        ])->assertCreated();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $follower->id,
            'source_type' => 'job_posted',
        ]);
    }

    public function test_a_root_category_follower_is_notified_but_a_different_specialty_follower_is_not(): void
    {
        $business = $this->business();
        $rootFollower = $this->client();
        $wrongChildFollower = $this->client();

        // Follows the whole «مصانع» root (no child).
        JobFollow::create(['user_id' => $rootFollower->id, 'category_id' => self::ROOT_CATEGORY_ID, 'category_child_id' => null, 'is_active' => true]);
        // Follows a different specialty under the same root.
        JobFollow::create(['user_id' => $wrongChildFollower->id, 'category_id' => self::ROOT_CATEGORY_ID, 'category_child_id' => self::CHILD_B, 'is_active' => true]);

        $this->actingAs($business, 'sanctum')->postJson('/api/v2/jobs', [
            'category_id' => self::ROOT_CATEGORY_ID,
            'category_child_id' => self::CHILD_A,
            'title' => 'وظيفة',
            'body' => 'وصف',
        ])->assertCreated();

        $this->assertDatabaseHas('app_notifications', ['user_id' => $rootFollower->id, 'source_type' => 'job_posted']);
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $wrongChildFollower->id, 'source_type' => 'job_posted']);
    }

    public function test_the_poster_is_never_notified_of_their_own_job(): void
    {
        $business = $this->business();
        // The business itself follows the field it is about to post in.
        JobFollow::create(['user_id' => $business->id, 'category_id' => self::ROOT_CATEGORY_ID, 'category_child_id' => self::CHILD_A, 'is_active' => true]);

        $this->actingAs($business, 'sanctum')->postJson('/api/v2/jobs', [
            'category_id' => self::ROOT_CATEGORY_ID,
            'category_child_id' => self::CHILD_A,
            'title' => 'وظيفة',
            'body' => 'وصف',
        ])->assertCreated();

        $this->assertDatabaseMissing('app_notifications', ['user_id' => $business->id, 'source_type' => 'job_posted']);
    }
}
