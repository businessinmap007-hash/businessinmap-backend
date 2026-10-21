<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Project;
use App\Models\ProjectShowcase;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «نتائج موثَّقة»: an office shows a project as a post only after its customer agrees to it; the post carries the progress the
 * customer CONFIRMED, live, with no name, address or unit; either side can take it down.
 */
class ProjectShowcaseTest extends TestCase
{
    use DatabaseTransactions;

    private User $office;
    private User $client;
    private Project $project;
    private ProjectTask $stage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = $this->user('Office', User::TYPE_BUSINESS, 19, 123);
        $this->client = $this->user('Client', User::TYPE_CLIENT);

        $booking = Booking::query()->create([
            'user_id' => $this->client->id, 'business_id' => $this->office->id, 'status' => Booking::STATUS_PENDING,
            'date' => now()->addDay()->toDateString(), 'time' => '12:00:00',
        ]);

        $this->project = Project::create(['business_id' => $this->office->id, 'title' => 'Hassan Villa, 5 Nile St', 'status' => Project::STATUS_PLANNING, 'starts_on' => now()->toDateString(), 'operation_type' => Booking::class, 'operation_id' => $booking->id]);
        $this->stage = $this->project->tasks()->create(['title' => 'Frame', 'requires_photo' => false, 'sort_order' => 1]);
        $this->project->tasks()->create(['parent_id' => $this->stage->id, 'title' => 'Columns flat 7', 'zone' => 'Floor 2 / flat 7', 'weight' => 10, 'requires_photo' => false, 'requires_approval' => true, 'status' => ProjectTask::STATUS_DONE, 'progress' => 100, 'approval_status' => ProjectTask::APPROVAL_APPROVED, 'approval_at' => now(), 'sort_order' => 2]);
        $this->project->tasks()->create(['parent_id' => $this->stage->id, 'title' => 'Slab flat 7', 'zone' => 'Floor 2 / flat 7', 'weight' => 10, 'requires_photo' => false, 'requires_approval' => true, 'status' => ProjectTask::STATUS_DONE, 'progress' => 100, 'approval_status' => ProjectTask::APPROVAL_PENDING, 'sort_order' => 3]);
    }

    private function user(string $tag, string $type, ?int $category = null, ?int $child = null): User
    {
        $u = new User();
        $u->forceFill([
            'name' => $tag . ' ' . Str::random(4), 'type' => $type, 'email' => strtolower($tag) . '-' . uniqid() . '@example.test',
            'phone' => '010' . random_int(10000000, 99999999), 'password' => 'secret-password', 'api_token' => Str::random(60),
            'category_id' => $category, 'category_child_id' => $child,
        ])->save();

        return $u;
    }

    private function agreed(): void
    {
        Sanctum::actingAs($this->office);
        $this->postJson("/api/v2/business/projects/{$this->project->id}/agreement", ['starts_on' => now()->toDateString(), 'due_on' => now()->addDays(60)->toDateString(), 'grace_days' => 30])->assertCreated();
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/operations/booking/{$this->project->operation_id}/project/agreement/agree")->assertOk();
    }

    private function ask(bool $photos = false)
    {
        Sanctum::actingAs($this->office);

        return $this->postJson("/api/v2/business/projects/{$this->project->id}/showcase", ['public_title' => 'A villa in progress', 'note' => 'Frame stage', 'show_photos' => $photos]);
    }

    private function op(): string
    {
        return "/api/v2/operations/booking/{$this->project->operation_id}/project/showcase";
    }

    public function test_a_project_cannot_be_shown_before_the_customer_agrees_to_the_plan(): void
    {
        $this->ask()->assertStatus(422);
        $this->assertSame(0, ProjectShowcase::query()->where('project_id', $this->project->id)->count());
    }

    public function test_nothing_is_published_until_the_customer_approves_and_the_post_shows_only_confirmed_progress_without_names(): void
    {
        $this->agreed();
        $this->ask()->assertCreated()->assertJsonPath('data.showcase.status', 'requested');

        // asked, not approved: no post, nothing on the feed
        $this->assertNull(ProjectShowcase::query()->where('project_id', $this->project->id)->value('post_id'));

        Sanctum::actingAs($this->client);
        $this->postJson($this->op() . '/approve')->assertOk()->assertJsonPath('data.showcase.status', 'approved');

        $showcase = ProjectShowcase::query()->where('project_id', $this->project->id)->firstOrFail();
        $this->assertNotNull($showcase->post_id);

        // the wall of the office shows it, live
        $res = $this->getJson("/api/v2/businesses/{$this->office->id}/posts")->assertOk();
        $post = collect($res->json('data'))->firstWhere('id', $showcase->post_id);
        $this->assertNotNull($post);
        $this->assertSame('A villa in progress', $post['title']);
        $this->assertNull($post['subject'], 'a showcase is not a link to something sold');

        // one of two equal works is confirmed (the other is done but waits for the customer) → 50%, not 100%
        $this->assertSame(50, $post['showcase']['progress']);
        $this->assertSame(1, $post['showcase']['stages'][0]['works_confirmed']);
        $this->assertSame(2, $post['showcase']['stages'][0]['works']);
        $this->assertTrue($post['showcase']['verified']);

        // …and it grows by itself when the customer approves the second one
        $this->project->tasks()->where('title', 'Slab flat 7')->update(['approval_status' => ProjectTask::APPROVAL_APPROVED, 'approval_at' => now()]);
        $again = collect($this->getJson("/api/v2/businesses/{$this->office->id}/posts")->json('data'))->firstWhere('id', $showcase->post_id);
        $this->assertSame(100, $again['showcase']['progress']);

        // no person, place or unit anywhere in what the public reads
        $raw = json_encode($again, JSON_UNESCAPED_UNICODE);
        foreach (['Hassan', 'Nile', 'flat 7', 'Floor 2', $this->client->name] as $leak) {
            $this->assertStringNotContainsString($leak, $raw, "leaked: {$leak}");
        }
    }

    public function test_a_declined_request_publishes_nothing_and_can_be_asked_again(): void
    {
        $this->agreed();
        $this->ask()->assertCreated();

        Sanctum::actingAs($this->client);
        $this->postJson($this->op() . '/decline', ['note' => 'Not now'])->assertOk()->assertJsonPath('data.showcase.status', 'declined');
        $this->assertNull(ProjectShowcase::query()->where('project_id', $this->project->id)->value('post_id'));

        $this->ask()->assertCreated()->assertJsonPath('data.showcase.status', 'requested');
    }

    public function test_either_side_can_take_it_down_and_it_leaves_the_feed(): void
    {
        $this->agreed();
        $this->ask()->assertCreated();
        Sanctum::actingAs($this->client);
        $this->postJson($this->op() . '/approve')->assertOk();
        $postId = (int) ProjectShowcase::query()->where('project_id', $this->project->id)->value('post_id');

        $this->postJson($this->op() . '/withdraw')->assertOk()->assertJsonPath('data.showcase.status', 'withdrawn');
        $this->assertFalse((bool) \App\Models\Post::query()->whereKey($postId)->value('is_active'));
        $this->assertNull(collect($this->getJson("/api/v2/businesses/{$this->office->id}/posts")->json('data'))->firstWhere('id', $postId));
        $this->getJson("/api/v2/posts/{$postId}")->assertNotFound();

        // asked again and agreed again: the same post comes back
        $this->ask()->assertCreated();
        Sanctum::actingAs($this->client);
        $this->postJson($this->op() . '/approve')->assertOk();
        $this->assertSame($postId, (int) ProjectShowcase::query()->where('project_id', $this->project->id)->value('post_id'));
        $this->assertTrue((bool) \App\Models\Post::query()->whereKey($postId)->value('is_active'));

        // the business can take it down too
        Sanctum::actingAs($this->office);
        $this->deleteJson("/api/v2/business/projects/{$this->project->id}/showcase")->assertOk()->assertJsonPath('data.showcase.status', 'withdrawn');
        $this->assertFalse((bool) \App\Models\Post::query()->whereKey($postId)->value('is_active'));
    }

    public function test_only_the_customer_of_the_project_answers_and_only_the_owner_asks(): void
    {
        $this->agreed();
        $stranger = $this->user('Stranger', User::TYPE_CLIENT);
        $other = $this->user('Other', User::TYPE_BUSINESS, 19, 123);

        $this->ask()->assertCreated();

        Sanctum::actingAs($stranger);
        $this->postJson($this->op() . '/approve')->assertStatus(404);

        Sanctum::actingAs($other);
        $this->postJson("/api/v2/business/projects/{$this->project->id}/showcase", ['public_title' => 'x'])->assertNotFound();
    }

    public function test_stage_by_stage_mode_lists_and_counts_only_the_stages_the_customer_approved(): void
    {
        $this->agreed();
        Sanctum::actingAs($this->office);
        $this->postJson("/api/v2/business/projects/{$this->project->id}/showcase", ['public_title' => 'A villa', 'consent_mode' => 'each'])->assertCreated();

        Sanctum::actingAs($this->client);
        $this->postJson($this->op() . '/approve')->assertOk()->assertJsonPath('data.showcase.consent_mode', 'each');

        $postId = (int) ProjectShowcase::query()->where('project_id', $this->project->id)->value('post_id');
        $fetch = fn () => collect($this->getJson("/api/v2/businesses/{$this->office->id}/posts")->json('data'))->firstWhere('id', $postId)['showcase'];

        // approved as a post, but the finished stage is still waiting for his word
        $this->assertSame([], $fetch()['stages']);
        $this->assertSame(0, $fetch()['progress']);
        $units = $this->getJson("/api/v2/operations/booking/{$this->project->operation_id}/project")->json('data.showcase.units');
        $this->assertSame([['key' => (string) $this->stage->id, 'title' => 'Frame', 'status' => 'pending']], $units);

        $this->postJson($this->op() . "/stages/{$this->stage->id}/approve")->assertOk();
        $this->assertCount(1, $fetch()['stages']);
        $this->assertSame(50, $fetch()['progress']);

        $this->postJson($this->op() . "/stages/{$this->stage->id}/decline")->assertOk();
        $this->assertSame([], $fetch()['stages']);

        // a stage that is not finished cannot be answered, and neither can a stranger's
        $this->project->tasks()->where('parent_id', $this->stage->id)->first()->update(['status' => ProjectTask::STATUS_IN_PROGRESS]);
        $this->postJson($this->op() . "/stages/{$this->stage->id}/approve")->assertStatus(422);
    }

    public function test_the_customer_may_turn_a_once_for_all_request_into_stage_by_stage(): void
    {
        $this->agreed();
        $this->ask()->assertCreated()->assertJsonPath('data.showcase.consent_mode', 'once');

        Sanctum::actingAs($this->client);
        $this->postJson($this->op() . '/approve', ['consent_mode' => 'each'])->assertOk()->assertJsonPath('data.showcase.consent_mode', 'each');
    }
}
