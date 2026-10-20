<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\PlatformEventFee;
use App\Models\Project;
use App\Models\ProjectAgreement;
use App\Models\ProjectChangeRequest;
use App\Models\ProjectTask;
use App\Models\RatingOutcomeEvent;
use App\Models\User;
use App\Models\UserOperationRating;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Projects, third round: a building generated floor by floor and flat by flat, quantities the engineer edits with a log the
 * customer reads, the agreement (delivery date + grace) recorded as immutable versions, extensions and scope changes only with
 * the customer's word, a main stage starting only with his recorded approval (and both sides paying for it), and the rating
 * judging the job by the agreed date + grace.
 */
class ProjectBuildingAgreementTest extends TestCase
{
    use DatabaseTransactions;

    private User $office;
    private User $client;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = $this->user('Office', User::TYPE_BUSINESS, 19, 123);
        $this->client = $this->user('Client', User::TYPE_CLIENT);

        $this->booking = Booking::query()->create([
            'user_id' => $this->client->id, 'business_id' => $this->office->id, 'status' => Booking::STATUS_PENDING,
            'date' => now()->addDay()->toDateString(), 'time' => '12:00:00',
        ]);
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

    /** A project with a few works, linked to the booking. */
    private function project(int $works = 2, ?Booking $booking = null): Project
    {
        $booking ??= $this->booking;
        $project = Project::create(['business_id' => $this->office->id, 'title' => 'Tower', 'status' => Project::STATUS_PLANNING, 'starts_on' => now()->toDateString(), 'operation_type' => Booking::class, 'operation_id' => $booking->id]);
        $phase = $project->tasks()->create(['title' => 'Frame', 'requires_photo' => false, 'sort_order' => 1]);
        for ($i = 1; $i <= $works; $i++) {
            $project->tasks()->create(['parent_id' => $phase->id, 'title' => 'Work ' . $i, 'zone' => 'Floor ' . $i, 'weight' => 10, 'requires_photo' => false, 'sort_order' => $i + 1]);
        }

        return $project;
    }

    private function fund(User $u, float $amount = 500): void
    {
        Wallet::query()->updateOrCreate(['user_id' => $u->id], ['balance' => $amount, 'locked_balance' => 0, 'total_in' => $amount, 'total_out' => 0, 'status' => 'active']);
    }

    private function price(string $event, float $business, float $client): void
    {
        foreach (['business' => $business, 'client' => $client] as $role => $amount) {
            PlatformEventFee::query()->updateOrCreate(['event_key' => $event, 'role' => $role], ['enabled' => $amount > 0, 'calc_type' => 'fixed', 'amount' => $amount]);
        }
    }

    private function agreeToPlan(Project $project, string $due = '+60 days', int $grace = 30): void
    {
        Sanctum::actingAs($this->office);
        $this->postJson("/api/v2/business/projects/{$project->id}/agreement", ['due_on' => Carbon::parse($due)->toDateString(), 'grace_days' => $grace])->assertCreated();
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/operations/booking/{$project->operation_id}/project/agreement/agree")->assertOk();
    }

    /* ------------------------------------------------------------------ the building */

    public function test_a_building_is_generated_floor_by_floor_and_flat_by_flat_with_its_places_and_quantities(): void
    {
        $project = Project::create(['business_id' => $this->office->id, 'title' => 'Residential block', 'status' => Project::STATUS_PLANNING]);

        Sanctum::actingAs($this->office);
        $res = $this->postJson("/api/v2/business/projects/{$project->id}/generate", ['kind' => 'building', 'floors' => 5, 'basement' => true, 'apartments_per_floor' => 3, 'starts_on' => '2027-01-01'])
            ->assertCreated();

        // 6 main stages; works: foundations 3 + frame 6 levels×2 + masonry 6 + installs 15 flats×2 + finishing 15×7 + handover 1
        $this->assertSame(3 + 12 + 6 + 30 + 105 + 1, $res->json('data.works'));
        $this->assertSame(6, $project->tasks()->whereNull('parent_id')->count());

        // a flat's work carries its place; the trades of one flat are chained
        $paint = $project->tasks()->where('title', 'النقاشة')->where('zone', 'الدور الثالث / شقة 2')->firstOrFail();
        $this->assertSame(1, $paint->dependencies()->count());
        $this->assertSame('المساحة', $paint->items()->first()->name);

        // the basement has a frame but no flats
        $this->assertTrue($project->tasks()->where('zone', 'like', 'البدروم%')->where('title', 'الأعمدة')->exists());
        $this->assertFalse($project->tasks()->where('zone', 'like', 'البدروم / شقة%')->exists());

        // the frame goes level by level: the 2nd floor's columns wait for the 1st floor's slab
        $slab1 = $project->tasks()->where('title', 'السقف')->where('zone', 'الدور الأول')->firstOrFail();
        $columns2 = $project->tasks()->where('title', 'الأعمدة')->where('zone', 'الدور الثاني')->firstOrFail();
        $this->assertTrue(\DB::table('project_task_dependencies')->where('task_id', $columns2->id)->where('depends_on_id', $slab1->id)->exists());

        // a second generate on a project that has stages is refused
        $this->postJson("/api/v2/business/projects/{$project->id}/generate", ['kind' => 'building', 'floors' => 2, 'apartments_per_floor' => 1])->assertStatus(422);
    }

