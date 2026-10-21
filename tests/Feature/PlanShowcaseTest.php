<?php

namespace Tests\Feature;

use App\Models\BodyCompositionReport;
use App\Models\PlanShowcase;
use App\Models\Post;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «نتائج موثَّقة» for training / nutrition plans: ONE approval of the request covers the whole programme, and the post then
 * updates by itself from the trainer's monthly reports — changes only, no names, no weights, no photos.
 */
class PlanShowcaseTest extends TestCase
{
    use DatabaseTransactions;

    private User $trainer;
    private User $client;
    private TrainingPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trainer = $this->user('Coach', User::TYPE_BUSINESS);
        $this->client = $this->user('Trainee', User::TYPE_CLIENT);

        $this->plan = TrainingPlan::create([
            'trainer_id' => $this->trainer->id, 'client_id' => $this->client->id, 'title' => 'Ahmed cutting plan',
            'status' => TrainingPlan::STATUS_ACTIVE, 'starts_on' => now()->subWeeks(9)->toDateString(),
        ]);
        $this->plan->exercises()->create(['name' => 'Squat', 'sets' => 3, 'reps' => '10']);
        $this->plan->meals()->create(['meal_type' => 'lunch', 'name' => 'Grilled chicken']);
    }

    private function user(string $tag, string $type): User
    {
        $u = new User();
        $u->forceFill([
            'name' => $tag . ' ' . Str::random(4), 'type' => $type, 'email' => strtolower($tag) . '-' . uniqid() . '@example.test',
            'phone' => '010' . random_int(10000000, 99999999), 'password' => 'secret-password', 'api_token' => Str::random(60),
        ])->save();

        return $u;
    }

    private function report(string $month, float $weight, float $fat): BodyCompositionReport
    {
        return BodyCompositionReport::create([
            'training_plan_id' => $this->plan->id, 'client_id' => $this->client->id, 'trainer_id' => $this->trainer->id,
            'for_month' => $month, 'measured_on' => $month, 'weight_kg' => $weight, 'fat_percent' => $fat,
        ]);
    }

    private function ask()
    {
        Sanctum::actingAs($this->trainer);

        return $this->postJson("/api/v2/business/training-plans/{$this->plan->id}/showcase", ['public_title' => 'Eight weeks, real results', 'note' => 'Cutting']);
    }

    public function test_one_approval_publishes_and_every_later_month_shows_without_asking_again(): void
    {
        $this->report('2026-08-01', 95.0, 30.0);
        $this->report('2026-09-01', 91.5, 26.5);

        $this->ask()->assertCreated()->assertJsonPath('data.showcase.status', 'requested');
        $this->assertNull(PlanShowcase::query()->where('training_plan_id', $this->plan->id)->value('post_id'), 'nothing before he agrees');

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/showcase/approve")->assertOk()->assertJsonPath('data.showcase.status', 'approved');

        $postId = (int) PlanShowcase::query()->where('training_plan_id', $this->plan->id)->value('post_id');
        $fetch = fn () => collect($this->getJson("/api/v2/businesses/{$this->trainer->id}/posts")->json('data'))->firstWhere('id', $postId)['plan_showcase'];

        $s = $fetch();
        $this->assertSame('combined', $s['kind']);
        $this->assertSame(1, $s['confirmed_months']);
        $this->assertEquals(-3.5, $s['total_change']['weight_kg']);

        // the trainer records the next month: it shows by itself — the client is not asked again
        BodyCompositionReport::create(['training_plan_id' => $this->plan->id, 'client_id' => $this->client->id, 'trainer_id' => $this->trainer->id, 'for_month' => '2026-10-01', 'measured_on' => '2026-10-01', 'weight_kg' => 88.0, 'fat_percent' => 23.0]);
        $this->assertSame(2, $fetch()['confirmed_months']);
        $this->assertEquals(-7.0, $fetch()['total_change']['weight_kg']);

        $raw = json_encode($fetch(), JSON_UNESCAPED_UNICODE);
        foreach (['Ahmed', $this->client->name, '95', '91.5', 'photo', 'image'] as $leak) {
            $this->assertStringNotContainsString($leak, $raw, "leaked: {$leak}");
        }
    }

    public function test_an_empty_or_unaccepted_plan_cannot_be_shown(): void
    {
        $this->plan->update(['status' => TrainingPlan::STATUS_PENDING]);
        $this->ask()->assertStatus(422);

        $this->plan->update(['status' => TrainingPlan::STATUS_ACTIVE]);
        $this->plan->exercises()->delete();
        $this->plan->meals()->delete();
        $this->ask()->assertStatus(422);
    }

    public function test_a_declined_request_publishes_nothing_and_can_be_asked_again(): void
    {
        $this->ask()->assertCreated();

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/showcase/decline", ['note' => 'Not now'])->assertOk()->assertJsonPath('data.showcase.status', 'declined');
        $this->assertNull(PlanShowcase::query()->where('training_plan_id', $this->plan->id)->value('post_id'));

        $this->ask()->assertCreated()->assertJsonPath('data.showcase.status', 'requested');
    }

    public function test_either_side_takes_it_down_and_only_the_clients_own_plan_is_his_to_answer(): void
    {
        $this->ask()->assertCreated();
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/showcase/approve")->assertOk();
        $postId = (int) PlanShowcase::query()->where('training_plan_id', $this->plan->id)->value('post_id');

        $this->postJson("/api/v2/training-plans/{$this->plan->id}/showcase/withdraw")->assertOk();
        $this->assertFalse((bool) Post::query()->whereKey($postId)->value('is_active'));
        $this->getJson("/api/v2/posts/{$postId}")->assertNotFound();

        // asked again and approved again: the same post comes back; the trainer can take it down too
        $this->ask()->assertCreated();
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/showcase/approve")->assertOk();
        $this->assertTrue((bool) Post::query()->whereKey($postId)->value('is_active'));
        Sanctum::actingAs($this->trainer);
        $this->deleteJson("/api/v2/business/training-plans/{$this->plan->id}/showcase")->assertOk();
        $this->assertFalse((bool) Post::query()->whereKey($postId)->value('is_active'));

        $stranger = $this->user('Other', User::TYPE_CLIENT);
        Sanctum::actingAs($stranger);
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/showcase/approve")->assertNotFound();
    }

    public function test_month_by_month_mode_shows_only_the_months_the_client_approved_and_a_renewal_opens_the_next_question(): void
    {
        $this->report('2026-08-01', 95.0, 30.0);
        $this->report('2026-09-01', 91.5, 26.5);

        Sanctum::actingAs($this->trainer);
        $this->postJson("/api/v2/business/training-plans/{$this->plan->id}/showcase", ['public_title' => 'Real results', 'consent_mode' => 'each'])->assertCreated()->assertJsonPath('data.showcase.consent_mode', 'each');

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/showcase/approve")->assertOk();
        $postId = (int) PlanShowcase::query()->where('training_plan_id', $this->plan->id)->value('post_id');
        $fetch = fn () => collect($this->getJson("/api/v2/businesses/{$this->trainer->id}/posts")->json('data'))->firstWhere('id', $postId)['plan_showcase'];

        // approved as a post, but no month is in it yet: each month is asked
        $this->assertSame(0, $fetch()['confirmed_months']);
        $units = $this->getJson("/api/v2/training-plans/{$this->plan->id}")->json('data.plan.showcase.units');
        $this->assertSame([['key' => '2026-08', 'status' => 'pending'], ['key' => '2026-09', 'status' => 'pending']], $units);

        $this->postJson("/api/v2/training-plans/{$this->plan->id}/showcase/months/2026-08/approve")->assertOk();
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/showcase/months/2026-09/approve")->assertOk();
        $this->assertSame(1, $fetch()['confirmed_months']);
        $this->assertEquals(-3.5, $fetch()['total_change']['weight_kg']);

        // a month he declines never shows; a month nobody asked about cannot be answered
        $this->report('2026-07-01', 98.0, 33.0);
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/showcase/months/2026-07/decline")->assertOk();
        $this->assertSame(1, $fetch()['confirmed_months']);
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/showcase/months/2030-01/approve")->assertStatus(422);

        // the renewal of the paid month opens that month's question
        app(\App\Services\Training\PlanShowcaseService::class)->monthRenewed($this->plan->fresh());
        $units = collect($this->getJson("/api/v2/training-plans/{$this->plan->id}")->json('data.plan.showcase.units'));
        $this->assertSame('pending', $units->firstWhere('key', now()->format('Y-m'))['status']);
    }

    public function test_the_client_can_make_a_once_for_all_request_stricter_but_not_looser(): void
    {
        $this->report('2026-08-01', 95.0, 30.0);
        $this->report('2026-09-01', 91.5, 26.5);
        $this->ask()->assertCreated()->assertJsonPath('data.showcase.consent_mode', 'once');

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/showcase/approve", ['consent_mode' => 'each'])->assertOk()->assertJsonPath('data.showcase.consent_mode', 'each');
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/showcase/approve", ['consent_mode' => 'once'])->assertStatus(422);
    }
}
