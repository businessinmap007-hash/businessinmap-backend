<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The project timeline: earliest start/finish, the critical path, and the rule
 * that keeps the dependency graph acyclic.
 */
class ProjectTimelineTest extends TestCase
{
    use DatabaseTransactions;

    private ProjectService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProjectService::class);
    }

    private function makeBusiness(): User
    {
        $u = new User();
        $u->name = 'Proj Shop ' . Str::random(4);
        $u->email = 'proj-' . uniqid() . '@example.test';
        $u->phone = '0107' . random_int(1000000, 9999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_BUSINESS;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    private function task(Project $p, string $title, ?string $start, ?string $end): ProjectTask
    {
        return $p->tasks()->create([
            'title' => $title,
            'starts_on' => $start,
            'ends_on' => $end,
            'requires_photo' => false,
        ]);
    }

    public function test_timeline_computes_offsets_and_the_critical_path(): void
    {
        $business = $this->makeBusiness();
        $project = Project::create([
            'business_id' => $business->id,
            'title' => 'Villa build',
            'starts_on' => '2026-08-01',
        ]);

        // A(3d) -> B(2d), A -> C(5d), {B,C} -> D(1d).
        $a = $this->task($project, 'Foundation', '2026-08-01', '2026-08-03'); // 3
        $b = $this->task($project, 'Framing', '2026-08-04', '2026-08-05');    // 2
        $c = $this->task($project, 'Utilities', '2026-08-04', '2026-08-08');  // 5
        $d = $this->task($project, 'Handover', '2026-08-09', '2026-08-09');   // 1

        $this->service->setDependencies($b, [$a->id]);
        $this->service->setDependencies($c, [$a->id]);
        $this->service->setDependencies($d, [$b->id, $c->id]);

        $t = $this->service->timeline($project->fresh());

        $this->assertFalse($t['has_cycle']);
        $this->assertSame(9, $t['project_duration_days']); // 3 + 5 + 1 on the long chain

        $rows = $t['tasks'];
        $this->assertSame(0, $rows[$a->id]['earliest_start_offset']);
        $this->assertSame(3, $rows[$a->id]['earliest_finish_offset']);
        $this->assertSame(3, $rows[$c->id]['earliest_start_offset']);
        $this->assertSame(8, $rows[$c->id]['earliest_finish_offset']);
        $this->assertSame(8, $rows[$d->id]['earliest_start_offset']);

        // A -> C -> D is critical; B has slack and is not.
        $this->assertTrue($rows[$a->id]['is_critical']);
        $this->assertTrue($rows[$c->id]['is_critical']);
        $this->assertTrue($rows[$d->id]['is_critical']);
        $this->assertFalse($rows[$b->id]['is_critical']);
        $this->assertSame(3, $rows[$b->id]['slack_days']);

        // Real planned dates flow from the project start.
        $this->assertSame('2026-08-04', $rows[$c->id]['planned_start']);
    }

    public function test_a_cycle_is_refused(): void
    {
        $business = $this->makeBusiness();
        $project = Project::create(['business_id' => $business->id, 'title' => 'Loop']);

        $a = $this->task($project, 'A', null, null);
        $b = $this->task($project, 'B', null, null);

        $this->service->setDependencies($b, [$a->id]); // B after A — fine.

        $this->expectException(ValidationException::class);
        $this->service->setDependencies($a, [$b->id]); // A after B — closes a cycle.
    }

    public function test_a_dependency_must_be_in_the_same_project(): void
    {
        $business = $this->makeBusiness();
        $p1 = Project::create(['business_id' => $business->id, 'title' => 'P1']);
        $p2 = Project::create(['business_id' => $business->id, 'title' => 'P2']);

        $t1 = $this->task($p1, 'T1', null, null);
        $foreign = $this->task($p2, 'Foreign', null, null);

        $this->expectException(ValidationException::class);
        $this->service->setDependencies($t1, [$foreign->id]);
    }
}
