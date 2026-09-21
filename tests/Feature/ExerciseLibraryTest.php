<?php

namespace Tests\Feature;

use App\Models\ExerciseCategory;
use App\Models\LibraryExercise;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The exercise catalogue a trainer picks from: sections + type + equipment,
 * and adding an exercise to a plan/template from it. Entries are created here,
 * never assumed, so the tests hold whatever the live catalogue contains.
 */
class ExerciseLibraryTest extends TestCase
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

    private function category(string $name = null): ExerciseCategory
    {
        return ExerciseCategory::create(['name_ar' => $name ?? 'قسم ' . uniqid(), 'name_en' => 'Section']);
    }

    private function entry(ExerciseCategory $c, string $ar, array $extra = []): LibraryExercise
    {
        return LibraryExercise::create($extra + [
            'exercise_category_id' => $c->id, 'name_ar' => $ar, 'name_en' => $ar . ' EN',
            'kind' => 'strength', 'equipment' => 'barbell', 'default_sets' => 4, 'default_reps' => '8-10',
        ]);
    }

    private function library(array $query = []): array
    {
        $url = '/api/v2/business/training/exercise-library' . ($query ? '?' . http_build_query($query) : '');

        return $this->getJson($url)->assertOk()->json('data');
    }

    public function test_the_library_lists_sections_kinds_equipment_and_exercises(): void
    {
        $cat = $this->category();
        $e = $this->entry($cat, 'تمرين ' . uniqid());

        Sanctum::actingAs($this->user(User::TYPE_BUSINESS, 'Gym'));
        $data = $this->library();

        $this->assertContains($cat->id, collect($data['categories'])->pluck('id')->all());
        $this->assertContains('cardio', collect($data['kinds'])->pluck('key')->all());
        $this->assertContains('dumbbell', collect($data['equipment'])->pluck('key')->all());

        $row = collect($data['exercises'])->firstWhere('id', $e->id);
        $this->assertSame($cat->id, $row['category_id']);
        $this->assertSame(4, $row['default_sets']);
        $this->assertSame('8-10', $row['default_reps']);
    }

    public function test_it_filters_by_section_kind_equipment_and_search(): void
    {
        $chest = $this->category();
        $legs = $this->category();
        $press = $this->entry($chest, 'ضغط ' . uniqid(), ['equipment' => 'dumbbell']);
        $run = $this->entry($legs, 'جري ' . uniqid(), ['kind' => 'cardio', 'equipment' => 'machine']);

        Sanctum::actingAs($this->user(User::TYPE_BUSINESS, 'Gym'));

        $ids = fn (array $q) => collect($this->library($q)['exercises'])->pluck('id');

        $this->assertEquals([$press->id], $ids(['category_id' => $chest->id])->all());
        $this->assertEquals([$run->id], $ids(['category_id' => $legs->id, 'kind' => 'cardio'])->all());
        $this->assertEquals([$press->id], $ids(['category_id' => $chest->id, 'equipment' => 'dumbbell'])->all());
        $this->assertEquals([], $ids(['category_id' => $chest->id, 'equipment' => 'cable'])->all());
        $this->assertEquals([$run->id], $ids(['q' => $run->name_ar])->all());
    }

    public function test_retired_entries_and_sections_are_not_offered(): void
    {
        $live = $this->category();
        $retired = $this->category();
        $retired->update(['is_active' => false]);
        $off = $this->entry($live, 'مخفي ' . uniqid(), ['is_active' => false]);
        $inRetiredSection = $this->entry($retired, 'داخل قسم مخفي ' . uniqid());

        Sanctum::actingAs($this->user(User::TYPE_BUSINESS, 'Gym'));
        $data = $this->library();

        $ids = collect($data['exercises'])->pluck('id');
        $this->assertFalse($ids->contains($off->id));
        $this->assertFalse($ids->contains($inRetiredSection->id));
        $this->assertFalse(collect($data['categories'])->pluck('id')->contains($retired->id));
    }

    public function test_a_client_account_cannot_read_the_trainers_library(): void
    {
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'Client'));

        $this->getJson('/api/v2/business/training/exercise-library')->assertStatus(403);
    }

    public function test_adding_from_the_library_fills_name_sets_and_reps(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $plan = TrainingPlan::create([
            'trainer_id' => $trainer->id, 'client_id' => $this->user(User::TYPE_CLIENT, 'C')->id,
            'title' => 'Plan', 'status' => TrainingPlan::STATUS_ACTIVE,
        ]);
        $entry = $this->entry($this->category(), 'سكوات ' . uniqid());

        Sanctum::actingAs($trainer);

        $this->postJson("/api/v2/business/training-plans/{$plan->id}/exercises", [
            'library_exercise_id' => $entry->id,
        ])->assertCreated()
            ->assertJsonPath('data.exercise.name', $entry->name_ar)
            ->assertJsonPath('data.exercise.sets', 4)
            ->assertJsonPath('data.exercise.reps', '8-10')
            ->assertJsonPath('data.exercise.library_exercise_id', $entry->id);

        // Explicit values win over the suggestion.
        $this->postJson("/api/v2/business/training-plans/{$plan->id}/exercises", [
            'library_exercise_id' => $entry->id, 'name' => 'سكوات ثقيل', 'sets' => 6, 'reps' => '5',
        ])->assertCreated()
            ->assertJsonPath('data.exercise.name', 'سكوات ثقيل')
            ->assertJsonPath('data.exercise.sets', 6)
            ->assertJsonPath('data.exercise.reps', '5');
    }

    public function test_a_plan_keeps_its_exercise_when_the_catalogue_entry_is_deleted(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $plan = TrainingPlan::create([
            'trainer_id' => $trainer->id, 'client_id' => $this->user(User::TYPE_CLIENT, 'C')->id,
            'title' => 'Plan', 'status' => TrainingPlan::STATUS_ACTIVE,
        ]);
        $entry = $this->entry($this->category(), 'محذوف ' . uniqid());

        Sanctum::actingAs($trainer);
        $id = $this->postJson("/api/v2/business/training-plans/{$plan->id}/exercises", ['library_exercise_id' => $entry->id])
            ->assertCreated()->json('data.exercise.id');

        $entry->delete();

        $row = $plan->exercises()->find($id);
        $this->assertNotNull($row, 'the plan must not lose an exercise when the catalogue changes');
        $this->assertNull($row->library_exercise_id);
        $this->assertSame($entry->name_ar, $row->name);
    }

    public function test_an_exercise_needs_a_name_or_a_library_pick(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $plan = TrainingPlan::create([
            'trainer_id' => $trainer->id, 'client_id' => $this->user(User::TYPE_CLIENT, 'C')->id,
            'title' => 'Plan', 'status' => TrainingPlan::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($trainer);

        $this->postJson("/api/v2/business/training-plans/{$plan->id}/exercises", ['sets' => 3])
            ->assertStatus(422)->assertJsonValidationErrors(['name']);
        $this->postJson("/api/v2/business/training-plans/{$plan->id}/exercises", ['library_exercise_id' => 999999999])
            ->assertStatus(422);
        $this->postJson("/api/v2/business/training-plans/{$plan->id}/exercises", ['name' => 'Free text'])
            ->assertCreated()->assertJsonPath('data.exercise.library_exercise_id', null);
    }

    public function test_templates_accept_a_library_pick_too(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        Sanctum::actingAs($trainer);
        $templateId = $this->postJson('/api/v2/business/training-templates', ['title' => 'T'])
            ->assertCreated()->json('data.template.id');
        $entry = $this->entry($this->category(), 'قالب ' . uniqid());

        $this->postJson("/api/v2/business/training-templates/{$templateId}/exercises", ['library_exercise_id' => $entry->id])
            ->assertCreated()
            ->assertJsonPath('data.exercise.name', $entry->name_ar)
            ->assertJsonPath('data.exercise.library_exercise_id', $entry->id);
    }

    public function test_the_admin_can_curate_sections_and_exercises(): void
    {
        $admin = User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin account to act as.');
        }

        $catName = 'قسم اختبار ' . uniqid();
        $exName = 'تمرين اختبار ' . uniqid();

        $this->actingAs($admin)->post(route('admin.exercise-library.categories.store'), ['name_ar' => $catName])->assertRedirect();
        $cat = ExerciseCategory::where('name_ar', $catName)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.exercise-library.exercises.store'), [
            'exercise_category_id' => $cat->id, 'name_ar' => $exName, 'kind' => 'cardio', 'equipment' => 'machine',
            'default_sets' => 2, 'default_reps' => '10',
        ])->assertRedirect();
        $ex = LibraryExercise::where('name_ar', $exName)->firstOrFail();
        $this->assertSame('cardio', $ex->kind);

        $this->actingAs($admin)->get(route('admin.exercise-library.index', ['q' => $exName]))
            ->assertOk()->assertSee($exName, false);

        $this->actingAs($admin)->put(route('admin.exercise-library.exercises.update', $ex), [
            'exercise_category_id' => $cat->id, 'name_ar' => $exName . ' 2', 'kind' => 'strength',
        ])->assertRedirect();
        $this->assertFalse((bool) $ex->fresh()->is_active, 'an unchecked box retires the exercise');
        $this->assertSame($exName . ' 2', $ex->fresh()->name_ar);

        $this->actingAs($admin)->delete(route('admin.exercise-library.categories.destroy', $cat))
            ->assertSessionHasErrors('category');
        $this->assertNotNull(ExerciseCategory::find($cat->id), 'a section with exercises must not be deletable');

        $this->actingAs($admin)->delete(route('admin.exercise-library.exercises.destroy', $ex))->assertRedirect();
        $this->actingAs($admin)->delete(route('admin.exercise-library.categories.destroy', $cat))->assertRedirect();
        $this->assertNull(ExerciseCategory::find($cat->id));
    }

    /** A real 1x1 PNG, so getimagesizefromstring accepts it. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function mapFile(array $map): string
    {
        $path = tempnam(sys_get_temp_dir(), 'exmap') . '.php';
        file_put_contents($path, '<?php return ' . var_export($map, true) . ';');

        return $path;
    }

    public function test_the_import_command_downloads_and_attaches_photos_and_they_die_with_the_exercise(): void
    {
        $name = 'Zz Test Press ' . uniqid();
        $entry = $this->entry($this->category(), 'اختبار ' . uniqid(), ['name_en' => $name]);
        \Illuminate\Support\Facades\Http::fake(['raw.githubusercontent.com/*' => \Illuminate\Support\Facades\Http::response(base64_decode(self::PNG), 200)]);

        $this->artisan('exercise-library:import-images', ['--map' => $this->mapFile([$name => 'Some_Folder'])])
            ->assertSuccessful();

        $paths = $entry->fresh()->images->pluck('image')->all();
        $this->assertCount(2, $paths);
        foreach ($paths as $p) {
            $this->assertFileExists(public_path($p));
        }

        // Idempotent: a second run leaves an exercise that has photos alone.
        $this->artisan('exercise-library:import-images', ['--map' => $this->mapFile([$name => 'Some_Folder'])])->assertSuccessful();
        $this->assertCount(2, $entry->fresh()->images);

        // Rows AND files go with the exercise.
        $entry->delete();
        foreach ($paths as $p) {
            $this->assertFileDoesNotExist(public_path($p));
        }
        $this->assertSame(0, \App\Models\Image::query()->whereIn('image', $paths)->count());
    }

    public function test_the_import_command_stores_nothing_when_the_download_is_not_a_picture(): void
    {
        $name = 'Zz Bad ' . uniqid();
        $entry = $this->entry($this->category(), 'اختبار ' . uniqid(), ['name_en' => $name]);
        \Illuminate\Support\Facades\Http::fake(['raw.githubusercontent.com/*' => \Illuminate\Support\Facades\Http::response('<html>not found</html>', 200)]);

        $this->artisan('exercise-library:import-images', ['--map' => $this->mapFile([$name => 'Some_Folder'])])
            ->assertFailed();

        $this->assertCount(0, $entry->fresh()->images);
    }

    public function test_the_library_and_the_plan_expose_the_demonstration_photos(): void
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');
        $plan = TrainingPlan::create([
            'trainer_id' => $trainer->id, 'client_id' => $client->id,
            'title' => 'Plan', 'status' => TrainingPlan::STATUS_ACTIVE,
        ]);
        $entry = $this->entry($this->category(), 'مصوّر ' . uniqid());
        \App\Models\Image::create(['image' => 'files/uploads/exercise-library/x-0.jpg', 'imageable_id' => $entry->id, 'imageable_type' => $entry->getMorphClass(), 'source' => 'upload']);

        Sanctum::actingAs($trainer);
        $row = collect($this->library()['exercises'])->firstWhere('id', $entry->id);
        $this->assertSame(['files/uploads/exercise-library/x-0.jpg'], $row['images']);

        $this->postJson("/api/v2/business/training-plans/{$plan->id}/exercises", ['library_exercise_id' => $entry->id])->assertCreated();
        // A free-text exercise carries no library photos.
        $this->postJson("/api/v2/business/training-plans/{$plan->id}/exercises", ['name' => 'Free'])->assertCreated();

        $byName = fn (array $exercises) => collect($exercises)->keyBy('name');

        $trainerView = $this->getJson("/api/v2/business/training-plans/{$plan->id}")->assertOk()->json('data.plan.exercises');
        $this->assertSame(['files/uploads/exercise-library/x-0.jpg'], $byName($trainerView)[$entry->name_ar]['library_images']);
        $this->assertSame([], $byName($trainerView)['Free']['library_images']);

        Sanctum::actingAs($client);
        $clientView = $this->getJson("/api/v2/training-plans/{$plan->id}")->assertOk()->json('data.plan.exercises');
        $this->assertSame(['files/uploads/exercise-library/x-0.jpg'], $byName($clientView)[$entry->name_ar]['library_images']);
        $this->assertSame([], $byName($clientView)['Free']['library_images']);
    }
}
