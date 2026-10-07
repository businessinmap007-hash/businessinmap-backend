<?php

namespace Tests\Feature;

use App\Models\ExerciseCategory;
use App\Models\FoodCategory;
use App\Models\FoodItem;
use App\Models\LibraryExercise;
use App\Models\TrainingPlan;
use App\Models\User;
use Database\Seeders\FoodLibrarySeeder;
use Database\Seeders\TherapeuticExercisesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «اضف جدول التغذية وجدول التمارين العلاجية واضف بهم كل الخيارات ليختار منها المتخصص مباشرة الاكل او التمرين مع امكانية
 * اضافة من المتخصص لنوع غذاء او تمرين» — المالك، 2026-10-08.
 *
 * Rolls back.
 */
class FoodAndTherapeuticLibraryTest extends TestCase
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

    private function plan(User $trainer): TrainingPlan
    {
        return TrainingPlan::create([
            'trainer_id' => $trainer->id, 'client_id' => $this->user(User::TYPE_CLIENT, 'C')->id,
            'title' => 'Plan', 'status' => TrainingPlan::STATUS_ACTIVE,
        ]);
    }

    public function test_the_seeders_fill_the_tables_and_a_second_run_changes_nothing(): void
    {
        $this->seed(FoodLibrarySeeder::class);
        $this->seed(TherapeuticExercisesSeeder::class);

        $foods = FoodItem::query()->whereNull('owner_id')->count();
        $exercises = LibraryExercise::query()->whereNull('owner_id')->where('kind', 'rehab')->count();

        $this->assertGreaterThan(100, $foods);
        $this->assertGreaterThan(60, $exercises);

        $this->seed(FoodLibrarySeeder::class);
        $this->seed(TherapeuticExercisesSeeder::class);

        $this->assertSame($foods, FoodItem::query()->whereNull('owner_id')->count());
        $this->assertSame($exercises, LibraryExercise::query()->whereNull('owner_id')->where('kind', 'rehab')->count());

        $bread = FoodItem::query()->where('name_ar', 'رغيف عيش بلدي')->firstOrFail();
        $this->assertSame(245, $bread->calories, 'the numbers are for the serving the label names');
        $this->assertStringContainsString('90', $bread->serving_label);
    }

    public function test_a_specialist_sees_the_shared_foods_and_their_own_never_anothers(): void
    {
        $this->seed(FoodLibrarySeeder::class);
        $mine = $this->user(User::TYPE_BUSINESS, 'Nutri');
        $other = $this->user(User::TYPE_BUSINESS, 'Other');
        $cat = FoodCategory::query()->firstOrFail();

        Sanctum::actingAs($other);
        $theirs = $this->postJson('/api/v2/business/training/food-library', [
            'food_category_id' => $cat->id, 'name_ar' => 'صنف غيري ' . uniqid(), 'serving_label' => 'حصة', 'calories' => 100,
        ])->assertCreated()->json('data.food.id');

        Sanctum::actingAs($mine);
        $created = $this->postJson('/api/v2/business/training/food-library', [
            'food_category_id' => $cat->id, 'name_ar' => 'كشري أمي ' . uniqid(), 'serving_label' => 'طبق (300 جم)',
            'serving_grams' => 300, 'calories' => 480, 'protein_g' => 14, 'carbs_g' => 90, 'fat_g' => 8,
        ])->assertCreated()->assertJsonPath('data.food.mine', true)->json('data.food.id');

        $list = $this->getJson('/api/v2/business/training/food-library')->assertOk()->json('data');
        $ids = collect($list['foods'])->pluck('id');

        $this->assertTrue($ids->contains($created));
        $this->assertFalse($ids->contains($theirs), 'another specialist\'s own food is never offered');
        $this->assertTrue(collect($list['foods'])->contains(fn ($f) => $f['mine'] === false), 'the shared catalogue is there');
        $this->assertNotEmpty($list['categories']);

        // search
        $found = $this->getJson('/api/v2/business/training/food-library?q=' . urlencode('رغيف'))->assertOk()->json('data.foods');
        $this->assertNotEmpty($found);
        $this->assertLessThan(count($list['foods']), count($found), 'the search narrows the list');
        $this->assertTrue(collect($found)->every(fn ($f) => $f['name'] !== ''));
    }

    public function test_a_specialist_corrects_and_deletes_only_their_own_food(): void
    {
        $this->seed(FoodLibrarySeeder::class);
        $me = $this->user(User::TYPE_BUSINESS, 'Nutri');
        $cat = FoodCategory::query()->firstOrFail();
        $shared = FoodItem::query()->whereNull('owner_id')->firstOrFail();

        Sanctum::actingAs($me);
        $id = $this->postJson('/api/v2/business/training/food-library', [
            'food_category_id' => $cat->id, 'name_ar' => 'صنفي', 'serving_label' => 'حصة', 'calories' => 100,
        ])->assertCreated()->json('data.food.id');

        $this->putJson("/api/v2/business/training/food-library/{$id}", [
            'food_category_id' => $cat->id, 'name_ar' => 'صنفي بعد التصحيح', 'serving_label' => 'حصة', 'calories' => 120,
        ])->assertOk()->assertJsonPath('data.food.calories', 120);

        // the shared catalogue is read-only from the app
        $this->putJson("/api/v2/business/training/food-library/{$shared->id}", [
            'food_category_id' => $cat->id, 'name_ar' => 'تخريب', 'serving_label' => 'حصة', 'calories' => 1,
        ])->assertNotFound();
        $this->deleteJson("/api/v2/business/training/food-library/{$shared->id}")->assertNotFound();
        $this->assertSame($shared->name_ar, $shared->fresh()->name_ar);

        $this->deleteJson("/api/v2/business/training/food-library/{$id}")->assertOk();
        $this->assertNull(FoodItem::query()->find($id));

        // a client account has no library at all
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'Client'));
        $this->getJson('/api/v2/business/training/food-library')->assertStatus(403);
    }

    public function test_a_meal_picked_from_the_catalogue_takes_its_numbers_from_the_catalogue(): void
    {
        $this->seed(FoodLibrarySeeder::class);
        $trainer = $this->user(User::TYPE_BUSINESS, 'Nutri');
        $plan = $this->plan($trainer);
        $bread = FoodItem::query()->where('name_ar', 'رغيف عيش بلدي')->firstOrFail();

        Sanctum::actingAs($trainer);

        // the client cannot lower the calories: they are the food's × the servings
        $this->postJson("/api/v2/business/training-plans/{$plan->id}/meals", [
            'meal_type' => 'breakfast', 'food_id' => $bread->id, 'servings' => 2, 'calories' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.meal.name', 'رغيف عيش بلدي')
            ->assertJsonPath('data.meal.calories', 490)
            ->assertJsonPath('data.meal.food_id', $bread->id)
            ->assertJsonPath('data.meal.servings', 2);

        // a meal still needs a food or a name
        $this->postJson("/api/v2/business/training-plans/{$plan->id}/meals", ['meal_type' => 'lunch'])
            ->assertStatus(422)->assertJsonValidationErrors(['name']);
        $this->postJson("/api/v2/business/training-plans/{$plan->id}/meals", ['meal_type' => 'lunch', 'food_id' => 999999999])
            ->assertStatus(422);

        // typing one is still possible
        $this->postJson("/api/v2/business/training-plans/{$plan->id}/meals", ['meal_type' => 'lunch', 'name' => 'وجبة حرة', 'calories' => 300])
            ->assertCreated()->assertJsonPath('data.meal.food_id', null)->assertJsonPath('data.meal.calories', 300);
    }

    public function test_a_food_of_another_specialist_cannot_be_put_in_my_plan(): void
    {
        $this->seed(FoodLibrarySeeder::class);
        $owner = $this->user(User::TYPE_BUSINESS, 'Owner');
        $me = $this->user(User::TYPE_BUSINESS, 'Me');
        $cat = FoodCategory::query()->firstOrFail();
        $private = FoodItem::query()->create([
            'food_category_id' => $cat->id, 'owner_id' => $owner->id, 'name_ar' => 'خاص', 'serving_label' => 'حصة', 'calories' => 50, 'is_active' => true,
        ]);

        Sanctum::actingAs($me);
        $this->postJson("/api/v2/business/training-plans/{$this->plan($me)->id}/meals", ['meal_type' => 'snack', 'food_id' => $private->id])
            ->assertStatus(422);
    }

    public function test_a_template_meal_picks_from_the_catalogue_too(): void
    {
        $this->seed(FoodLibrarySeeder::class);
        Sanctum::actingAs($this->user(User::TYPE_BUSINESS, 'Nutri'));
        $templateId = $this->postJson('/api/v2/business/training-templates', ['title' => 'T'])->assertCreated()->json('data.template.id');
        $egg = FoodItem::query()->where('name_ar', 'بيضة مسلوقة')->firstOrFail();

        $this->postJson("/api/v2/business/training-templates/{$templateId}/meals", ['meal_type' => 'breakfast', 'food_id' => $egg->id, 'servings' => 3])
            ->assertCreated()->assertJsonPath('data.meal.calories', 234)->assertJsonPath('data.meal.name', 'بيضة مسلوقة');
    }

    public function test_the_rehab_exercises_are_offered_and_a_pick_copies_the_instructions(): void
    {
        $this->seed(TherapeuticExercisesSeeder::class);
        $trainer = $this->user(User::TYPE_BUSINESS, 'Physio');
        $plan = $this->plan($trainer);
        $entry = LibraryExercise::query()->where('name_ar', 'جسر الأرداف')->firstOrFail();

        Sanctum::actingAs($trainer);
        $rehab = collect($this->getJson('/api/v2/business/training/exercise-library?kind=rehab')->assertOk()->json('data.exercises'));
        $this->assertGreaterThan(60, $rehab->count());
        $this->assertContains('rehab', collect($this->getJson('/api/v2/business/training/exercise-library')->json('data.kinds'))->pluck('key')->all());

        $this->postJson("/api/v2/business/training-plans/{$plan->id}/exercises", ['library_exercise_id' => $entry->id])
            ->assertCreated()
            ->assertJsonPath('data.exercise.name', 'جسر الأرداف')
            ->assertJsonPath('data.exercise.sets', 3)
            ->assertJsonPath('data.exercise.notes', $entry->instructions);
    }

    public function test_a_specialist_adds_their_own_exercise_and_only_they_can_use_it(): void
    {
        $category = ExerciseCategory::query()->firstOrFail();
        $me = $this->user(User::TYPE_BUSINESS, 'Physio');
        $other = $this->user(User::TYPE_BUSINESS, 'Other');

        Sanctum::actingAs($me);
        $id = $this->postJson('/api/v2/business/training/exercise-library', [
            'exercise_category_id' => $category->id, 'name_ar' => 'تمريني الخاص', 'default_sets' => 2, 'default_reps' => '15', 'instructions' => 'ببطء',
        ])->assertCreated()->assertJsonPath('data.exercise.mine', true)->assertJsonPath('data.exercise.kind', 'rehab')->json('data.exercise.id');

        $plan = $this->plan($me);
        $this->postJson("/api/v2/business/training-plans/{$plan->id}/exercises", ['library_exercise_id' => $id])
            ->assertCreated()->assertJsonPath('data.exercise.name', 'تمريني الخاص')->assertJsonPath('data.exercise.notes', 'ببطء');

        // an edit that does not name the kind keeps it
        $this->putJson("/api/v2/business/training/exercise-library/{$id}", ['exercise_category_id' => $category->id, 'name_ar' => 'تمريني بعد التصحيح'])
            ->assertOk()->assertJsonPath('data.exercise.kind', 'rehab');

        // another specialist neither sees it, nor edits it, nor puts it in a plan
        Sanctum::actingAs($other);
        $this->assertFalse(collect($this->getJson('/api/v2/business/training/exercise-library')->json('data.exercises'))->pluck('id')->contains($id));
        $this->putJson("/api/v2/business/training/exercise-library/{$id}", ['exercise_category_id' => $category->id, 'name_ar' => 'سرقة'])->assertNotFound();
        $this->deleteJson("/api/v2/business/training/exercise-library/{$id}")->assertNotFound();
        $this->postJson("/api/v2/business/training-plans/{$this->plan($other)->id}/exercises", ['library_exercise_id' => $id])->assertStatus(422);

        // the owner deletes it; the plan keeps its copy
        Sanctum::actingAs($me);
        $this->deleteJson("/api/v2/business/training/exercise-library/{$id}")->assertOk();
        $this->assertSame('تمريني الخاص', $plan->exercises()->first()->name);
    }
}
