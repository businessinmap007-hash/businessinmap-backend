<?php

namespace Tests\Feature;

use App\Models\PlanExerciseRound;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The weekly adherence summary: confirmed rounds vs. the weekly target, active
 * days, and check-ins over a 7-day window — for both the trainer and the client.
 */
class TrainingWeeklySummaryTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type, string $tag): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    public function test_it_computes_adherence_over_the_window(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');

        $plan = TrainingPlan::create([
            'trainer_id' => $trainer->id,
            'client_id' => $client->id,
            'title' => 'Plan',
            'status' => TrainingPlan::STATUS_ACTIVE,
        ]);
        // Weekly target = 3 + 2 = 5 rounds.
        $squat = $plan->exercises()->create(['name' => 'Squat', 'sets' => 3]);
        $plan->exercises()->create(['name' => 'Plank', 'sets' => 2]);

        $from = '2026-08-01'; // window 2026-08-01 .. 2026-08-07
        // 2 rounds on day 1, 1 round on day 3 → 3 completed across 2 active days.
        PlanExerciseRound::insert([
            ['plan_exercise_id' => $squat->id, 'training_plan_id' => $plan->id, 'client_id' => $client->id, 'for_date' => '2026-08-01', 'round_number' => 1, 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['plan_exercise_id' => $squat->id, 'training_plan_id' => $plan->id, 'client_id' => $client->id, 'for_date' => '2026-08-01', 'round_number' => 2, 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['plan_exercise_id' => $squat->id, 'training_plan_id' => $plan->id, 'client_id' => $client->id, 'for_date' => '2026-08-03', 'round_number' => 1, 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            // Out of window — must be excluded.
            ['plan_exercise_id' => $squat->id, 'training_plan_id' => $plan->id, 'client_id' => $client->id, 'for_date' => '2026-08-20', 'round_number' => 1, 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);
        $plan->progressLogs()->create(['client_id' => $client->id, 'logged_on' => '2026-08-02', 'weight' => 82.0]);

        Sanctum::actingAs($trainer);
        $s = $this->getJson("/api/v2/business/training-plans/{$plan->id}/weekly-summary?from={$from}")
            ->assertOk()->json('data.summary');

        $this->assertSame('2026-08-01', $s['from']);
        $this->assertSame('2026-08-07', $s['to']);
        $this->assertSame(5, $s['weekly_target_rounds']);
        $this->assertSame(3, $s['completed_rounds']);       // window only
        $this->assertSame(60, $s['adherence_percent']);     // 3/5
        $this->assertSame(2, $s['active_days']);
        $this->assertCount(7, $s['days']);
        $this->assertSame(1, $s['progress']['check_ins']);
        $this->assertEquals(82.0, $s['progress']['latest_weight']);
    }

    public function test_the_client_sees_their_own_summary_and_a_stranger_does_not(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');
        $plan = TrainingPlan::create([
            'trainer_id' => $trainer->id, 'client_id' => $client->id,
            'title' => 'Plan', 'status' => TrainingPlan::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($client);
        $this->getJson("/api/v2/training-plans/{$plan->id}/weekly-summary")
            ->assertOk()->assertJsonPath('data.summary.completed_rounds', 0);

        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'Stranger'));
        $this->getJson("/api/v2/training-plans/{$plan->id}/weekly-summary")->assertNotFound();
    }
}
