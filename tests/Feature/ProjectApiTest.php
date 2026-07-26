<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The business-facing project timeline API: CRUD, task wiring, and ownership.
 */
class ProjectApiTest extends TestCase
{
    use DatabaseTransactions;

    private function makeBusiness(): User
    {
        $u = new User();
        $u->name = 'Proj API ' . Str::random(4);
        $u->email = 'projapi-' . uniqid() . '@example.test';
        $u->phone = '0108' . random_int(1000000, 9999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_BUSINESS;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    public function test_a_business_creates_a_project_with_tasks_and_reads_its_timeline(): void
    {
        $shop = $this->makeBusiness();
        Sanctum::actingAs($shop);

        $projectId = $this->postJson('/api/v2/business/projects', [
            'title' => 'Furniture shipment #A12',
            'reference' => 'SHIP-A12',
            'starts_on' => '2026-08-01',
            'due_on' => '2026-08-20',
        ])->assertCreated()->json('data.project.id');

        $cut = $this->postJson("/api/v2/business/projects/{$projectId}/tasks", [
            'title' => 'Cutting',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-03',
            'requires_photo' => false,
        ])->assertCreated()->json('data.task.id');

        $this->postJson("/api/v2/business/projects/{$projectId}/tasks", [
            'title' => 'Assembly',
            'starts_on' => '2026-08-04',
            'ends_on' => '2026-08-08',
            'requires_photo' => false,
            'depends_on' => [$cut],
        ])->assertCreated()->assertJsonPath('data.task.depends_on', [$cut]);

        $res = $this->getJson("/api/v2/business/projects/{$projectId}")->assertOk();
        $res->assertJsonPath('data.project.tasks_count', 2);
        $this->assertSame(8, $res->json('data.timeline.project_duration_days'));
    }

    public function test_progress_rolls_up_to_the_project(): void
    {
        $shop = $this->makeBusiness();
        Sanctum::actingAs($shop);

        $projectId = $this->postJson('/api/v2/business/projects', ['title' => 'Rollup'])
            ->json('data.project.id');

        $t1 = $this->postJson("/api/v2/business/projects/{$projectId}/tasks", [
            'title' => 'One', 'requires_photo' => false,
        ])->json('data.task.id');
        $this->postJson("/api/v2/business/projects/{$projectId}/tasks", [
            'title' => 'Two', 'requires_photo' => false,
        ])->json('data.task.id');

        $this->patchJson("/api/v2/business/projects/{$projectId}/tasks/{$t1}/progress", [
            'progress' => 50,
        ])->assertOk();

        // One task at 50, one at 0 → project average 25.
        $this->getJson("/api/v2/business/projects/{$projectId}")
            ->assertJsonPath('data.project.progress', 25);
    }

    public function test_a_business_cannot_touch_another_businesss_project(): void
    {
        $owner = $this->makeBusiness();
        $stranger = $this->makeBusiness();

        $project = Project::create(['business_id' => $owner->id, 'title' => 'Private']);

        Sanctum::actingAs($stranger);
        $this->getJson("/api/v2/business/projects/{$project->id}")->assertNotFound();
        $this->deleteJson("/api/v2/business/projects/{$project->id}")->assertNotFound();
    }

    public function test_a_non_business_is_refused(): void
    {
        $customer = new User();
        $customer->name = 'Cust ' . Str::random(4);
        $customer->email = 'cust-' . uniqid() . '@example.test';
        $customer->phone = '0109' . random_int(1000000, 9999999);
        $customer->password = 'secret-password';
        $customer->type = User::TYPE_CLIENT;
        $customer->api_token = Str::random(80);
        $customer->save();

        Sanctum::actingAs($customer);
        $this->getJson('/api/v2/business/projects')->assertForbidden();
    }
}
