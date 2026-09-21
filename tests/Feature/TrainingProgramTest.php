<?php

namespace Tests\Feature;

use App\Models\PlanExercise;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Support\ExerciseProgression;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «تحديد التمارين وعدد الأسابيع دفعة واحدة» — a Push / Pull / Legs programme is
 * defined ONCE, given a length in weeks, and the weights climb by a rule
 * (20-25-30 for two weeks, then +5, then +5 …). Nothing is stored per week:
 * every week's target is computed from the rule, so editing the rule re-plans
 * every later week at once.
 */
class TrainingProgramTest extends TestCase
{
    use DatabaseTransactions;

    private const START = '2026-06-01'; // a Monday

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

    private function exercise(array $attrs): PlanExercise
    {
        return (new PlanExercise($attrs + ['name' => 'X', 'sets' => 3]));
    }

    // ---------------------------------------------------------------- the maths

    public function test_the_weights_climb_by_the_rule_every_block_of_weeks(): void
    {
        // «20-25-30 for the first two weeks, then +5 kg» — the trainer's own example.
        $bench = $this->exercise(['set_weights' => [20, 25, 30], 'progress_every_weeks' => 2, 'progress_increment_kg' => 5]);

        $this->assertSame([20.0, 25.0, 30.0], ExerciseProgression::targetsFor($bench, 1));
        $this->assertSame([20.0, 25.0, 30.0], ExerciseProgression::targetsFor($bench, 2));
        $this->assertSame([25.0, 30.0, 35.0], ExerciseProgression::targetsFor($bench, 3));
        $this->assertSame([25.0, 30.0, 35.0], ExerciseProgression::targetsFor($bench, 4));
        $this->assertSame([30.0, 35.0, 40.0], ExerciseProgression::targetsFor($bench, 5));
    }

    public function test_the_increment_can_be_fractional_and_the_block_any_length(): void
    {
        $curl = $this->exercise(['target_weight' => 10, 'sets' => 2, 'progress_every_weeks' => 1, 'progress_increment_kg' => 2.5]);

        $this->assertSame([10.0, 10.0], ExerciseProgression::targetsFor($curl, 1));
        $this->assertSame([12.5, 12.5], ExerciseProgression::targetsFor($curl, 2));
        $this->assertSame([15.0, 15.0], ExerciseProgression::targetsFor($curl, 3));
    }

    public function test_edge_cases(): void
    {
        // No rule: the weights stay put. No weight at all: nothing to compute.
        $flat = $this->exercise(['target_weight' => 40]);
        $this->assertSame([40.0, 40.0, 40.0], ExerciseProgression::targetsFor($flat, 9));
        $this->assertSame([], ExerciseProgression::targetsFor($this->exercise([]), 3));

        // Fewer weights than sets: the last one carries on.
        $ramp = $this->exercise(['sets' => 4, 'set_weights' => [20, 30]]);
        $this->assertSame([20.0, 30.0, 30.0, 30.0], ExerciseProgression::targetsFor($ramp, 1));

        // Weights typed the way a trainer writes them.
        $this->assertSame([20.0, 25.0, 30.0], ExerciseProgression::parseWeights('20-25-30'));
        $this->assertSame([20.0, 25.0, 30.0], ExerciseProgression::parseWeights('20,25,30'));
        $this->assertSame([42.5], ExerciseProgression::parseWeights('42.5'));
        $this->assertSame([], ExerciseProgression::parseWeights(' '));
    }

    public function test_week_numbers_count_from_the_start_date(): void
    {
        $plan = new TrainingPlan(['starts_on' => self::START, 'duration_weeks' => 8]);
        $plan->starts_on = Carbon::parse(self::START);

        $week = fn (string $d) => ExerciseProgression::weekNumber($plan, Carbon::parse($d));

        $this->assertSame(1, $week('2026-06-01'));
        $this->assertSame(1, $week('2026-06-07'));
        $this->assertSame(2, $week('2026-06-08'));
        $this->assertSame(3, $week('2026-06-16'));
        $this->assertSame(1, $week('2026-05-20'), 'before the start it is week 1');
        $this->assertSame(8, ExerciseProgression::totalWeeks($plan));

        // Without duration_weeks the length comes from the two dates.
        $byDates = new TrainingPlan();
        $byDates->starts_on = Carbon::parse(self::START);
        $byDates->ends_on = Carbon::parse('2026-06-28');
        $this->assertSame(4, ExerciseProgression::totalWeeks($byDates));
        $this->assertNull(ExerciseProgression::totalWeeks(new TrainingPlan()));
    }

