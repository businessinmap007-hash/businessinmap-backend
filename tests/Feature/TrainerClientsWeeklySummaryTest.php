<?php

namespace Tests\Feature;

use App\Models\PlanExerciseRound;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Support\BusinessCapability;
use App\Models\BusinessStaff;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The trainer's weekly adherence overview across ALL their clients at once.
 */
class TrainerClientsWeeklySummaryTest extends TestCase
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

    private function planWith(User $trainer, User $client, int $sets): TrainingPlan
    {
        $plan = TrainingPlan::create([
            'trainer_id' => $trainer->id, 'client_id' => $client->id,
            'title' => 'Plan ' . Str::random(3), 'status' => TrainingPlan::STATUS_ACTIVE,
        ]);
        $plan->exercises()->create(['name' => 'Squat', 'sets' => $sets]);

        return $plan;
    }

    private function tick(TrainingPlan $plan, string $date, int $rounds): void
    {
        $exId = $plan->exercises()->value('id');
        $rows = [];
        for ($i = 1; $i <= $rounds; $i++) {
            $rows[] = ['plan_exercise_id' => $exId, 'training_plan_id' => $plan->id, 'client_id' => $plan->client_id, 'for_date' => $date, 'round_number' => $i, 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()];
        }
        PlanExerciseRound::insert($rows);
    }

    public function test_it_summarizes_every_client_in_one_call(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $a = $this->user(User::TYPE_CLIENT, 'Alice');
        $b = $this->user(User::TYPE_CLIENT, 'Bob');

        $planA = $this->planWith($trainer, $a, 4); // target 4
        $planB = $this->planWith($trainer, $b, 2); // target 2

        $from = '2026-08-01';
        $this->tick($planA, '2026-08-01', 2);      // 2/4 = 50%
        $this->tick($planB, '2026-08-02', 2);      // 2/2 = 100%
        $this->tick($planA, '2026-08-20', 4);      // out of window, ignored

        Sanctum::actingAs($trainer);
        $s = $this->getJson("/api/v2/business/training-plans/weekly-summary?from={$from}")
            ->assertOk()->json('data.summary');

        $this->assertSame(2, $s['plans']);
        $this->assertSame(75, $s['average_adherence']); // (50 + 100) / 2

        $byPlan = collect($s['clients'])->keyBy('plan_id');
        $this->assertSame(50, $byPlan[$planA->id]['adherence_percent']);
        $this->assertSame(2, $byPlan[$planA->id]['completed_rounds']);
        $this->assertSame(100, $byPlan[$planB->id]['adherence_percent']);
    }

    public function test_it_only_covers_the_callers_own_clients(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $otherGym = $this->user(User::TYPE_BUSINESS, 'OtherGym');
        $this->planWith($trainer, $this->user(User::TYPE_CLIENT, 'Mine'), 3);
        $this->planWith($otherGym, $this->user(User::TYPE_CLIENT, 'Theirs'), 3);

        Sanctum::actingAs($trainer);
        $s = $this->getJson('/api/v2/business/training-plans/weekly-summary')->assertOk()->json('data.summary');
        $this->assertSame(1, $s['plans']);
    }

    public function test_a_training_delegate_sees_the_gyms_overview(): void
    {
        $gym = $this->user(User::TYPE_BUSINESS, 'Gym');
        $coach = $this->user(User::TYPE_CLIENT, 'Coach');
        $this->planWith($gym, $this->user(User::TYPE_CLIENT, 'Client'), 3);
        BusinessStaff::create([
            'business_id' => $gym->id, 'user_id' => $coach->id,
            'capabilities' => [BusinessCapability::TRAINING], 'is_active' => true,
        ]);

        Sanctum::actingAs($coach);
        $this->getJson('/api/v2/business/training-plans/weekly-summary')
            ->assertOk()->assertJsonPath('data.summary.plans', 1);
    }
}
