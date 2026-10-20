<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\BusinessStaff;
use App\Models\Image;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Project tracking, second round: a business starts from a ready stage plan of its trade, hands each stage to one of its
 * people, and the customer approves (or sends back) the stages that ask for it.
 */
class ProjectTemplatesAndApprovalsTest extends TestCase
{
    use DatabaseTransactions;

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

    /** An engineering office (root 19 / child 123) — it has the service and its own template. */
    private function office(): User
    {
        return $this->user('Office', User::TYPE_BUSINESS, 19, 123);
    }

    public function test_the_templates_offered_are_the_trades_own_plus_the_general_one(): void
    {
        Sanctum::actingAs($this->office());

        $codes = collect($this->getJson('/api/v2/business/projects/templates')->assertOk()->json('data.templates'))->pluck('code')->all();

        $this->assertContains('engineering_office', $codes);
        $this->assertContains('general', $codes);
        $this->assertNotContains('painting', $codes, 'another trade\'s plan is not offered');
    }

    public function test_a_project_starts_from_a_template_as_a_chain_of_dated_stages(): void
    {
        Sanctum::actingAs($this->office());
        $template = collect($this->getJson('/api/v2/business/projects/templates')->json('data.templates'))->firstWhere('code', 'engineering_office');

        $res = $this->postJson('/api/v2/business/projects', ['title' => 'Villa design', 'template_id' => $template['id'], 'starts_on' => '2027-01-01'])
            ->assertCreated()->assertJsonPath('data.project.tasks_count', count($template['stages']));
        $project = Project::findOrFail($res->json('data.project.id'));

        $tasks = $project->tasks()->orderBy('sort_order')->get();
        $this->assertSame('2027-01-01', $tasks[0]->starts_on->toDateString());
        $this->assertSame($tasks[0]->ends_on->copy()->addDay()->toDateString(), $tasks[1]->starts_on->toDateString(), 'each stage starts the day after the previous');
        $this->assertSame([(int) $tasks[0]->id], $tasks[1]->dependencies()->pluck('project_tasks.id')->map(fn ($i) => (int) $i)->all(), 'and waits for it');
        $this->assertNotNull($project->due_on);

        // a plan of another trade is refused
        $painting = \App\Models\ProjectTemplate::where('code', 'painting')->firstOrFail();
        $this->postJson('/api/v2/business/projects', ['title' => 'x', 'template_id' => $painting->id])->assertStatus(422)->assertJsonValidationErrors('template_id');
    }

    public function test_a_stage_is_handed_to_a_provider_who_sees_it_in_his_stages(): void
    {
        $office = $this->office();
        $engineer = $this->user('Engineer', User::TYPE_CLIENT);
        BusinessStaff::create([
            'business_id' => $office->id, 'user_id' => $engineer->id, 'title' => 'مهندس تنفيذ', 'status' => BusinessStaff::STATUS_ACCEPTED,
            'is_active' => true, 'is_provider' => true, 'capabilities' => ['projects'],
        ]);

        Sanctum::actingAs($office);
        $project = Project::create(['business_id' => $office->id, 'title' => 'Tower', 'status' => Project::STATUS_ACTIVE]);
        $mine = $project->tasks()->create(['title' => 'Drawings', 'starts_on' => '2027-01-01', 'ends_on' => '2027-01-05']);
        $other = $project->tasks()->create(['title' => 'Permits']);

        $this->putJson("/api/v2/business/projects/{$project->id}/tasks/{$mine->id}/assignee", ['assignee_id' => $engineer->id])
            ->assertOk()->assertJsonPath('data.task.assignee.id', $engineer->id)->assertJsonPath('data.task.assignee.title', 'مهندس تنفيذ');
        $this->assertSame(1, AppNotification::where('user_id', $engineer->id)->where('title_ar', 'تم تعيينك على عمل جديد')->count());

        // someone who is not a provider of this business cannot be given a stage
        $this->putJson("/api/v2/business/projects/{$project->id}/tasks/{$other->id}/assignee", ['assignee_id' => $this->user('Stranger', User::TYPE_CLIENT)->id])->assertStatus(422);

        // he sees only what is assigned to him, and cannot hand work around
        Sanctum::actingAs($engineer);
        $stages = collect($this->withHeader('X-Business-Id', (string) $office->id)->getJson('/api/v2/business/projects/my-stages')->assertOk()->json('data.stages'));
        $this->assertSame(['Drawings'], $stages->pluck('title')->all());
        $this->assertSame('Tower', $stages->first()['project_title']);
        $this->withHeader('X-Business-Id', (string) $office->id)
            ->putJson("/api/v2/business/projects/{$project->id}/tasks/{$other->id}/assignee", ['assignee_id' => $engineer->id])->assertForbidden();

        // the timeline carries who is responsible
        Sanctum::actingAs($office);
        $row = collect($this->getJson("/api/v2/business/projects/{$project->id}")->json('data.timeline.tasks'))->firstWhere('id', $mine->id);
        $this->assertSame($engineer->id, $row['assignee']['id']);
    }

