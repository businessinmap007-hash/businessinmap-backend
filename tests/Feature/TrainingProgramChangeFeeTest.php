<?php

namespace Tests\Feature;

use App\Models\PlatformEventCharge;
use App\Models\PlatformEventFee;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Fees\PlatformEventFeeService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A programme runs one month; changing it while it runs costs the fee the control panel set for each side
 * (training and nutrition are priced separately), only when both can pay, and a burst of edits is one change.
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

    private function activePlan(): int
    {
        Sanctum::actingAs($this->trainer);
        $id = $this->postJson('/api/v2/business/training-plans', [
            'client_id' => $this->client->id,
            'title' => 'Month plan',
            'exercises' => [['name' => 'Squat', 'sets' => 3, 'reps' => '10']],
            'meals' => [['meal_type' => 'lunch', 'name' => 'Chicken + rice', 'calories' => 700]],
        ])->assertCreated()->json('data.plan.id');

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/training-plans/{$id}/accept")->assertOk();

        return $id;
    }

    public function test_a_new_programme_runs_one_month_unless_its_length_is_set(): void
    {
        Carbon::setTestNow('2026-03-01');
        $id = $this->activePlan();
        $plan = TrainingPlan::find($id);

        $this->assertSame('2026-03-01', $plan->starts_on->toDateString());
        $this->assertSame('2026-03-31', $plan->ends_on->toDateString());

        Carbon::setTestNow('2026-01-31');
        $other = $this->activePlan();
        $this->assertSame('2026-02-27', TrainingPlan::find($other)->ends_on->toDateString(), 'a month from 31 Jan ends on the last day of Feb');

        Carbon::setTestNow();
    }

    public function test_changing_a_running_programme_charges_each_side_once_per_burst_and_the_two_kinds_separately(): void
    {
        $this->price(PlatformEventFeeService::TRAINING_PROGRAM_CHANGE, 10, 5);
        $this->price(PlatformEventFeeService::NUTRITION_PROGRAM_CHANGE, 8, 0);
        $id = $this->activePlan();

        Sanctum::actingAs($this->trainer);
        $this->assertEquals(100, $this->balance($this->trainer), 'drafting before acceptance was free');

        $this->postJson("/api/v2/business/training-plans/{$id}/exercises", ['name' => 'Deadlift', 'sets' => 3, 'reps' => '5'])->assertCreated();
        $this->assertEquals(90, $this->balance($this->trainer));
        $this->assertEquals(95, $this->balance($this->client));

        // the same burst of edits is the same change
        $this->postJson("/api/v2/business/training-plans/{$id}/exercises", ['name' => 'Row', 'sets' => 3, 'reps' => '8'])->assertCreated();
        $this->assertEquals(90, $this->balance($this->trainer));

        // the meals are the other event, with its own price
        $this->postJson("/api/v2/business/training-plans/{$id}/meals", ['meal_type' => 'dinner', 'name' => 'Fish', 'calories' => 500])->assertCreated();
        $this->assertEquals(82, $this->balance($this->trainer));
        $this->assertEquals(95, $this->balance($this->client), 'nutrition is free for the trainee');

        // a later burst is a new change
        $this->travel(2)->hours();
        $this->postJson("/api/v2/business/training-plans/{$id}/exercises", ['name' => 'Curl', 'sets' => 3, 'reps' => '12'])->assertCreated();
        $this->assertEquals(72, $this->balance($this->trainer));
        $this->assertEquals(90, $this->balance($this->client));
        $this->assertSame(2, PlatformEventCharge::where('event_key', PlatformEventFeeService::TRAINING_PROGRAM_CHANGE)->where('role', 'trainer')->where('reference_id', $id)->count());
    }

    public function test_a_side_that_cannot_pay_stops_the_change(): void
    {
        $this->price(PlatformEventFeeService::TRAINING_PROGRAM_CHANGE, 10, 50);
        $id = $this->activePlan();
        app(WalletService::class)->getOrCreateWallet($this->client->id)->update(['balance' => 20]);

        Sanctum::actingAs($this->trainer);
        $res = $this->postJson("/api/v2/business/training-plans/{$id}/exercises", ['name' => 'Deadlift', 'sets' => 3, 'reps' => '5'])
            ->assertStatus(422)->assertJsonValidationErrors('balance');

        $this->assertStringNotContainsString('50', json_encode($res->json('errors')), 'the trainee\'s shortfall is not told to the trainer');
        $this->assertSame(1, TrainingPlan::find($id)->exercises()->count(), 'nothing was added');
        $this->assertEquals(100, $this->balance($this->trainer), 'nothing was taken');
    }

    public function test_nothing_is_charged_while_the_panel_has_priced_nothing_or_the_plan_is_not_running(): void
    {
        $id = $this->activePlan();
        Sanctum::actingAs($this->trainer);
        $this->postJson("/api/v2/business/training-plans/{$id}/exercises", ['name' => 'Deadlift', 'sets' => 3, 'reps' => '5'])->assertCreated();
        $this->assertEquals(100, $this->balance($this->trainer));

        $this->price(PlatformEventFeeService::TRAINING_PROGRAM_CHANGE, 10, 5);
        TrainingPlan::whereKey($id)->update(['status' => TrainingPlan::STATUS_PAUSED]);
        $this->postJson("/api/v2/business/training-plans/{$id}/exercises", ['name' => 'Row', 'sets' => 3, 'reps' => '8'])->assertCreated();
        $this->assertEquals(100, $this->balance($this->trainer), 'a paused plan is still being drafted');
    }
}