    public function test_progress_is_weighted_and_a_main_stage_takes_its_progress_from_its_works(): void
    {
        $project = Project::create(['business_id' => $this->office->id, 'title' => 'P', 'status' => Project::STATUS_ACTIVE]);
        $phase = $project->tasks()->create(['title' => 'Phase', 'requires_photo' => false]);
        $big = $project->tasks()->create(['parent_id' => $phase->id, 'title' => 'Big', 'weight' => 90, 'requires_photo' => false]);
        $project->tasks()->create(['parent_id' => $phase->id, 'title' => 'Small', 'weight' => 10, 'requires_photo' => false]);

        Sanctum::actingAs($this->office);
        $this->patchJson("/api/v2/business/projects/{$project->id}/tasks/{$big->id}/progress", ['progress' => 50])->assertOk();

        $this->assertSame(45, $project->refresh()->progress, '50% of a work worth 90 of 100');
        $this->assertSame(45, $phase->refresh()->progress);

        // a main stage cannot be set by hand
        $this->patchJson("/api/v2/business/projects/{$project->id}/tasks/{$phase->id}/progress", ['progress' => 100])->assertStatus(422);
    }

    /* ------------------------------------------------------------------ quantities */

    public function test_quantities_drive_the_progress_and_every_change_is_logged_for_the_customer(): void
    {
        $project = $this->project(1);
        $work = $project->tasks()->where('title', 'Work 1')->firstOrFail();

        Sanctum::actingAs($this->office);
        $itemId = $this->postJson("/api/v2/business/projects/{$project->id}/tasks/{$work->id}/items", ['name' => 'عدد الأعمدة', 'unit' => 'عمود', 'planned' => 24])
            ->assertCreated()->json('data.items.0.id');

        $this->patchJson("/api/v2/business/projects/{$project->id}/tasks/{$work->id}/items/{$itemId}", ['done' => 18])->assertOk()->assertJsonPath('data.progress', 75);
        // the engineer changes the plan before the agreement: applied at once, and logged
        $this->patchJson("/api/v2/business/projects/{$project->id}/tasks/{$work->id}/items/{$itemId}", ['planned' => 30, 'reason' => 'design revised'])->assertOk()->assertJsonPath('data.progress', 60);

        // the customer reads the history
        Sanctum::actingAs($this->client);
        $view = $this->getJson("/api/v2/operations/booking/{$this->booking->id}/project")->assertOk();
        $log = collect($view->json('data.quantity_log'));
        $this->assertTrue($log->contains(fn ($l) => $l['field'] === 'planned' && $l['old'] === '24' && $l['new'] === '30' && $l['reason'] === 'design revised'));
        $this->assertTrue($log->contains(fn ($l) => $l['field'] === 'done' && $l['new'] === '18'));
        $row = collect($view->json('data.tasks'))->firstWhere('id', $work->id);
        $this->assertSame(30.0, (float) $row['items'][0]['planned']);
        $this->assertSame('Floor 1', $row['zone']);
    }