    public function test_the_customer_approves_or_sends_back_a_completed_stage_that_asks_for_it(): void
    {
        $booking = Booking::withTrashed()->whereNotNull('user_id')->whereNotNull('business_id')->whereColumn('user_id', '!=', 'business_id')->first();
        if ($booking && $booking->trashed()) {
            $booking->restore();
        }
        if (! $booking || ! $booking->user || ! $booking->business) {
            $this->markTestSkipped('Needs a booking with a client and a business.');
        }
        $client = $booking->user;
        $business = $booking->business;

        Sanctum::actingAs($business);
        $projectId = $this->postJson('/api/v2/business/projects', ['title' => 'Kitchen', 'operation_type' => 'booking', 'operation_id' => $booking->id])->assertCreated()->json('data.project.id');
        $task = Project::findOrFail($projectId)->tasks()->create(['title' => 'Cabinets', 'requires_approval' => true, 'requires_photo' => true]);
        $task->photos()->create(['image' => 'files/uploads/test-evidence.jpg', 'source' => Image::SOURCE_CAMERA]);

        // finishing the stage makes it wait for the customer, who is told
        $this->patchJson("/api/v2/business/projects/{$projectId}/tasks/{$task->id}/progress", ['status' => 'done'])->assertOk()
            ->assertJsonPath('data.task.approval_status', 'pending');
        $this->assertSame(1, AppNotification::where('user_id', $client->id)->where('title_ar', 'مرحلة بانتظار اعتمادك')->count());

        // a stranger (not the contracted customer) cannot decide; the business itself cannot approve its own work
        $this->postJson("/api/v2/operations/booking/{$booking->id}/project/tasks/{$task->id}/approve")->assertStatus(422);

        Sanctum::actingAs($client);
        $this->postJson("/api/v2/operations/booking/{$booking->id}/project/tasks/{$task->id}/reject")->assertStatus(422);   // a note is needed
        $res = $this->postJson("/api/v2/operations/booking/{$booking->id}/project/tasks/{$task->id}/reject", ['note' => 'The doors are crooked'])->assertOk();
        $row = collect($res->json('data.tasks'))->firstWhere('id', $task->id);
        $this->assertSame('rejected', $row['approval_status']);
        $this->assertSame('in_progress', $row['status'], 'the stage reopens');
        $this->assertLessThanOrEqual(90, $row['progress']);
        $this->assertSame(1, AppNotification::where('user_id', $business->id)->where('title_ar', 'أعاد العميل مرحلة للتعديل')->count());

        // finished again → waits again → approved
        Sanctum::actingAs($business);
        $this->patchJson("/api/v2/business/projects/{$projectId}/tasks/{$task->id}/progress", ['status' => 'done'])->assertOk()->assertJsonPath('data.task.approval_status', 'pending');
        Sanctum::actingAs($client);
        $this->postJson("/api/v2/operations/booking/{$booking->id}/project/tasks/{$task->id}/approve")->assertOk();
        $this->assertSame('approved', $task->refresh()->approval_status);

        // nothing left to decide
        $this->postJson("/api/v2/operations/booking/{$booking->id}/project/tasks/{$task->id}/approve")->assertStatus(422);
    }

    public function test_a_stage_that_does_not_ask_for_approval_just_completes(): void
    {
        $business = $this->office();
        Sanctum::actingAs($business);
        $project = Project::create(['business_id' => $business->id, 'title' => 'P', 'status' => Project::STATUS_ACTIVE]);
        $task = $project->tasks()->create(['title' => 'Survey', 'requires_photo' => false]);

        $this->patchJson("/api/v2/business/projects/{$project->id}/tasks/{$task->id}/progress", ['status' => 'done'])->assertOk()
            ->assertJsonPath('data.task.approval_status', null);
    }

    public function test_the_service_is_offered_to_the_trades_that_work_in_phases(): void
    {
        $service = \App\Models\PlatformService::where('key', 'projects')->firstOrFail();
        $has = fn (int $root, int $child) => \Illuminate\Support\Facades\DB::table('category_platform_services')
            ->where('category_id', $root)->where('child_id', $child)->where('platform_service_id', $service->id)->where('is_active', 1)->exists()
            && \Illuminate\Support\Facades\DB::table('category_service_configs')
                ->where('category_id', $root)->where('child_id', $child)->where('platform_service_id', $service->id)->where('is_active', 1)->exists();

        $this->assertTrue($has(10, 543), 'a car workshop: bodywork, mechanics and electrical in order');
        $this->assertTrue($has(19, 123), 'an engineering office');
        $this->assertTrue($has(22, 72), 'a contracting company');
        $this->assertTrue($has(23, 116), 'a furniture factory');
        $this->assertFalse($has(24, 536), 'a hotel does not run phased jobs');
    }
}