    // ------------------------------------------------- one call builds a programme

    private function pushPullLegs(array $extra = []): array
    {
        return $extra + [
            'client_id' => $this->client->id,
            'title' => 'Push Pull Legs',
            'starts_on' => self::START,
            'duration_weeks' => 8,
            // The default climb for every weighted exercise: every 2 weeks, +5 kg.
            'progression' => ['every_weeks' => 2, 'increment_kg' => 5],
            'exercises' => [
                ['name' => 'Bench press', 'day_of_week' => 1, 'day_label' => 'Push', 'sets' => 3, 'reps' => '8', 'set_weights' => [20, 25, 30]],
                ['name' => 'Row', 'day_of_week' => 3, 'day_label' => 'Pull', 'sets' => 3, 'reps' => '10', 'target_weight' => 40],
                ['name' => 'Squat', 'day_of_week' => 5, 'day_label' => 'Legs', 'sets' => 4, 'reps' => '6', 'set_weights' => [60, 70, 80, 90],
                    'progress_every_weeks' => 1, 'progress_increment_kg' => 2.5],
                ['name' => 'Plank', 'day_of_week' => 5, 'day_label' => 'Legs', 'sets' => 3],
            ],
        ];
    }

    public function test_the_whole_programme_is_created_in_one_call(): void
    {
        Sanctum::actingAs($this->trainer);

        $plan = $this->postJson('/api/v2/business/training-plans', $this->pushPullLegs())
            ->assertCreated()->json('data.plan');

        $this->assertSame(8, $plan['duration_weeks']);
        $this->assertSame(8, $plan['total_weeks']);
        // 8 weeks from Monday 1 June end on Sunday 26 July.
        $this->assertSame('2026-07-26', $plan['ends_on']);

        $byName = collect($plan['exercises'])->keyBy('name');

        // The plan-level default reaches the weighted exercises...
        $this->assertSame(2, $byName['Bench press']['progress_every_weeks']);
        $this->assertEquals(5, $byName['Bench press']['progress_increment_kg']);
        $this->assertSame(2, $byName['Row']['progress_every_weeks']);
        // ...an exercise's own rule wins...
        $this->assertSame(1, $byName['Squat']['progress_every_weeks']);
        $this->assertEquals(2.5, $byName['Squat']['progress_increment_kg']);
        // ...and a bodyweight exercise gets none.
        $this->assertNull($byName['Plank']['progress_every_weeks']);

        $this->assertSame('Push', $byName['Bench press']['day_label']);
        $this->assertSame('Legs', $byName['Plank']['day_label']);
        $this->assertSame([20, 25, 30], array_map('intval', $byName['Bench press']['set_weights']));

        // The trainer previews the whole climb, week by week.
        $schedule = $byName['Bench press']['schedule'];
        $this->assertCount(8, $schedule);
        $this->assertEquals([20, 25, 30], $schedule[0]['weights']);
        $this->assertEquals([25, 30, 35], $schedule[2]['weights']);
        $this->assertEquals([35, 40, 45], $schedule[6]['weights']);
        $this->assertSame([], $byName['Plank']['schedule'][0]['weights']);
    }

    public function test_the_trainee_sees_this_weeks_weights_and_the_climb_coming(): void
    {
        Sanctum::actingAs($this->trainer);
        $id = $this->postJson('/api/v2/business/training-plans', $this->pushPullLegs())->assertCreated()->json('data.plan.id');
        TrainingPlan::whereKey($id)->update(['status' => TrainingPlan::STATUS_ACTIVE]);

        Sanctum::actingAs($this->client);
        $bench = fn () => collect($this->getJson("/api/v2/training-plans/{$id}")->assertOk()->json('data.plan.exercises'))->firstWhere('name', 'Bench press');

        // Week 2: still 20-25-30, and next week steps up.
        $this->travelTo(Carbon::parse('2026-06-10 09:00'));
        $plan = $this->getJson("/api/v2/training-plans/{$id}")->json('data.plan');
        $this->assertSame(2, $plan['week_number']);
        $this->assertSame(8, $plan['total_weeks']);
        $this->assertEquals([20, 25, 30], $bench()['current_targets']);
        $this->assertEquals([25, 30, 35], $bench()['next_targets']);
        $this->assertSame('Push', $bench()['day_label']);

        // Week 3: the first step-up has arrived; next week is the same, so nothing is announced.
        $this->travelTo(Carbon::parse('2026-06-16 09:00'));
        $this->assertEquals([25, 30, 35], $bench()['current_targets']);
        $this->assertSame([], $bench()['next_targets']);

        // The last week has no "next".
        $this->travelTo(Carbon::parse('2026-07-22 09:00'));
        $this->assertSame([], $bench()['next_targets']);
    }

