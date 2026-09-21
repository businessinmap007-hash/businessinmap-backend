<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\PlanExerciseRound;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A trainer is told when a client has stopped showing up — but not every day,
 * and not for days the client was never asked to train. Dates are fixed in June
 * 2026 (1 June is a Monday) so nothing here depends on today.
 */
class TrainingLapseAlertTest extends TestCase
{
    use DatabaseTransactions;

    private User $trainer;

    private User $client;

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $this->client = $this->user(User::TYPE_CLIENT, 'Trainee');
    }

    /** An active plan on Mondays and Wednesdays, created on Monday 1 June. */
    private function plan(string $status = TrainingPlan::STATUS_ACTIVE, ?string $createdOn = '2026-06-01'): TrainingPlan
    {
        $this->travelTo(Carbon::parse(($createdOn ?? '2026-06-01') . ' 08:00'));

        $plan = TrainingPlan::create([
            'trainer_id' => $this->trainer->id, 'client_id' => $this->client->id,
            'title' => 'Strength', 'status' => $status, 'starts_on' => '2026-06-01',
        ]);
        $plan->exercises()->create(['name' => 'Squat', 'sets' => 3, 'day_of_week' => 1]);
        $plan->exercises()->create(['name' => 'Press', 'sets' => 3, 'day_of_week' => 3]);

        return $plan;
    }

    private function trained(TrainingPlan $plan, string $date): void
    {
        PlanExerciseRound::create([
            'plan_exercise_id' => $plan->exercises()->first()->id, 'training_plan_id' => $plan->id,
            'client_id' => $this->client->id, 'for_date' => $date, 'round_number' => 1, 'completed_at' => now(),
        ]);
    }

    private function alerts(string $asOf): int
    {
        $this->artisan('training:alert-lapses', ['--date' => $asOf])->assertSuccessful();

        return AppNotification::query()
            ->where('user_id', $this->trainer->id)->where('source_type', 'training_lapse')->count();
    }

    public function test_two_missed_scheduled_days_tell_the_trainer(): void
    {
        $plan = $this->plan();

        // Friday 5 June: Wednesday 3rd and Monday 1st were both scheduled and both missed.
        $this->assertSame(1, $this->alerts('2026-06-05'));

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->trainer->id,
            'source_type' => 'training_lapse',
            'action_type' => 'open_training_plan_manage',
            'notifiable_type' => TrainingPlan::class,
            'notifiable_id' => $plan->id,
        ]);
        $this->assertSame('2026-06-03', $plan->fresh()->lapse_alerted_on->toDateString());
    }

    public function test_one_missed_day_is_not_a_lapse(): void
    {
        $this->plan();

        // Wednesday 3 June: only Monday 1st has passed.
        $this->assertSame(0, $this->alerts('2026-06-03'));
    }

    public function test_a_day_with_any_activity_breaks_the_run(): void
    {
        $plan = $this->plan();
        $this->trained($plan, '2026-06-01');   // trained Monday; only Wednesday missed

        $this->assertSame(0, $this->alerts('2026-06-05'));
    }

    public function test_the_trainer_is_told_once_per_lapse_not_every_day(): void
    {
        $this->plan();

        $this->assertSame(1, $this->alerts('2026-06-05'));
        // The client stays away: later runs stay quiet.
        $this->assertSame(1, $this->alerts('2026-06-05'));
        $this->assertSame(1, $this->alerts('2026-06-08'));
        $this->assertSame(1, $this->alerts('2026-06-12'));
    }

    public function test_training_again_re_arms_the_alert_for_the_next_lapse(): void
    {
        $plan = $this->plan();
        $this->assertSame(1, $this->alerts('2026-06-05'));

        // Back on Monday 8th, then gone again for Wednesday 10th and Monday 15th.
        $this->trained($plan, '2026-06-08');
        $this->assertSame(1, $this->alerts('2026-06-12'), 'one missed day since training is not yet a lapse');

        $this->assertSame(2, $this->alerts('2026-06-16'));
        $this->assertSame('2026-06-15', $plan->fresh()->lapse_alerted_on->toDateString());
    }

    public function test_only_active_plans_are_watched(): void
    {
        $this->plan(TrainingPlan::STATUS_PENDING);

        $this->assertSame(0, $this->alerts('2026-06-05'));

        TrainingPlan::query()->update(['status' => TrainingPlan::STATUS_PAUSED]);
        $this->assertSame(0, $this->alerts('2026-06-05'));
    }

    public function test_an_any_day_exercise_never_defines_a_schedule(): void
    {
        $this->travelTo(Carbon::parse('2026-06-01 08:00'));
        $plan = TrainingPlan::create([
            'trainer_id' => $this->trainer->id, 'client_id' => $this->client->id,
            'title' => 'Flexible', 'status' => TrainingPlan::STATUS_ACTIVE, 'starts_on' => '2026-06-01',
        ]);
        // «Any day» is owed every day; treating that as a schedule would flag
        // everyone who trains three times a week.
        $plan->exercises()->create(['name' => 'Whenever', 'sets' => 3]);

        $this->assertSame(0, $this->alerts('2026-06-12'));
    }

    public function test_days_before_the_plan_existed_are_not_owed(): void
    {
        // Written on Thursday 4 June with a start date of the 1st: Monday and
        // Wednesday were never the client's to train.
        $this->plan(TrainingPlan::STATUS_ACTIVE, '2026-06-04');

        $this->assertSame(0, $this->alerts('2026-06-05'));
    }
}
