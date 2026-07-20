<?php

namespace Tests\Feature;

use App\Models\Apply;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * v2 Jobs: a business posts a vacancy in any field, a client applies. v1's
 * equivalent (Api\V1\JobController/ApplyController) referenced App\Models\Job
 * and App\Company, neither of which exist — every method there fatal-errors.
 * This is a fresh build on the one part of v1 that WAS real: `posts` with
 * type='job' + the `applies` table (47 live job posts, 143 applications).
 *
 * Core rule under test: the public sees a job and an applicant COUNT; only
 * the posting business sees who applied. Rolls back.
 */
class JobsApiTest extends TestCase
{
    use DatabaseTransactions;

    // «مصانع» root and two of its real children, reused rather than invented.
    private const ROOT_CATEGORY_ID = 23;

    private const CHILD_A = 8;   // اكسسوار

    private const CHILD_B = 34;  // طوب

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

    public function test_a_business_can_post_a_job_with_a_free_text_salary(): void
    {
        $business = $this->business();

        $response = $this->actingAs($business, 'sanctum')->postJson('/api/v2/jobs', [
            'category_id' => self::ROOT_CATEGORY_ID,
            'category_child_id' => self::CHILD_A,
            'title' => 'مدير حسابات',
            'body' => 'مطلوب مدير حسابات لمصنع اكسسوارات',
            'requirements' => 'خبرة 5 سنوات على الأقل',
            'salary' => 'يحدد بعد المقابلة',
            'interview_starts_at' => now()->addDays(3)->toDateTimeString(),
            'expire_at' => now()->addDays(20)->toDateTimeString(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.salary', 'يحدد بعد المقابلة');
        $this->assertDatabaseHas('posts', [
            'type' => 'job',
            'user_id' => $business->id,
            'category_id' => self::ROOT_CATEGORY_ID,
            'category_child_id' => self::CHILD_A,
        ]);
    }

    public function test_the_job_body_actually_persists(): void
    {
        // Caught by hitting the real running server, not by this test suite:
        // Post::$fillable listed body_ar/body_en, columns that no longer
        // exist (unified into `body` by 2026_02_16_180855) — every job
        // description ever posted through store() was silently discarded.
        $business = $this->business();

        $response = $this->actingAs($business, 'sanctum')->postJson('/api/v2/jobs', [
            'category_id' => self::ROOT_CATEGORY_ID,
            'title' => 'وظيفة',
            'body' => 'وصف الوظيفة الحقيقي',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.body', 'وصف الوظيفة الحقيقي');
        $this->assertDatabaseHas('posts', ['body' => 'وصف الوظيفة الحقيقي']);
    }

    public function test_a_client_account_cannot_post_a_job(): void
    {
        $client = $this->client();

        $this->actingAs($client, 'sanctum')->postJson('/api/v2/jobs', [
            'category_id' => self::ROOT_CATEGORY_ID,
            'title' => 'وظيفة',
            'body' => 'وصف',
        ])->assertStatus(403);
    }

    public function test_category_child_must_belong_to_the_given_category(): void
    {
        $business = $this->business();

        // CHILD_A does not belong to a category far from its real root.
        $wrongRoot = DB::table('categories')->where('id', '!=', self::ROOT_CATEGORY_ID)->value('id');

        $this->actingAs($business, 'sanctum')->postJson('/api/v2/jobs', [
            'category_id' => $wrongRoot,
            'category_child_id' => self::CHILD_A,
            'title' => 'وظيفة',
            'body' => 'وصف',
        ])->assertStatus(422);
    }

    public function test_public_listing_shows_a_count_never_applicant_identities(): void
    {
        $business = $this->business();
        $applicant = $this->client();

        $post = Post::create([
            'type' => 'job', 'user_id' => $business->id, 'is_active' => true,
            'category_id' => self::ROOT_CATEGORY_ID, 'category_child_id' => self::CHILD_B,
            'title' => 'محاسب', 'body' => 'وصف الوظيفة',
        ]);
        Apply::create(['post_id' => $post->id, 'user_id' => $applicant->id]);

        $response = $this->getJson('/api/v2/jobs/'.$post->id);

        $response->assertOk();
        $response->assertJsonPath('data.applicants_count', 1);
        $response->assertJsonMissingPath('data.applicants');
        $this->assertStringNotContainsString($applicant->name, $response->getContent());
    }

    public function test_a_client_can_apply_once_and_not_twice(): void
    {
        $business = $this->business();
        $client = $this->client();

        $post = Post::create([
            'type' => 'job', 'user_id' => $business->id, 'is_active' => true,
            'category_id' => self::ROOT_CATEGORY_ID, 'category_child_id' => self::CHILD_A,
            'title' => 'محاسب', 'body' => 'وصف الوظيفة',
        ]);

        $first = $this->actingAs($client, 'sanctum')->postJson("/api/v2/jobs/{$post->id}/apply");
        $first->assertCreated();

        $second = $this->actingAs($client, 'sanctum')->postJson("/api/v2/jobs/{$post->id}/apply");
        $second->assertStatus(422);

        $this->assertSame(1, Apply::where('post_id', $post->id)->where('user_id', $client->id)->count());
    }

    public function test_a_business_cannot_apply_to_its_own_job(): void
    {
        $business = $this->business();

        $post = Post::create([
            'type' => 'job', 'user_id' => $business->id, 'is_active' => true,
            'category_id' => self::ROOT_CATEGORY_ID, 'title' => 'محاسب', 'body' => 'وصف',
        ]);

        $this->actingAs($business, 'sanctum')->postJson("/api/v2/jobs/{$post->id}/apply")->assertStatus(422);
    }

    public function test_only_the_posting_business_sees_applicant_details(): void
    {
        $business = $this->business();
        $otherBusiness = $this->business();
        $applicant = $this->client();

        $post = Post::create([
            'type' => 'job', 'user_id' => $business->id, 'is_active' => true,
            'category_id' => self::ROOT_CATEGORY_ID, 'title' => 'محاسب', 'body' => 'وصف',
        ]);
        Apply::create(['post_id' => $post->id, 'user_id' => $applicant->id]);

        $this->actingAs($otherBusiness, 'sanctum')
            ->getJson("/api/v2/jobs/{$post->id}/applicants")
            ->assertStatus(403);

        $owner = $this->actingAs($business, 'sanctum')->getJson("/api/v2/jobs/{$post->id}/applicants");
        $owner->assertOk();
        $owner->assertJsonPath('data.data.0.user.name', $applicant->name);
    }

    public function test_categories_endpoint_only_lists_branches_with_a_job_and_sums_correctly(): void
    {
        $business = $this->business();

        Post::create(['type' => 'job', 'user_id' => $business->id, 'is_active' => true, 'category_id' => self::ROOT_CATEGORY_ID, 'category_child_id' => self::CHILD_A, 'title' => 'أ', 'body' => 'ب']);
        Post::create(['type' => 'job', 'user_id' => $business->id, 'is_active' => true, 'category_id' => self::ROOT_CATEGORY_ID, 'category_child_id' => self::CHILD_A, 'title' => 'أ2', 'body' => 'ب']);
        Post::create(['type' => 'job', 'user_id' => $business->id, 'is_active' => true, 'category_id' => self::ROOT_CATEGORY_ID, 'category_child_id' => self::CHILD_B, 'title' => 'ج', 'body' => 'د']);

        $response = $this->getJson('/api/v2/jobs/categories');
        $response->assertOk();

        $root = collect($response->json('data'))->firstWhere('id', self::ROOT_CATEGORY_ID);
        $this->assertNotNull($root, 'the root with fresh jobs must appear');
        $this->assertSame(3, $root['jobs_count']);

        $childCounts = collect($root['children'])->pluck('jobs_count', 'id');
        $this->assertSame(2, $childCounts[self::CHILD_A]);
        $this->assertSame(1, $childCounts[self::CHILD_B]);
    }

    public function test_expired_or_inactive_jobs_are_not_publicly_listed(): void
    {
        $business = $this->business();

        $expired = Post::create([
            'type' => 'job', 'user_id' => $business->id, 'is_active' => true,
            'category_id' => self::ROOT_CATEGORY_ID, 'title' => 'منتهية', 'body' => 'ب',
            'expire_at' => now()->subDay(),
        ]);
        $inactive = Post::create([
            'type' => 'job', 'user_id' => $business->id, 'is_active' => false,
            'category_id' => self::ROOT_CATEGORY_ID, 'title' => 'موقوفة', 'body' => 'ب',
        ]);

        $this->getJson('/api/v2/jobs/'.$expired->id.'')->assertOk(); // show() bypasses the open scope by design (owner needs to see it too)
        $ids = collect($this->getJson('/api/v2/jobs')->json('data.data'))->pluck('id');
        $this->assertFalse($ids->contains($expired->id));
        $this->assertFalse($ids->contains($inactive->id));
    }
}
