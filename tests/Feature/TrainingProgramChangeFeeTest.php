<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\PlatformEventFee;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Fees\PlatformEventFeeService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A programme is a paid month (owner, 2026-10-27): the fee the control panel set for each side (training and nutrition are
 * priced separately) is taken when the trainer shares it with a client and again for each renewed month, only when both can
 * pay; editing inside the month is free; a plan that is not renewed disappears from the client.
 */
class TrainingProgramChangeFeeTest extends TestCase
{
    use DatabaseTransactions;

    private User $trainer;

    private User $client;

    private function user(string $type, string $tag, float $balance): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);
        $u->save();

        app(WalletService::class)->getOrCreateWallet((int) $u->id)->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => $balance, 'locked_balance' => 0]);

        return $u->fresh();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->trainer = $this->user(User::TYPE_BUSINESS, 'Gym', 100);
        $this->client = $this->user(User::TYPE_CLIENT, 'Trainee', 100);
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function price(string $event, float $trainer, float $client): void
    {
        foreach (['trainer' => $trainer, 'client' => $client] as $role => $amount) {
            PlatformEventFee::query()->updateOrCreate(
                ['event_key' => $event, 'role' => $role],
                ['enabled' => $amount > 0, 'calc_type' => 'fixed', 'amount' => $amount, 'min_amount' => null, 'max_amount' => null]
            );
        }
    }

    private function balance(User $u): float
    {
        return (float) app(WalletService::class)->getOrCreateWallet($u->id)->balance;
    }

    private function share(array $extra = []): int
    {
        Sanctum::actingAs($this->trainer);

        return $this->postJson('/api/v2/business/training-plans', $extra + [
            'client_id' => $this->client->id,
            'title' => 'Month plan',
            'exercises' => [['name' => 'Squat', 'sets' => 3, 'reps' => '10']],
            'meals' => [['meal_type' => 'lunch', 'name' => 'Chicken + rice', 'calories' => 700]],
        ])->assertCreated()->json('data.plan.id');
    }

    public function test_sharing_a_programme_takes_the_fee_of_each_service_it_uses_from_each_side(): void
    {
        $this->price(PlatformEventFeeService::TRAINING_PROGRAM_CHANGE, 10, 5);
        $this->price(PlatformEventFeeService::NUTRITION_PROGRAM_CHANGE, 8, 0);

        $id = $this->share();

        $this->assertEquals(82, $this->balance($this->trainer), '10 for the workout + 8 for the meals');
        $this->assertEquals(95, $this->balance($this->client));
        $this->assertSame(now()->addMonth()->toDateString(), TrainingPlan::find($id)->paid_until->toDateString());

        // a workout-only plan pays only the workout fee
        $this->share(['meals' => []]);
        $this->assertEquals(72, $this->balance($this->trainer));
    }

    public function test_a_side_that_cannot_pay_stops_the_sharing_and_nothing_is_created_or_taken(): void
    {
        $this->price(PlatformEventFeeService::TRAINING_PROGRAM_CHANGE, 10, 50);
        app(WalletService::class)->getOrCreateWallet($this->client->id)->update(['balance' => 20]);

        Sanctum::actingAs($this->trainer);
        $res = $this->postJson('/api/v2/business/training-plans', [
            'client_id' => $this->client->id, 'title' => 'Month plan',
            'exercises' => [['name' => 'Squat', 'sets' => 3, 'reps' => '10']],
        ])->assertStatus(422)->assertJsonValidationErrors('balance');

        $this->assertStringNotContainsString('50', json_encode($res->json('errors')), 'the trainee shortfall is not told to the trainer');
        $this->assertSame(0, TrainingPlan::where('client_id', $this->client->id)->count());
        $this->assertEquals(100, $this->balance($this->trainer));
    }

    public function test_editing_inside_the_paid_month_is_free(): void
    {
        $this->price(PlatformEventFeeService::TRAINING_PROGRAM_CHANGE, 10, 0);
        $id = $this->share();
        $after = $this->balance($this->trainer);

        $this->postJson("/api/v2/business/training-plans/{$id}/exercises", ['name' => 'Deadlift', 'sets' => 3, 'reps' => '5'])->assertCreated();
        $this->postJson("/api/v2/business/training-plans/{$id}/exercises", ['name' => 'Row', 'sets' => 3, 'reps' => '8'])->assertCreated();

        $this->assertEquals($after, $this->balance($this->trainer));
    }

    public function test_the_first_meal_on_a_workout_only_plan_starts_the_nutrition_fee_for_the_month(): void
    {
        $this->price(PlatformEventFeeService::TRAINING_PROGRAM_CHANGE, 10, 0);
        $this->price(PlatformEventFeeService::NUTRITION_PROGRAM_CHANGE, 8, 0);
        $id = $this->share(['meals' => []]);
        $this->assertEquals(90, $this->balance($this->trainer));

        $this->postJson("/api/v2/business/training-plans/{$id}/meals", ['meal_type' => 'dinner', 'name' => 'Fish', 'calories' => 500])->assertCreated();
        $this->assertEquals(82, $this->balance($this->trainer));

        $this->postJson("/api/v2/business/training-plans/{$id}/meals", ['meal_type' => 'lunch', 'name' => 'Rice', 'calories' => 300])->assertCreated();
        $this->assertEquals(82, $this->balance($this->trainer), 'the second meal is the same month');
    }

    public function test_a_plan_not_renewed_disappears_from_the_client_and_editing_it_renews_it_for_another_fee(): void
    {
        $this->price(PlatformEventFeeService::TRAINING_PROGRAM_CHANGE, 10, 0);
        $id = $this->share(['meals' => []]);
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/training-plans/{$id}/accept")->assertOk();
        $this->assertCount(1, $this->getJson('/api/v2/training-plans')->json('data.data'));

        $this->travel(32)->days();

        Sanctum::actingAs($this->client);
        $this->assertCount(0, $this->getJson('/api/v2/training-plans')->json('data.data'), 'the month ran out');
        $this->getJson("/api/v2/training-plans/{$id}")->assertStatus(404);

        Sanctum::actingAs($this->trainer);
        $this->assertTrue($this->getJson("/api/v2/business/training-plans/{$id}")->json('data.plan.expired'), 'the trainer still sees it, flagged');

        // editing after the month renews it: the fee is taken again
        $before = $this->balance($this->trainer);
        $this->postJson("/api/v2/business/training-plans/{$id}/exercises", ['name' => 'Deadlift', 'sets' => 3, 'reps' => '5'])->assertCreated();
        $this->assertEquals($before - 10, $this->balance($this->trainer));
        $this->assertSame(1, (int) TrainingPlan::find($id)->renewals);

        Sanctum::actingAs($this->client);
        $this->assertCount(1, $this->getJson('/api/v2/training-plans')->json('data.data'), 'back in front of the client');
    }

    public function test_renewing_without_editing_takes_the_fee_and_a_finished_plan_is_not_renewed(): void
    {
        $this->price(PlatformEventFeeService::TRAINING_PROGRAM_CHANGE, 10, 5);
        $id = $this->share(['meals' => []]);
        $this->assertEquals(90, $this->balance($this->trainer));

        $this->travel(32)->days();
        Sanctum::actingAs($this->trainer);
        $this->postJson("/api/v2/business/training-plans/{$id}/renew")->assertOk()->assertJsonPath('data.plan.expired', false);
        $this->assertEquals(80, $this->balance($this->trainer));
        $this->assertEquals(90, $this->balance($this->client));

        TrainingPlan::whereKey($id)->update(['status' => TrainingPlan::STATUS_CANCELLED]);
        $this->postJson("/api/v2/business/training-plans/{$id}/renew")->assertStatus(422);
    }

    public function test_nothing_is_taken_while_the_panel_has_priced_nothing(): void
    {
        $this->share();
        $this->assertEquals(100, $this->balance($this->trainer));
        $this->assertEquals(100, $this->balance($this->client));
    }

    public function test_trainer_and_client_are_told_once_a_few_days_before_the_month_runs_out(): void
    {
        $id = $this->share(['meals' => []]);

        $this->artisan('training:alert-expiring')->assertSuccessful();
        $this->assertSame(0, AppNotification::where('source_type', 'training_plan_expiring')->where('source_id', $id)->count(), 'a fresh plan has a month to go');

        $this->travel(28)->days();
        $this->artisan('training:alert-expiring')->assertSuccessful();
        $told = AppNotification::where('source_type', 'training_plan_expiring')->where('source_id', $id)->get();
        $this->assertEqualsCanonicalizing([$this->trainer->id, $this->client->id], $told->pluck('user_id')->all());

        // the next day announces nothing more
        $this->travel(1)->days();
        $this->artisan('training:alert-expiring')->assertSuccessful();
        $this->assertSame(2, AppNotification::where('source_type', 'training_plan_expiring')->where('source_id', $id)->count());

        // a renewed month is announced again when it nears its end
        Sanctum::actingAs($this->trainer);
        $this->postJson("/api/v2/business/training-plans/{$id}/renew")->assertOk();
        $this->travel(28)->days();
        $this->artisan('training:alert-expiring')->assertSuccessful();
        $this->assertSame(4, AppNotification::where('source_type', 'training_plan_expiring')->where('source_id', $id)->count());
    }

    public function test_a_plan_past_its_paid_month_is_not_counted_in_lapse_alerts_or_the_weekly_summary(): void
    {
        $id = $this->share(['meals' => []]);
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/training-plans/{$id}/accept")->assertOk();

        Sanctum::actingAs($this->trainer);
        $this->assertSame(1, $this->getJson('/api/v2/business/training-plans/weekly-summary')->json('data.summary.plans'));

        // schedule a training day every day so a lapse is certain once the plan is old enough, then let the month run out
        TrainingPlan::whereKey($id)->first()->exercises()->update(['day_of_week' => null]);
        $this->travel(33)->days();

        Sanctum::actingAs($this->trainer);
        $this->assertSame(0, $this->getJson('/api/v2/business/training-plans/weekly-summary')->json('data.summary.plans'), 'hidden from the client = nothing to average');
        $this->artisan('training:alert-lapses')->assertSuccessful();
        $this->assertSame(0, AppNotification::where('user_id', $this->trainer->id)->where('source_type', 'training_lapse')->count());
    }
}
