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
 * The direct, text-only chat between a trainer and trainee, scoped to a plan.
 * Both parties talk; a stranger can't; and there are no attachments by design.
 */
class TrainingChatTest extends TestCase
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

    private function plan(User $trainer, User $client): TrainingPlan
    {
        return TrainingPlan::create([
            'trainer_id' => $trainer->id,
            'client_id' => $client->id,
            'title' => 'Plan',
            'status' => TrainingPlan::STATUS_ACTIVE,
        ]);
    }

    public function test_trainer_and_trainee_exchange_messages(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');
        $plan = $this->plan($trainer, $client);

        // Client opens the chat (a system line is there) and writes.
        Sanctum::actingAs($client);
        $this->getJson("/api/v2/training-plans/{$plan->id}/chat")->assertOk();
        $this->postJson("/api/v2/training-plans/{$plan->id}/chat/messages", ['body' => 'When do I start?'])
            ->assertCreated()->assertJsonPath('data.body', 'When do I start?');

        // Trainer reads it and replies (posting AS the gym, a seated party).
        Sanctum::actingAs($trainer);
        $this->getJson("/api/v2/business/training-plans/{$plan->id}/chat")
            ->assertOk()->assertJsonFragment(['body' => 'When do I start?']);
        $this->postJson("/api/v2/business/training-plans/{$plan->id}/chat/messages", ['body' => 'Tomorrow 6am'])
            ->assertCreated()->assertJsonPath('data.is_mine', true);

        // Client sees the reply.
        Sanctum::actingAs($client);
        $this->getJson("/api/v2/training-plans/{$plan->id}/chat")
            ->assertOk()->assertJsonFragment(['body' => 'Tomorrow 6am']);
    }

    public function test_the_chat_is_text_only(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');
        $plan = $this->plan($trainer, $client);

        Sanctum::actingAs($client);
        // An empty body is refused (no attachments can stand in for it).
        $this->postJson("/api/v2/training-plans/{$plan->id}/chat/messages", ['body' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('body');

        // The meta advertises that attachments are not allowed here.
        $this->getJson("/api/v2/training-plans/{$plan->id}/chat")
            ->assertOk()->assertJsonPath('meta.thread.attachments_allowed', false);
    }

    public function test_a_stranger_cannot_read_or_post(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');
        $plan = $this->plan($trainer, $client);

        $stranger = $this->user(User::TYPE_CLIENT, 'Stranger');
        Sanctum::actingAs($stranger);
        $this->getJson("/api/v2/training-plans/{$plan->id}/chat")->assertNotFound();
        $this->postJson("/api/v2/training-plans/{$plan->id}/chat/messages", ['body' => 'hi'])->assertNotFound();

        $otherGym = $this->user(User::TYPE_BUSINESS, 'OtherGym');
        Sanctum::actingAs($otherGym);
        $this->getJson("/api/v2/business/training-plans/{$plan->id}/chat")->assertNotFound();
    }

    public function test_a_training_delegate_chats_as_the_gym(): void
    {
        $gym = $this->user(User::TYPE_BUSINESS, 'Gym');
        $coach = $this->user(User::TYPE_CLIENT, 'Coach');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');
        $plan = $this->plan($gym, $client);
        BusinessStaff::create([
            'business_id' => $gym->id,
            'user_id' => $coach->id,
            'capabilities' => [BusinessCapability::TRAINING],
            'is_active' => true,
        ]);

        Sanctum::actingAs($coach);
        $this->postJson("/api/v2/business/training-plans/{$plan->id}/chat/messages", ['body' => 'Good job'])
            ->assertCreated();

        // The message is seated as the gym, so the client sees it as the trainer.
        Sanctum::actingAs($client);
        $this->getJson("/api/v2/training-plans/{$plan->id}/chat")
            ->assertOk()->assertJsonFragment(['body' => 'Good job']);
    }
}