    public function test_a_finished_day_is_shown_against_that_weeks_targets(): void
    {
        Sanctum::actingAs($this->trainer);
        $id = $this->postJson('/api/v2/business/training-plans', $this->pushPullLegs())->assertCreated()->json('data.plan.id');
        $plan = TrainingPlan::findOrFail($id);
        $plan->update(['status' => TrainingPlan::STATUS_ACTIVE]);
        $bench = $plan->exercises()->where('name', 'Bench press')->firstOrFail();

        // A set logged in week 3 (Monday 15 June).
        \App\Models\PlanExerciseRound::create([
            'plan_exercise_id' => $bench->id, 'training_plan_id' => $plan->id, 'client_id' => $this->client->id,
            'for_date' => '2026-06-15', 'round_number' => 1, 'reps' => 8, 'weight' => 25, 'completed_at' => now(),
        ]);

        $log = $this->getJson("/api/v2/business/training-plans/{$id}/log?date=2026-06-15")->assertOk()->json('data.log');

        $this->assertSame(3, $log['week_number']);
        $this->assertEquals([25, 30, 35], $log['exercises'][0]['target_weights']);
    }

    // ------------------------------------------------------------- editing it

    public function test_the_programme_can_be_re_planned_for_the_whole_plan_at_once(): void
    {
        Sanctum::actingAs($this->trainer);
        $created = $this->postJson('/api/v2/business/training-plans', $this->pushPullLegs(['progression' => null]))
            ->assertCreated()->json('data.plan');
        $byName = collect($created['exercises'])->keyBy('name');
        $this->assertNull($byName['Bench press']['progress_every_weeks']);

        // Twelve weeks, and +2.5 kg every week — for every weighted exercise.
        $plan = $this->putJson("/api/v2/business/training-plans/{$created['id']}/program", [
            'duration_weeks' => 12,
            'progression' => ['every_weeks' => 1, 'increment_kg' => 2.5],
        ])->assertOk()->json('data.plan');

        $this->assertSame(12, $plan['duration_weeks']);
        // 12 weeks from 1 June end on Sunday 23 August.
        $this->assertSame('2026-08-23', $plan['ends_on']);

        $byName = collect($plan['exercises'])->keyBy('name');
        $this->assertSame(1, $byName['Bench press']['progress_every_weeks']);
        $this->assertEquals(2.5, $byName['Row']['progress_increment_kg']);
        $this->assertNull($byName['Plank']['progress_every_weeks'], 'bodyweight work is not given a weight climb');
        $this->assertCount(12, $byName['Bench press']['schedule']);
        $this->assertEquals([22.5, 27.5, 32.5], $byName['Bench press']['schedule'][1]['weights']);

        // Just some exercises: only those change.
        $benchId = $byName['Bench press']['id'];
        $only = $this->putJson("/api/v2/business/training-plans/{$created['id']}/program", [
            'progression' => ['every_weeks' => 3, 'increment_kg' => 5, 'exercise_ids' => [$benchId]],
        ])->assertOk()->json('data.plan');
        $only = collect($only['exercises'])->keyBy('name');
        $this->assertSame(3, $only['Bench press']['progress_every_weeks']);
        $this->assertSame(1, $only['Row']['progress_every_weeks']);
    }

    public function test_one_exercise_can_be_edited_and_cleared(): void
    {
        Sanctum::actingAs($this->trainer);
        $plan = $this->postJson('/api/v2/business/training-plans', $this->pushPullLegs())->assertCreated()->json('data.plan');
        $row = collect($plan['exercises'])->firstWhere('name', 'Row');
        $url = "/api/v2/business/training-plans/{$plan['id']}/exercises/{$row['id']}";

        $this->patchJson($url, ['set_weights' => [40, 45, 50], 'progress_increment_kg' => 2.5])
            ->assertOk()
            ->assertJsonPath('data.exercise.progress_increment_kg', 2.5)
            ->assertJsonPath('data.exercise.name', 'Row');

        // An explicit null clears the per-set weights; untouched fields stay.
        $cleared = $this->patchJson($url, ['set_weights' => null])->assertOk()->json('data.exercise');
        $this->assertNull($cleared['set_weights']);
        $this->assertEquals(40, $cleared['target_weight']);
        $this->assertSame('Pull', $cleared['day_label']);

        $this->patchJson($url, ['set_weights' => array_fill(0, 13, 10)])->assertStatus(422);
        $this->patchJson($url, ['progress_every_weeks' => 0])->assertStatus(422);
        $this->patchJson($url, ['progress_increment_kg' => -5])->assertStatus(422);
    }

