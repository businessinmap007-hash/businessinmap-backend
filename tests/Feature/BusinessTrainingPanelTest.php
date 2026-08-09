<?php

namespace Tests\Feature;

use App\Models\BodyCompositionReport;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «اين يمكن انشاء خطة التدريب والنظام الغذائي للعميل» — owner, 2026-08-09.
 *
 * Nowhere, was the answer: the plans, the API and the admin's read-only
 * oversight all existed, but a gym owner at a desk had no screen to WRITE one.
 * These guard the panel that closes that, and above all the scoping — a plan is
 * two people's private business and the panel is the easiest place to leak it.
 */
class BusinessTrainingPanelTest extends TestCase
{
    use DatabaseTransactions;

    private User $trainer;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trainer = $this->user(User::TYPE_BUSINESS, 'جيم اللوحة');
        $this->client = $this->user(User::TYPE_CLIENT, 'متدرب اللوحة');
    }

    /** The screen exists, and the plan is created from it. */
    public function test_the_trainer_creates_a_plan_from_the_panel(): void
    {
        $this->actingAs($this->trainer)
            ->get(route('business.training-plans.create'))
            ->assertOk();

        $this->actingAs($this->trainer)
            ->post(route('business.training-plans.store'), [
                'client_id' => $this->client->id,
                'title' => 'خطة اللوحة',
                'goal' => 'خسارة دهون',
            ])
            ->assertRedirect();

        $plan = TrainingPlan::query()->where('trainer_id', $this->trainer->id)->latest('id')->first();

        $this->assertNotNull($plan);
        $this->assertSame('خطة اللوحة', $plan->title);
        $this->assertSame((int) $this->client->id, (int) $plan->client_id);
    }

    /** Exercises and meals are written from the same screen. */
    public function test_exercises_and_meals_are_added_from_the_plan_screen(): void
    {
        $plan = $this->plan();

        $this->actingAs($this->trainer)
            ->post(route('business.training-plans.exercises.store', $plan->id), [
                'name' => 'ضغط بنش', 'sets' => 4, 'reps' => '12', 'day_of_week' => 1,
            ])->assertRedirect();

        $this->actingAs($this->trainer)
            ->post(route('business.training-plans.meals.store', $plan->id), [
                'meal_type' => 'breakfast', 'name' => 'شوفان وبيض', 'calories' => 450,
            ])->assertRedirect();

        $this->assertSame(1, $plan->exercises()->count());
        $this->assertSame(1, $plan->meals()->count());

        $this->actingAs($this->trainer)
            ->get(route('business.training-plans.show', $plan->id))
            ->assertOk()
            ->assertSee('ضغط بنش')
            ->assertSee('شوفان وبيض');
    }

    /** The monthly measurement, recorded from the desk. */
    public function test_the_monthly_report_is_recorded_and_shows_its_change(): void
    {
        $plan = $this->plan();

        foreach ([['2026-03-04', 38.0, 28.0], ['2026-04-04', 39.5, 25.5]] as [$month, $muscle, $fat]) {
            $this->actingAs($this->trainer)
                ->post(route('business.training-plans.body-reports.store', $plan->id), [
                    'for_month' => $month, 'muscle_mass_kg' => $muscle, 'fat_percent' => $fat,
                ])->assertRedirect();
        }

        $this->assertSame(2, BodyCompositionReport::query()->where('training_plan_id', $plan->id)->count());

        // +1.5 muscle and -2.5 fat, both stated on the April row.
        $this->actingAs($this->trainer)
            ->get(route('business.training-plans.show', $plan->id))
            ->assertOk()
            ->assertSee('+1.5')
            ->assertSee('-2.5');
    }

    /** A report of nothing would occupy the month and read as «measured, zero». */
    public function test_an_empty_report_is_refused(): void
    {
        $plan = $this->plan();

        $this->actingAs($this->trainer)
            ->post(route('business.training-plans.body-reports.store', $plan->id), [
                'for_month' => '2026-03-01', 'notes' => 'نسيت الميزان',
            ])
            ->assertSessionHasErrors('weight_kg');

        $this->assertSame(0, BodyCompositionReport::query()->where('training_plan_id', $plan->id)->count());
    }

    /** Another gym's plan does not exist as far as this panel is concerned. */
    public function test_another_trainers_plan_is_invisible(): void
    {
        $plan = $this->plan();
        $otherGym = $this->user(User::TYPE_BUSINESS, 'جيم آخر');

        $this->actingAs($otherGym)
            ->get(route('business.training-plans.show', $plan->id))
            ->assertNotFound();

        $this->actingAs($otherGym)
            ->post(route('business.training-plans.exercises.store', $plan->id), ['name' => 'تمرين مدسوس'])
            ->assertNotFound();

        $this->assertSame(0, $plan->exercises()->count());
    }

    /** The picker matches a phone exactly — it is not a people-finder. */
    public function test_the_client_lookup_matches_an_exact_phone(): void
    {
        $this->actingAs($this->trainer)
            ->getJson(route('business.training-plans.lookup', ['q' => $this->client->phone]))
            ->assertOk()
            ->assertJson(['found' => true, 'id' => (int) $this->client->id]);

        $this->actingAs($this->trainer)
            ->getJson(route('business.training-plans.lookup', ['q' => substr((string) $this->client->phone, 0, 5)]))
            ->assertOk()
            ->assertJson(['found' => false]);
    }

    /** Deleting an exercise must fire the model event that owns its photos. */
    public function test_deleting_an_exercise_goes_through_the_model(): void
    {
        $plan = $this->plan();
        $exercise = $plan->exercises()->create(['name' => 'سحب أرضي', 'sort_order' => 1]);

        $this->actingAs($this->trainer)
            ->delete(route('business.training-plans.exercises.destroy', [$plan->id, $exercise->id]))
            ->assertRedirect();

        $this->assertSame(0, $plan->exercises()->count());
    }

    private function plan(): TrainingPlan
    {
        return TrainingPlan::create([
            'trainer_id' => $this->trainer->id,
            'client_id' => $this->client->id,
            'title' => 'خطة اللوحة',
            'status' => TrainingPlan::STATUS_ACTIVE,
        ]);
    }

    private function user(string $type, string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => 'panel' . uniqid() . '@example.test',
            'password' => bcrypt('Test1234'),
            'type' => $type,
            'api_token' => Str::random(60),
            'phone' => '010' . random_int(10000000, 99999999),
        ]);
    }
}
