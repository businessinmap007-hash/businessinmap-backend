<?php

namespace Tests\Feature;

use App\Models\Apply;
use App\Models\JobFollow;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The platform-wide jobs counters (public API) and the AdminV2 oversight
 * screen that shows them next to the followed-fields demand signal.
 *
 * Asserts DELTAS, never absolutes: this runs against the real dev database,
 * which already holds 47 job posts and 143 applications.
 */
class JobsPlatformStatsTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT_CATEGORY_ID = 23; // مصانع
    private const CHILD_A = 8;           // اكسسوار

    private function business(): User
    {
        return User::query()->forceCreate([
            'name' => 'Test Business '.uniqid(),
            'phone' => '01'.random_int(100000000, 999999999),
            'email' => 'biz'.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'api_token' => Str::random(60),
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
            'api_token' => Str::random(60),
            'type' => 'client',
        ]);
    }

    private function stats(): array
    {
        $response = $this->getJson('/api/v2/jobs/stats');
        $response->assertOk();

        return $response->json('data');
    }

    public function test_platform_stats_are_public_and_expose_no_identities(): void
    {
        $data = $this->stats();

        $this->assertSame(
            ['applicants_total', 'approved_total', 'businesses_hiring', 'jobs_open', 'jobs_posted'],
            collect(array_keys($data))->sort()->values()->all(),
            'The public counters must stay aggregates — adding a name here would break the visibility rule.'
        );

        foreach ($data as $key => $value) {
            $this->assertIsInt($value, "{$key} should be an integer count.");
        }
    }

    public function test_posting_a_job_then_applying_then_approving_moves_each_counter(): void
    {
        $before = $this->stats();

        $business = $this->business();
        $post = Post::create([
            'type' => 'job', 'user_id' => $business->id, 'is_active' => true,
            'category_id' => self::ROOT_CATEGORY_ID, 'category_child_id' => self::CHILD_A,
            'title_ar' => 'وظيفة عدّاد', 'body' => 'وصف',
        ]);

        $afterPost = $this->stats();
        $this->assertSame($before['jobs_posted'] + 1, $afterPost['jobs_posted']);
        $this->assertSame($before['jobs_open'] + 1, $afterPost['jobs_open']);
        $this->assertSame($before['applicants_total'], $afterPost['applicants_total']);

        $apply = Apply::create(['post_id' => $post->id, 'user_id' => $this->client()->id]);

        $afterApply = $this->stats();
        $this->assertSame($before['applicants_total'] + 1, $afterApply['applicants_total']);
        $this->assertSame($before['approved_total'], $afterApply['approved_total']);

        $apply->approved_at = now();
        $apply->save();

        $afterApprove = $this->stats();
        $this->assertSame($before['approved_total'] + 1, $afterApprove['approved_total']);
        // Approving hires someone; it does not close the vacancy.
        $this->assertSame($afterPost['jobs_open'], $afterApprove['jobs_open']);
    }

    public function test_closing_a_job_drops_it_from_open_but_not_from_posted(): void
    {
        $business = $this->business();
        $post = Post::create([
            'type' => 'job', 'user_id' => $business->id, 'is_active' => true,
            'category_id' => self::ROOT_CATEGORY_ID, 'category_child_id' => self::CHILD_A,
            'title_ar' => 'وظيفة تُغلق', 'body' => 'وصف',
        ]);

        $open = $this->stats();

        $post->is_active = false;
        $post->save();

        $closed = $this->stats();

        $this->assertSame($open['jobs_open'] - 1, $closed['jobs_open']);
        $this->assertSame($open['jobs_posted'], $closed['jobs_posted']);
    }

    public function test_admin_screen_renders_the_counters_and_the_followed_field(): void
    {
        $admin = User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin account to act as.');
        }

        $follower = $this->client();
        JobFollow::create([
            'user_id' => $follower->id,
            'category_id' => self::ROOT_CATEGORY_ID,
            'category_child_id' => self::CHILD_A,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.job-follows.index'));

        $response->assertOk();
        $response->assertSee('متابعات الوظائف', false);
        $response->assertSee('أكثر المجالات متابعة', false);
        $response->assertSee($follower->name, false);
        // The field the follow points at, resolved to its Arabic name.
        $response->assertSee('اكسسوار', false);
    }

    public function test_admin_screen_search_filters_the_follows_list(): void
    {
        $admin = User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin account to act as.');
        }

        $wanted = $this->client();
        $other = $this->client();

        foreach ([$wanted, $other] as $u) {
            JobFollow::create([
                'user_id' => $u->id,
                'category_id' => self::ROOT_CATEGORY_ID,
                'category_child_id' => self::CHILD_A,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($admin)
            ->get(route('admin.job-follows.index', ['q' => $wanted->name]));

        $response->assertOk();
        $response->assertSee($wanted->name, false);
        $response->assertDontSee($other->name, false);
    }
}