    public function test_only_the_plans_trainer_can_change_the_programme(): void
    {
        Sanctum::actingAs($this->trainer);
        $plan = $this->postJson('/api/v2/business/training-plans', $this->pushPullLegs())->assertCreated()->json('data.plan');
        $exercise = $plan['exercises'][0]['id'];

        Sanctum::actingAs($this->client);
        $this->putJson("/api/v2/business/training-plans/{$plan['id']}/program", ['duration_weeks' => 2])->assertStatus(403);
        $this->patchJson("/api/v2/business/training-plans/{$plan['id']}/exercises/{$exercise}", ['sets' => 1])->assertStatus(403);

        Sanctum::actingAs($this->user(User::TYPE_BUSINESS, 'OtherGym'));
        $this->putJson("/api/v2/business/training-plans/{$plan['id']}/program", ['duration_weeks' => 2])->assertStatus(404);
        $this->patchJson("/api/v2/business/training-plans/{$plan['id']}/exercises/{$exercise}", ['sets' => 1])->assertStatus(404);
    }

    // ------------------------------------------------------------ via a template

    public function test_a_template_carries_the_programme_and_the_length_is_chosen_when_applying(): void
    {
        Sanctum::actingAs($this->trainer);
        $templateId = $this->postJson('/api/v2/business/training-templates', [
            'title' => 'PPL',
            'duration_weeks' => 6,
            'progression' => ['every_weeks' => 2, 'increment_kg' => 5],
            'exercises' => [
                ['name' => 'Bench press', 'day_of_week' => 1, 'day_label' => 'Push', 'sets' => 3, 'set_weights' => [20, 25, 30]],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.template.duration_weeks', 6)
            ->assertJsonPath('data.template.exercises.0.progress_every_weeks', 2)
            ->json('data.template.id');

        // Applied with no length: the template's own 6 weeks.
        $a = $this->postJson("/api/v2/business/training-templates/{$templateId}/apply", ['client_id' => $this->client->id, 'starts_on' => self::START])
            ->assertCreated()->json('data.plan_id');
        $planA = TrainingPlan::findOrFail($a);
        $this->assertSame(6, (int) $planA->duration_weeks);
        $this->assertSame('2026-07-12', $planA->ends_on->toDateString());

        // Applied for another client with 10 weeks chosen now.
        $b = $this->postJson("/api/v2/business/training-templates/{$templateId}/apply", [
            'client_id' => $this->user(User::TYPE_CLIENT, 'Second')->id, 'starts_on' => self::START, 'duration_weeks' => 10,
        ])->assertCreated()->json('data.plan_id');
        $this->assertSame(10, (int) TrainingPlan::findOrFail($b)->duration_weeks);

        // The rule came across with the exercise.
        $copy = PlanExercise::query()->where('training_plan_id', $b)->firstOrFail();
        $this->assertSame([20, 25, 30], array_map('intval', $copy->set_weights));
        $this->assertSame(2, (int) $copy->progress_every_weeks);
        $this->assertEquals(5, $copy->progress_increment_kg);
        $this->assertSame('Push', $copy->day_label);
    }

    public function test_adding_an_exercise_takes_the_programme_fields(): void
    {
        $this->travelTo(Carbon::parse(self::START . ' 09:00')); // week 1
        Sanctum::actingAs($this->trainer);
        $id = $this->postJson('/api/v2/business/training-plans', $this->pushPullLegs())->assertCreated()->json('data.plan.id');

        $this->postJson("/api/v2/business/training-plans/{$id}/exercises", [
            'name' => 'Deadlift', 'sets' => 3, 'reps' => '5', 'day_label' => 'Pull',
            'set_weights' => [80, 90, 100], 'progress_every_weeks' => 2, 'progress_increment_kg' => 5,
        ])->assertCreated()
            ->assertJsonPath('data.exercise.day_label', 'Pull')
            ->assertJsonPath('data.exercise.progress_every_weeks', 2)
            ->assertJsonPath('data.exercise.current_targets.0', 80);
    }
}