    public function test_after_the_agreement_a_change_of_planned_quantity_waits_for_the_customer(): void
    {
        $project = $this->project(1);
        $work = $project->tasks()->where('title', 'Work 1')->firstOrFail();
        Sanctum::actingAs($this->office);
        $itemId = $this->postJson("/api/v2/business/projects/{$project->id}/tasks/{$work->id}/items", ['name' => 'Concrete', 'unit' => 'm3', 'planned' => 100])->json('data.items.0.id');
        $this->agreeToPlan($project);

        // approve the start of the main stage so the engineer may report work
        $phase = $project->tasks()->whereNull('parent_id')->firstOrFail();
        Sanctum::actingAs($this->office);
        $this->postJson("/api/v2/business/projects/{$project->id}/tasks/{$phase->id}/request-start")->assertCreated();
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/operations/booking/{$this->booking->id}/project/tasks/{$phase->id}/start/approve")->assertOk();

        // what was DONE is reported freely…
        Sanctum::actingAs($this->office);
        $this->patchJson("/api/v2/business/projects/{$project->id}/tasks/{$work->id}/items/{$itemId}", ['done' => 40])->assertOk();

        // …but a different PLAN is a scope request: 202, nothing changed yet
        $res = $this->patchJson("/api/v2/business/projects/{$project->id}/tasks/{$work->id}/items/{$itemId}", ['planned' => 140, 'reason' => 'extra column'])->assertStatus(202);
        $requestId = $res->json('data.pending_request.id');
        $this->assertSame(100.0, (float) \App\Models\ProjectTaskItem::find($itemId)->planned);

        // adding an item and removing one are requests too
        $this->postJson("/api/v2/business/projects/{$project->id}/tasks/{$work->id}/items", ['name' => 'Bricks', 'unit' => 'pcs', 'planned' => 5000])->assertStatus(202);
        $this->deleteJson("/api/v2/business/projects/{$project->id}/tasks/{$work->id}/items/{$itemId}")->assertStatus(202);
        $this->assertSame(1, \App\Models\ProjectTaskItem::where('task_id', $work->id)->count());

        // the customer approves the first: it is applied, logged against the request, and a new agreement version is written
        Sanctum::actingAs($this->client);
        $before = ProjectAgreement::where('project_id', $project->id)->count();
        $this->postJson("/api/v2/operations/booking/{$this->booking->id}/project/change-requests/{$requestId}/approve")->assertOk();
        $this->assertSame(140.0, (float) \App\Models\ProjectTaskItem::find($itemId)->planned);
        $this->assertSame($before + 1, ProjectAgreement::where('project_id', $project->id)->count());
        $this->assertSame(0, ProjectAgreement::where('project_id', $project->id)->where('version', 2)->where('status', 'agreed')->count() - 1);

        // a rejection needs a reason and changes nothing
        $other = ProjectChangeRequest::where('project_id', $project->id)->where('status', 'pending')->orderBy('id')->firstOrFail();
        $this->postJson("/api/v2/operations/booking/{$this->booking->id}/project/change-requests/{$other->id}/reject")->assertStatus(422);
        $this->postJson("/api/v2/operations/booking/{$this->booking->id}/project/change-requests/{$other->id}/reject", ['note' => 'No extra bricks'])->assertOk();
        $this->assertSame(1, \App\Models\ProjectTaskItem::where('task_id', $work->id)->count());
    }

    /* ------------------------------------------------------------------ the agreement */

    public function test_the_agreement_records_both_sides_and_is_immutable(): void
    {
        $project = $this->project();

        // no customer / no stages → refused
        Sanctum::actingAs($this->office);
        $unlinked = Project::create(['business_id' => $this->office->id, 'title' => 'Internal']);
        $unlinked->tasks()->create(['title' => 'x']);
        $this->postJson("/api/v2/business/projects/{$unlinked->id}/agreement", ['due_on' => now()->addMonth()->toDateString(), 'grace_days' => 30])->assertStatus(422);

        $due = now()->addMonths(8)->toDateString();
        $this->postJson("/api/v2/business/projects/{$project->id}/agreement", ['due_on' => $due, 'grace_days' => 30])->assertCreated()
            ->assertJsonPath('data.agreement.status', 'proposed')->assertJsonPath('data.agreed', false);

        // a stranger cannot agree
        Sanctum::actingAs($this->user('Stranger', User::TYPE_CLIENT));
        $this->postJson("/api/v2/operations/booking/{$this->booking->id}/project/agreement/agree")->assertStatus(404);

        // the customer agrees: recorded with his consent and a hash; the project carries the dates
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/operations/booking/{$this->booking->id}/project/agreement/agree")->assertOk()
            ->assertJsonPath('data.agreed', true)->assertJsonPath('data.agreement.version', 1);
        $agreement = ProjectAgreement::where('project_id', $project->id)->firstOrFail();
        $this->assertSame($this->client->id, (int) $agreement->customer_id);
        $this->assertNotNull($agreement->customer_agreed_at);
        $this->assertSame(64, strlen($agreement->content_hash));
        $this->assertSame(Carbon::parse($due)->addDays(30)->toDateString(), $project->refresh()->grace_until->toDateString());

        // what was agreed cannot be edited in place
        $this->expectException(\LogicException::class);
        $agreement->update(['due_on' => now()->addYear()->toDateString()]);
    }

