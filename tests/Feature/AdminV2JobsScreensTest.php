<?php

namespace Tests\Feature;

use App\Models\Apply;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Renders the AdminV2 Jobs screens after adding the job-specific fields
 * (category/salary/requirements/interview_starts_at) and the applicant
 * oversight view that didn't exist before this — verified by rendering, not
 * by reading the blade source. Rolls back.
 */
class AdminV2JobsScreensTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $admin = User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin account to act as.');
        }

        return $admin;
    }

    public function test_edit_screen_renders_the_new_job_fields(): void
    {
        $business = User::query()->where('type', 'business')->first();
        $post = Post::create([
            'type' => 'job', 'user_id' => $business->id, 'is_active' => true,
            'title' => 'وظيفة اختبار', 'body' => 'وصف', 'salary' => 'يحدد بعد المقابلة',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.jobs.edit', ['post' => $post->id]));

        $response->assertOk();
        $response->assertSee('name="salary"', false);
        $response->assertSee('name="requirements"', false);
        $response->assertSee('name="interview_starts_at"', false);
        $response->assertSee('name="category_id"', false);
        $response->assertSee('name="category_child_id"', false);
        $response->assertSee('يحدد بعد المقابلة', false);
    }

    public function test_show_screen_renders_the_new_fields_and_applicants_link(): void
    {
        $business = User::query()->where('type', 'business')->first();
        $post = Post::create([
            'type' => 'job', 'user_id' => $business->id, 'is_active' => true,
            'title' => 'وظيفة اختبار', 'body' => 'وصف', 'salary' => '10000 جنيه',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.jobs.show', ['post' => $post->id]));

        $response->assertOk();
        $response->assertSee('10000 جنيه', false);
        $response->assertSee(route('admin.jobs.applicants', ['post' => $post->id]), false);
    }

    public function test_index_screen_generates_working_show_and_delete_links(): void
    {
        // Route::resource('jobs', ...) derives {job} by default; every blade
        // view calls route(..., ['post' => ...]). Without ->parameter('jobs',
        // 'post') every generated link here throws "Missing required
        // parameter" — this is what actually caught it.
        $business = User::query()->where('type', 'business')->first();
        $post = Post::create([
            'type' => 'job', 'user_id' => $business->id, 'is_active' => true,
            'title' => 'وظيفة اختبار', 'body' => 'وصف',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.jobs.index'));

        $response->assertOk();
        $response->assertSee(route('admin.jobs.show', ['post' => $post->id]), false);
    }

    public function test_applicants_screen_renders_the_real_applicant(): void
    {
        $business = User::query()->where('type', 'business')->first();
        $client = User::query()->where('type', 'client')->first();
        $post = Post::create([
            'type' => 'job', 'user_id' => $business->id, 'is_active' => true,
            'title' => 'وظيفة اختبار', 'body' => 'وصف',
        ]);
        Apply::create(['post_id' => $post->id, 'user_id' => $client->id]);

        $response = $this->actingAs($this->admin())->get(route('admin.jobs.applicants', ['post' => $post->id]));

        $response->assertOk();
        $response->assertSee($client->name, false);
    }
}
