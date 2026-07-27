<?php

namespace Tests\Feature;

use App\Models\BusinessStaff;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Reusable training templates: a trainer builds one once and applies it to many
 * clients — each application copies the items into a fresh plan, decoupled from
 * the template.
 */
class TrainingTemplateTest extends TestCase
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

    private function makeTemplate(User $trainer): int
    {
        Sanctum::actingAs($trainer);

        return $this->postJson('/api/v2/business/training-templates', [
            'title' => 'Beginner full body',
            'goal' => 'general fitness',
            'exercises' => [
                ['name' => 'Squat', 'sets' => 3, 'reps' => '12'],
                ['name' => 'Push-up', 'sets' => 3, 'reps' => '15'],
            ],
            'meals' => [
                ['meal_type' => 'breakfast', 'name' => 'Eggs', 'calories' => 300],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.template.exercises_count', 2)
            ->json('data.template.id');
    }

    public function test_trainer_builds_a_template_and_applies_it_to_a_client(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');

        $templateId = $this->makeTemplate($trainer);

        // Apply → a real plan is created for the client, copying the items.
        $planId = $this->postJson("/api/v2/business/training-templates/{$templateId}/apply", [
            'client_id' => $client->id,
            'starts_on' => '2026-08-01',
        ])->assertCreated()->json('data.plan_id');

        $this->assertDatabaseHas('training_plans', [
            'id' => $planId,
            'trainer_id' => $trainer->id,
            'client_id' => $client->id,
            'title' => 'Beginner full body',
        ]);

        // The client sees the plan with the copied exercises + meals.
        Sanctum::actingAs($client);
        $res = $this->getJson("/api/v2/training-plans/{$planId}")->assertOk();
        $this->assertCount(2, $res->json('data.plan.exercises'));
        $this->assertCount(1, $res->json('data.plan.meals'));
    }

    public function test_editing_the_template_does_not_change_an_already_applied_plan(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');
        $templateId = $this->makeTemplate($trainer);

        Sanctum::actingAs($trainer);
        $planId = $this->postJson("/api/v2/business/training-templates/{$templateId}/apply", [
            'client_id' => $client->id,
        ])->json('data.plan_id');

        // Add an exercise to the template AFTER applying.
        $this->postJson("/api/v2/business/training-templates/{$templateId}/exercises", [
            'name' => 'Deadlift', 'sets' => 5, 'reps' => '5',
        ])->assertCreated();

        // The already-applied plan still has the original 2 exercises (a copy).
        $this->assertSame(2, TrainingPlan::find($planId)->exercises()->count());
        // …while the template now has 3.
        $this->getJson("/api/v2/business/training-templates/{$templateId}")
            ->assertJsonPath('data.template.exercises_count', 3);
    }

    public function test_a_template_is_scoped_to_its_owner(): void
    {
        $owner = $this->user(User::TYPE_BUSINESS, 'Gym');
        $other = $this->user(User::TYPE_BUSINESS, 'OtherGym');
        $templateId = $this->makeTemplate($owner);

        Sanctum::actingAs($other);
        $this->getJson("/api/v2/business/training-templates/{$templateId}")->assertNotFound();
        $this->postJson("/api/v2/business/training-templates/{$templateId}/apply", [
            'client_id' => $this->user(User::TYPE_CLIENT, 'X')->id,
        ])->assertNotFound();
    }

    public function test_a_client_cannot_create_a_template(): void
    {
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'Client'));
        $this->postJson('/api/v2/business/training-templates', ['title' => 'X'])->assertForbidden();
    }

    public function test_a_training_delegate_can_build_and_apply_templates(): void
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

        Sanctum::actingAs($coach);
        $templateId = $this->postJson('/api/v2/business/training-templates', [
            'title' => 'Coach template',
            'exercises' => [['name' => 'Plank', 'sets' => 3]],
        ])->assertCreated()->json('data.template.id');

        $planId = $this->postJson("/api/v2/business/training-templates/{$templateId}/apply", [
            'client_id' => $client->id,
        ])->assertCreated()->json('data.plan_id');

        // Template + resulting plan both belong to the GYM.
        $this->assertDatabaseHas('training_plan_templates', ['id' => $templateId, 'trainer_id' => $gym->id]);
        $this->assertDatabaseHas('training_plans', ['id' => $planId, 'trainer_id' => $gym->id]);
    }
}
