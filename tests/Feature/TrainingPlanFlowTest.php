<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\BusinessStaff;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Training & nutrition plans: a trainer builds a client's workout + meals, the
 * client reads it and logs progress, and the plan stays private to the two
 * parties. Also delegable to a `training` staff member.
 */
class TrainingPlanFlowTest extends TestCase
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

    private function createPlan(User $trainer, User $client): int
    {
        Sanctum::actingAs($trainer);

        return $this->postJson('/api/v2/business/training-plans', [
            'client_id' => $client->id,
            'title' => 'Fat loss – 8 weeks',
            'goal' => 'weight loss',
            'exercises' => [
                ['name' => 'Squat', 'day_of_week' => 6, 'sets' => 4, 'reps' => '10-12', 'rest_seconds' => 90],
                ['name' => 'Bench press', 'day_of_week' => 1, 'sets' => 4, 'reps' => '8'],
            ],
            'meals' => [
                ['meal_type' => 'breakfast', 'name' => 'Oats + eggs', 'calories' => 450],
                ['meal_type' => 'lunch', 'name' => 'Chicken + rice', 'calories' => 700],
            ],
        ])->assertCreated()->json('data.plan.id');
    }

    public function test_trainer_builds_a_plan_and_the_client_reads_and_logs_progress(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');

        $id = $this->createPlan($trainer, $client);

        // The client was notified.
        $this->assertTrue(
            AppNotification::query()->where('user_id', $client->id)
                ->where('notifiable_type', TrainingPlan::class)->where('notifiable_id', $id)->exists()
        );

        // The client reads the plan with its exercises + meals.
        Sanctum::actingAs($client);
        $res = $this->getJson("/api/v2/training-plans/{$id}")->assertOk();
        $res->assertJsonPath('data.plan.title', 'Fat loss – 8 weeks');
        $this->assertCount(2, $res->json('data.plan.exercises'));
        $this->assertCount(2, $res->json('data.plan.meals'));

        // The client logs a check-in.
        $this->postJson("/api/v2/training-plans/{$id}/progress", ['weight' => 84.5, 'notes' => 'Feeling good'])
            ->assertCreated()->assertJsonPath('data.progress.weight', 84.5);

        // The trainer sees the progress on their side.
        Sanctum::actingAs($trainer);
        $this->getJson("/api/v2/business/training-plans/{$id}")
            ->assertOk()->assertJsonPath('data.plan.progress.0.weight', 84.5);
    }

    public function test_the_trainee_confirms_exercise_rounds_one_by_one(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');
        $id = $this->createPlan($trainer, $client);

        // Grab the first exercise (Squat, 4 sets).
        Sanctum::actingAs($client);
        $exId = $this->getJson("/api/v2/training-plans/{$id}")->json('data.plan.exercises.0.id');

        // Confirm round after round — the server assigns the round number.
        $this->postJson("/api/v2/training-plans/{$id}/exercises/{$exId}/complete-round")
            ->assertCreated()
            ->assertJsonPath('data.round_number', 1)
            ->assertJsonPath('data.completed_rounds', 1)
            ->assertJsonPath('data.total_sets', 4);
        $this->postJson("/api/v2/training-plans/{$id}/exercises/{$exId}/complete-round")
            ->assertCreated()->assertJsonPath('data.round_number', 2);
        $this->postJson("/api/v2/training-plans/{$id}/exercises/{$exId}/complete-round")->assertCreated();
        $this->postJson("/api/v2/training-plans/{$id}/exercises/{$exId}/complete-round")
            ->assertCreated()->assertJsonPath('data.completed_rounds', 4);

        // The 5th exceeds the prescribed 4 sets → refused.
        $this->postJson("/api/v2/training-plans/{$id}/exercises/{$exId}/complete-round")
            ->assertStatus(422)->assertJsonValidationErrors('round');

        // Today's tally shows on the plan view for both sides.
        $this->getJson("/api/v2/training-plans/{$id}")
            ->assertJsonPath('data.plan.exercises.0.completed_rounds_today', 4);
        Sanctum::actingAs($trainer);
        $this->getJson("/api/v2/business/training-plans/{$id}")
            ->assertJsonPath('data.plan.exercises.0.completed_rounds_today', 4);
    }

    public function test_a_non_party_cannot_confirm_rounds(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');
        $stranger = $this->user(User::TYPE_CLIENT, 'Stranger');
        $id = $this->createPlan($trainer, $client);
        Sanctum::actingAs($client);
        $exId = $this->getJson("/api/v2/training-plans/{$id}")->json('data.plan.exercises.0.id');

        Sanctum::actingAs($stranger);
        $this->postJson("/api/v2/training-plans/{$id}/exercises/{$exId}/complete-round")->assertNotFound();
    }

    public function test_a_plan_is_private_to_its_two_parties(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');
        $stranger = $this->user(User::TYPE_CLIENT, 'Stranger');

        $id = $this->createPlan($trainer, $client);

        Sanctum::actingAs($stranger);
        $this->getJson("/api/v2/training-plans/{$id}")->assertNotFound();
        $this->postJson("/api/v2/training-plans/{$id}/progress", ['weight' => 70])->assertNotFound();
    }

    public function test_a_client_cannot_create_a_plan(): void
    {
        $notATrainer = $this->user(User::TYPE_CLIENT, 'Client');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');

        Sanctum::actingAs($notATrainer);
        $this->postJson('/api/v2/business/training-plans', [
            'client_id' => $client->id,
            'title' => 'X',
        ])->assertForbidden();
    }

    public function test_progress_is_refused_once_the_plan_is_not_active(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');
        $id = $this->createPlan($trainer, $client);

        // Trainer completes the plan.
        Sanctum::actingAs($trainer);
        $this->patchJson("/api/v2/business/training-plans/{$id}", ['status' => 'completed'])->assertOk();

        Sanctum::actingAs($client);
        $this->postJson("/api/v2/training-plans/{$id}/progress", ['weight' => 80])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_a_training_delegate_manages_plans_for_the_gym(): void
    {
        $gym = $this->user(User::TYPE_BUSINESS, 'Gym');
        $coach = $this->user(User::TYPE_CLIENT, 'Coach');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');

        BusinessStaff::create([
            'business_id' => $gym->id,
            'user_id' => $coach->id,
            'capabilities' => [BusinessCapability::TRAINING],
            'is_active' => true,
        ]);

        // The delegated coach creates a plan; it belongs to the GYM.
        Sanctum::actingAs($coach);
        $id = $this->postJson('/api/v2/business/training-plans', [
            'client_id' => $client->id,
            'title' => 'Beginner plan',
        ])->assertCreated()->json('data.plan.id');

        $this->assertDatabaseHas('training_plans', ['id' => $id, 'trainer_id' => $gym->id]);

        // A different business cannot see it.
        $other = $this->user(User::TYPE_BUSINESS, 'OtherGym');
        Sanctum::actingAs($other);
        $this->getJson("/api/v2/business/training-plans/{$id}")->assertNotFound();
    }
}
