<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A training plan used to go live the instant the trainer created it. Now
 * the client must accept before it activates (mirrors the staff invitation
 * shape): the plan starts `pending`, TrainingPlanService::setStatus()
 * refuses to move it out of `pending` on the trainer's own PATCH, and only
 * accept()/decline() (the client's own) change that.
 */
class TrainingPlanAcceptanceTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type, string $tag): User
    {
        $u = new User();
        $u->name = $tag.' '.Str::random(4);
        $u->email = strtolower($tag).'-'.uniqid().'@example.test';
        $u->phone = '01'.random_int(100000000, 999999999);
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
            'exercises' => [],
            'meals' => [],
        ])->assertCreated()->json('data.plan.id');
    }

    public function test_a_fresh_plan_starts_pending_and_notifies_the_client(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');

        $id = $this->createPlan($trainer, $client);

        $this->assertDatabaseHas('training_plans', ['id' => $id, 'status' => TrainingPlan::STATUS_PENDING]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $client->id,
            'action_type' => 'open_training_plan',
            'notifiable_type' => TrainingPlan::class,
            'notifiable_id' => $id,
            'source_type' => 'training_plan_assigned',
        ]);
    }

    public function test_progress_is_refused_before_acceptance(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');
        $id = $this->createPlan($trainer, $client);

        Sanctum::actingAs($client);
        $this->postJson("/api/v2/training-plans/{$id}/progress", ['weight' => 80])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_the_trainer_cannot_activate_it_directly(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');
        $id = $this->createPlan($trainer, $client);

        Sanctum::actingAs($trainer);
        $this->patchJson("/api/v2/business/training-plans/{$id}", ['status' => 'active'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_accepting_activates_the_plan_and_notifies_the_trainer(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');
        $id = $this->createPlan($trainer, $client);

        Sanctum::actingAs($client);
        $this->postJson("/api/v2/training-plans/{$id}/accept")
            ->assertOk()->assertJsonPath('data.plan.status', TrainingPlan::STATUS_ACTIVE);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $trainer->id, 'actor_id' => $client->id, 'source_type' => 'training_plan_accepted',
        ]);

        $this->postJson("/api/v2/training-plans/{$id}/progress", ['weight' => 80])->assertCreated();
    }

    public function test_declining_leaves_the_plan_permanently_inactive(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');
        $id = $this->createPlan($trainer, $client);

        Sanctum::actingAs($client);
        $this->postJson("/api/v2/training-plans/{$id}/decline")
            ->assertOk()->assertJsonPath('data.plan.status', TrainingPlan::STATUS_DECLINED);

        $this->postJson("/api/v2/training-plans/{$id}/progress", ['weight' => 80])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_only_the_assigned_client_can_accept(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');
        $stranger = $this->user(User::TYPE_CLIENT, 'Stranger');
        $id = $this->createPlan($trainer, $client);

        Sanctum::actingAs($stranger);
        $this->postJson("/api/v2/training-plans/{$id}/accept")->assertNotFound();
    }
}
