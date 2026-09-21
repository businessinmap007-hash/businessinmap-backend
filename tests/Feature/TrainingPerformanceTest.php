<?php

namespace Tests\Feature;

use App\Models\BodyCompositionReport;
use App\Models\PlanExerciseRound;
use App\Models\PlanSessionCompletion;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * What the trainee actually did. A confirmed set now carries the reps and the
 * weight; finishing everything owed that day tells the trainer once; and the
 * month rolls up from the sets themselves.
 */
class TrainingPerformanceTest extends TestCase
{
    use DatabaseTransactions;

    private User $trainer;

    private User $client;

    private TrainingPlan $plan;

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
        $this->plan = TrainingPlan::create([
            'trainer_id' => $this->trainer->id, 'client_id' => $this->client->id,
            'title' => 'Plan', 'status' => TrainingPlan::STATUS_ACTIVE,
        ]);
    }

    /** A Monday far enough in the past that "now" never interferes. */
    private const MONDAY = '2026-06-01';

    private function exercise(string $name, int $sets, ?int $weekday = null, ?float $weight = null)
    {
        return $this->plan->exercises()->create([
            'name' => $name, 'sets' => $sets, 'reps' => '10', 'day_of_week' => $weekday, 'target_weight' => $weight,
        ]);
    }

    private function done($exercise, ?int $reps = null, ?float $weight = null, string $date = self::MONDAY)
    {
        $body = array_filter(['for_date' => $date, 'reps' => $reps, 'weight' => $weight], fn ($v) => $v !== null);

        return $this->postJson("/api/v2/training-plans/{$this->plan->id}/exercises/{$exercise->id}/complete-round", $body);
    }

    public function test_a_set_records_the_reps_and_weight_that_were_done(): void
    {
        $squat = $this->exercise('Squat', 3, null, 60);
        Sanctum::actingAs($this->client);

        $this->done($squat, 10, 60)->assertCreated()
            ->assertJsonPath('data.round.round_number', 1)
            ->assertJsonPath('data.round.reps', 10)
            ->assertJsonPath('data.round.weight', 60)
            ->assertJsonPath('data.completed_rounds', 1)
            ->assertJsonPath('data.session_completed', false);

        // A set confirmed with no numbers still counts as done.
        $this->done($squat)->assertCreated()->assertJsonPath('data.round.reps', null);

        $this->assertDatabaseHas('plan_exercise_rounds', ['plan_exercise_id' => $squat->id, 'round_number' => 1, 'reps' => 10, 'weight' => 60]);
    }

    public function test_the_target_weight_is_prescribed_and_read_back(): void
    {
        Sanctum::actingAs($this->trainer);

        $this->postJson("/api/v2/business/training-plans/{$this->plan->id}/exercises", [
            'name' => 'Press', 'sets' => 3, 'reps' => '8', 'target_weight' => 42.5,
        ])->assertCreated()->assertJsonPath('data.exercise.target_weight', 42.5);

        Sanctum::actingAs($this->client);
        $exercises = $this->getJson("/api/v2/training-plans/{$this->plan->id}")->assertOk()->json('data.plan.exercises');
        $this->assertEquals(42.5, $exercises[0]['target_weight']);
    }

    public function test_the_numbers_are_validated(): void
    {
        $squat = $this->exercise('Squat', 3);
        Sanctum::actingAs($this->client);

        $this->done($squat, 5000)->assertStatus(422)->assertJsonValidationErrors(['reps']);
        $this->done($squat, 10, -5)->assertStatus(422)->assertJsonValidationErrors(['weight']);
        $this->assertSame(0, PlanExerciseRound::query()->where('plan_exercise_id', $squat->id)->count());
    }

    public function test_finishing_every_exercise_owed_that_day_tells_the_trainer_once(): void
    {
        $squat = $this->exercise('Squat', 2);
        $press = $this->exercise('Press', 1);
        Sanctum::actingAs($this->client);

        $this->done($squat, 10, 60)->assertJsonPath('data.session_completed', false);
        $this->done($press, 8, 40)->assertJsonPath('data.session_completed', false);
        $this->assertSame(0, PlanSessionCompletion::query()->where('training_plan_id', $this->plan->id)->count());

        // The last set of the last exercise completes the day.
        $this->done($squat, 10, 60)->assertJsonPath('data.session_completed', true);

        $completion = PlanSessionCompletion::query()->where('training_plan_id', $this->plan->id)->firstOrFail();
        $this->assertSame(3, $completion->sets_count);
        $this->assertSame(28, $completion->total_reps);
        // 10*60 + 8*40 + 10*60
        $this->assertEqualsWithDelta(1520.0, $completion->volume_kg, 0.001);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->trainer->id,
            'source_type' => 'training_session_completed',
            'notifiable_type' => TrainingPlan::class,
            'notifiable_id' => $this->plan->id,
            'action_type' => 'open_training_plan_manage',
        ]);

        // Nothing further can be confirmed today, so the trainer is never told twice.
        $this->done($squat)->assertStatus(422);
        $this->assertSame(1, PlanSessionCompletion::query()->where('training_plan_id', $this->plan->id)->count());
        $this->assertSame(1, \App\Models\AppNotification::query()
            ->where('user_id', $this->trainer->id)->where('source_type', 'training_session_completed')->count());
    }

    public function test_only_the_exercises_owed_on_that_weekday_are_required(): void
    {
        $monday = $this->exercise('Monday lift', 1, 1);     // 1 = Monday
        $this->exercise('Wednesday lift', 1, 3);            // not owed on a Monday
        $anyDay = $this->exercise('Every day', 1);          // null = any day

        Sanctum::actingAs($this->client);

        $this->done($monday, 5, 20)->assertJsonPath('data.session_completed', false);
        $this->done($anyDay, 5, 20)->assertJsonPath('data.session_completed', true);
    }

    public function test_a_trainee_can_correct_a_set_and_the_finished_day_is_retotalled(): void
    {
        $squat = $this->exercise('Squat', 1);
        Sanctum::actingAs($this->client);
        $roundId = $this->done($squat, 10, 50)->assertJsonPath('data.session_completed', true)->json('data.round.id');

        $this->putJson("/api/v2/training-plans/{$this->plan->id}/exercises/{$squat->id}/rounds/{$roundId}", ['reps' => 12, 'weight' => 55])
            ->assertOk()->assertJsonPath('data.round.reps', 12)->assertJsonPath('data.round.weight', 55);

        $completion = PlanSessionCompletion::query()->where('training_plan_id', $this->plan->id)->firstOrFail();
        $this->assertEqualsWithDelta(660.0, $completion->volume_kg, 0.001);

        // Someone else's set is not theirs to edit.
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'Stranger'));
        $this->putJson("/api/v2/training-plans/{$this->plan->id}/exercises/{$squat->id}/rounds/{$roundId}", ['reps' => 1])
            ->assertStatus(404);
    }

    public function test_the_monthly_summary_rolls_up_the_sets_for_both_sides(): void
    {
        $squat = $this->exercise('Squat', 2);
        $curl = $this->exercise('Curl', 1);
        Sanctum::actingAs($this->client);

        // Two days in June: day 1 finishes, day 2 has one unfinished exercise.
        $this->done($squat, 10, 60, '2026-06-01');
        $this->done($squat, 8, 70, '2026-06-01');
        $this->done($curl, 12, 20, '2026-06-01');
        $this->done($squat, 10, null, '2026-06-08');          // reps but no weight: a set, no volume
        // One set in July must not leak into June.
        $this->done($squat, 10, 100, '2026-07-06');

        $this->plan->progressLogs()->create(['client_id' => $this->client->id, 'logged_on' => '2026-06-02', 'weight' => 80]);
        $this->plan->progressLogs()->create(['client_id' => $this->client->id, 'logged_on' => '2026-06-20', 'weight' => 78.5]);
        BodyCompositionReport::create([
            'training_plan_id' => $this->plan->id, 'client_id' => $this->client->id, 'trainer_id' => $this->trainer->id,
            'for_month' => '2026-06-01', 'measured_on' => '2026-06-28', 'weight_kg' => 78.5, 'fat_percent' => 18.0,
        ]);

        $summary = $this->getJson("/api/v2/training-plans/{$this->plan->id}/monthly-summary?month=2026-06")
            ->assertOk()->json('data.summary');

        $this->assertSame('2026-06', $summary['month']);
        $this->assertSame(1, $summary['sessions_completed']);
        $this->assertSame(2, $summary['active_days']);
        $this->assertSame(4, $summary['total_sets']);
        $this->assertSame(40, $summary['total_reps']);
        // 10*60 + 8*70 + 12*20; the weightless set adds none.
        $this->assertEqualsWithDelta(1400.0, $summary['volume_kg'], 0.001);

        $byName = collect($summary['exercises'])->keyBy('name');
        $this->assertSame(3, $byName['Squat']['sets_done']);
        $this->assertEquals(70.0, $byName['Squat']['max_weight']);
        $this->assertEquals(20.0, $byName['Curl']['max_weight']);
        $this->assertSame('2026-06-01', $summary['sessions'][0]['date']);

        $this->assertSame(2, $summary['progress']['check_ins']);
        $this->assertEquals(80.0, $summary['progress']['first_weight']);
        $this->assertEquals(78.5, $summary['progress']['latest_weight']);
        $this->assertEquals(18.0, $summary['body_report']['fat_percent']);

        // The trainer reads the same roll-up from his own route.
        Sanctum::actingAs($this->trainer);
        $trainerView = $this->getJson("/api/v2/business/training-plans/{$this->plan->id}/monthly-summary?month=2026-06")
            ->assertOk()->json('data.summary');
        $this->assertSame($summary['volume_kg'], $trainerView['volume_kg']);

        // July has only its own set.
        $july = $this->getJson("/api/v2/business/training-plans/{$this->plan->id}/monthly-summary?month=2026-07")
            ->assertOk()->json('data.summary');
        $this->assertSame(1, $july['total_sets']);
        $this->assertSame([], $july['sessions']);
    }

    public function test_the_monthly_summary_is_private_to_the_two_parties(): void
    {
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'Stranger'));
        $this->getJson("/api/v2/training-plans/{$this->plan->id}/monthly-summary")->assertStatus(404);

        Sanctum::actingAs($this->user(User::TYPE_BUSINESS, 'OtherGym'));
        $this->getJson("/api/v2/business/training-plans/{$this->plan->id}/monthly-summary")->assertStatus(404);
        $this->getJson("/api/v2/business/training-plans/{$this->plan->id}/log?date=2026-06-01")->assertStatus(404);

        // The client has no trainer-side route at all.
        Sanctum::actingAs($this->client);
        $this->getJson("/api/v2/business/training-plans/{$this->plan->id}/monthly-summary")->assertStatus(403);
    }

    public function test_the_trainer_can_open_one_days_sets_against_the_prescription(): void
    {
        $squat = $this->exercise('Squat', 3, null, 60);
        Sanctum::actingAs($this->client);
        $this->done($squat, 10, 60);
        $this->done($squat, 9, 60);

        Sanctum::actingAs($this->trainer);
        $log = $this->getJson("/api/v2/business/training-plans/{$this->plan->id}/log?date=" . self::MONDAY)
            ->assertOk()->json('data.log');

        $this->assertFalse($log['completed']);
        $this->assertCount(1, $log['exercises']);
        $row = $log['exercises'][0];
        $this->assertSame('Squat', $row['name']);
        $this->assertSame(3, $row['target_sets']);
        $this->assertEquals(60.0, $row['target_weight']);
        $this->assertSame([10, 9], array_column($row['sets'], 'reps'));
    }

    public function test_today_s_sets_come_back_with_the_plan(): void
    {
        $squat = $this->exercise('Squat', 3);
        Sanctum::actingAs($this->client);
        $today = Carbon::today()->toDateString();
        $this->done($squat, 10, 60, $today);

        $exercise = $this->getJson("/api/v2/training-plans/{$this->plan->id}")->assertOk()->json('data.plan.exercises.0');

        $this->assertSame(1, $exercise['completed_rounds_today']);
        $this->assertSame(10, $exercise['today_rounds'][0]['reps']);
        $this->assertEquals(60.0, $exercise['today_rounds'][0]['weight']);
    }

    public function test_a_template_carries_the_target_weight_into_the_plan(): void
    {
        Sanctum::actingAs($this->trainer);
        $templateId = $this->postJson('/api/v2/business/training-templates', [
            'title' => 'T', 'exercises' => [['name' => 'Deadlift', 'sets' => 3, 'reps' => '5', 'target_weight' => 100]],
        ])->assertCreated()->json('data.template.id');

        $planId = $this->postJson("/api/v2/business/training-templates/{$templateId}/apply", ['client_id' => $this->client->id])
            ->assertCreated()->json('data.plan_id');

        $this->assertDatabaseHas('plan_exercises', ['training_plan_id' => $planId, 'name' => 'Deadlift', 'target_weight' => 100]);
    }
}