    public function test_more_time_is_a_request_the_customer_approves_and_each_approval_is_a_new_version(): void
    {
        $project = $this->project();
        $this->agreeToPlan($project, '+90 days', 30);
        $graceBefore = $project->refresh()->grace_until->copy();

        Sanctum::actingAs($this->office);
        $this->postJson("/api/v2/business/projects/{$project->id}/extension-requests", ['extra_days' => 20, 'reason' => 'the customer paid late'])->assertCreated();
        // one request at a time
        $this->postJson("/api/v2/business/projects/{$project->id}/extension-requests", ['extra_days' => 5, 'reason' => 'again'])->assertStatus(422);
        $request = ProjectChangeRequest::where('project_id', $project->id)->firstOrFail();
        $this->assertSame($graceBefore->toDateString(), $project->refresh()->grace_until->toDateString(), 'nothing moves before he answers');

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/operations/booking/{$this->booking->id}/project/change-requests/{$request->id}/approve")->assertOk();

        $this->assertSame($graceBefore->copy()->addDays(20)->toDateString(), $project->refresh()->grace_until->toDateString());
        $v2 = ProjectAgreement::where('project_id', $project->id)->where('version', 2)->firstOrFail();
        $this->assertSame('agreed', $v2->status);
        $this->assertSame($request->id, (int) $v2->change_request_id);
        $this->assertSame('superseded', ProjectAgreement::where('project_id', $project->id)->where('version', 1)->value('status'));
    }

    /* ------------------------------------------------------------------ starting a stage, and the fees */

    public function test_a_main_stage_starts_only_with_the_customers_recorded_approval_and_both_sides_pay(): void
    {
        $project = $this->project();
        $phase = $project->tasks()->whereNull('parent_id')->firstOrFail();
        $work = $project->tasks()->where('title', 'Work 1')->firstOrFail();
        $this->agreeToPlan($project);

        $this->fund($this->office, 100);
        $this->fund($this->client, 100);
        $this->price('project_stage_started', 15, 10);

        // nothing advances before the customer approved the start
        Sanctum::actingAs($this->office);
        $this->patchJson("/api/v2/business/projects/{$project->id}/tasks/{$work->id}/progress", ['progress' => 10])->assertStatus(422);

        $this->postJson("/api/v2/business/projects/{$project->id}/tasks/{$phase->id}/request-start")->assertCreated();
        $this->postJson("/api/v2/business/projects/{$project->id}/tasks/{$phase->id}/request-start")->assertStatus(422);   // already asked

        // a customer who cannot pay cannot approve — and silence approves nothing
        $this->fund($this->client, 3);
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/operations/booking/{$this->booking->id}/project/tasks/{$phase->id}/start/approve")->assertStatus(422);
        $this->assertSame('requested', $phase->refresh()->start_status);

        $this->fund($this->client, 100);
        $this->postJson("/api/v2/operations/booking/{$this->booking->id}/project/tasks/{$phase->id}/start/approve")->assertOk();
        $this->assertSame('approved', $phase->refresh()->start_status);
        $this->assertEquals(85.0, (float) Wallet::where('user_id', $this->office->id)->value('balance'));
        $this->assertEquals(90.0, (float) Wallet::where('user_id', $this->client->id)->value('balance'));

        // charged once
        $this->assertSame(2, \App\Models\PlatformEventCharge::where('event_key', 'project_stage_started')->where('reference_id', $phase->id)->count());

        // now the work may move
        Sanctum::actingAs($this->office);
        $this->patchJson("/api/v2/business/projects/{$project->id}/tasks/{$work->id}/progress", ['progress' => 10])->assertOk();
    }

    public function test_the_customer_may_decline_a_start_with_a_reason_and_the_business_asks_again(): void
    {
        $project = $this->project();
        $phase = $project->tasks()->whereNull('parent_id')->firstOrFail();
        $this->agreeToPlan($project);

        Sanctum::actingAs($this->office);
        $this->postJson("/api/v2/business/projects/{$project->id}/tasks/{$phase->id}/request-start")->assertCreated();

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/operations/booking/{$this->booking->id}/project/tasks/{$phase->id}/start/decline")->assertStatus(422);
        $this->postJson("/api/v2/operations/booking/{$this->booking->id}/project/tasks/{$phase->id}/start/decline", ['note' => 'Not before the 1st'])->assertOk();
        $this->assertSame('declined', $phase->refresh()->start_status);

        Sanctum::actingAs($this->office);
        $this->postJson("/api/v2/business/projects/{$project->id}/tasks/{$phase->id}/request-start")->assertCreated();
    }

    public function test_the_plan_agreement_fee_is_taken_from_both_sides_and_refused_if_either_cannot_pay(): void
    {
        $project = $this->project();
        $this->price('project_plan_agreed', 20, 12);
        $this->fund($this->office, 100);
        $this->fund($this->client, 5);

        Sanctum::actingAs($this->office);
        $this->postJson("/api/v2/business/projects/{$project->id}/agreement", ['due_on' => now()->addMonths(3)->toDateString(), 'grace_days' => 30])->assertCreated();

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/operations/booking/{$this->booking->id}/project/agreement/agree")->assertStatus(422);
        $this->assertFalse(ProjectAgreement::where('project_id', $project->id)->where('status', 'agreed')->exists());

        $this->fund($this->client, 50);
        $this->postJson("/api/v2/operations/booking/{$this->booking->id}/project/agreement/agree")->assertOk();
        $this->assertEquals(80.0, (float) Wallet::where('user_id', $this->office->id)->value('balance'));
        $this->assertEquals(38.0, (float) Wallet::where('user_id', $this->client->id)->value('balance'));
    }

    /* ------------------------------------------------------------------ the rating */

    private function finish(Project $project): void
    {
        $parents = $project->tasks()->whereNotNull('parent_id')->pluck('parent_id')->all();
        foreach ($project->tasks()->whereNotIn('id', $parents)->get() as $work) {
            $work->forceFill(['status' => 'done', 'progress' => 100])->save();
        }
        app(\App\Services\Projects\ProjectService::class)->recomputeProgress($project->fresh());
    }

    public function test_a_project_finished_by_the_agreed_date_and_grace_counts_as_a_success_and_no_deduction_inside_the_grace(): void
    {
        $project = $this->project();
        $this->agreeToPlan($project, '+10 days', 30);

        // finished AFTER the delivery date but INSIDE the grace → still on time
        $this->travelTo(now()->addDays(20));
        $this->finish($project);

        $this->assertNotNull($project->refresh()->completed_at);
        $this->assertTrue(RatingOutcomeEvent::where('operation_type', 'project')->where('operation_id', $project->id)->where('outcome', 'success')->exists());
        $rating = UserOperationRating::where('user_id', $this->office->id)->where('role', 'business')->firstOrFail();
        $this->assertSame(0, (int) $rating->late_count);
    }

    public function test_a_project_finished_after_the_grace_is_late_unless_the_customers_waiting_covers_it(): void
    {
        $project = $this->project();
        $this->agreeToPlan($project, '+10 days', 5);
        $phase = $project->tasks()->whereNull('parent_id')->firstOrFail();

        // the customer sat on the start request for 12 days
        Sanctum::actingAs($this->office);
        $this->postJson("/api/v2/business/projects/{$project->id}/tasks/{$phase->id}/request-start")->assertCreated();
        $this->travelTo(now()->addDays(12));
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/operations/booking/{$this->booking->id}/project/tasks/{$phase->id}/start/approve")->assertOk();
        $this->assertGreaterThanOrEqual(12 * 86400, (int) $project->refresh()->customer_wait_seconds);

        // finished on day 20: past delivery+grace (day 15) but inside it once the 12 days he made the business wait are added
        $this->travelTo(now()->addDays(8));
        $this->finish($project);
        $this->assertTrue(RatingOutcomeEvent::where('operation_type', 'project')->where('operation_id', $project->id)->where('outcome', 'success')->exists());

        // the same job, with the waiting counted against the business (config), is late
        $secondBooking = Booking::query()->create([
            'user_id' => $this->client->id, 'business_id' => $this->office->id, 'status' => Booking::STATUS_PENDING,
            'date' => now()->addDay()->toDateString(), 'time' => '13:00:00',
        ]);
        $second = $this->project(2, $secondBooking);
        $this->agreeToPlan($second, '+1 days', 2);
        config(['bim.projects.count_customer_wait' => true]);
        $this->travelTo(now()->addDays(30));
        $this->finish($second);
        $this->assertTrue(RatingOutcomeEvent::where('operation_type', 'project')->where('operation_id', $second->id)->where('outcome', 'late')->exists());
        $this->assertSame(1, (int) UserOperationRating::where('user_id', $this->office->id)->where('role', 'business')->value('late_count'));
    }

    public function test_a_project_without_an_agreed_customer_is_not_rated(): void
    {
        $project = Project::create(['business_id' => $this->office->id, 'title' => 'Internal', 'status' => Project::STATUS_ACTIVE]);
        $project->tasks()->create(['title' => 'Only', 'requires_photo' => false]);
        $this->finish($project);

        $this->assertNotNull($project->refresh()->completed_at);
        $this->assertSame(0, RatingOutcomeEvent::where('operation_type', 'project')->where('operation_id', $project->id)->count());
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }
}
