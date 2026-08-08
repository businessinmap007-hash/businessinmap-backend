<?php

namespace Tests\Feature;

use App\Models\PlanExercise;
use App\Models\PlanMeal;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «قم باستكمال الخطة التدريبية والنظام الغذائي ويكون مرتبط بين العميل والكابتن
 * فقط لا يمكن الظهر لاى مشترك اخر ولا يمكن اضافة صور من العميل الصور تكون
 * توضيحية فقط من الكابتن للجهاز مثلا او شكل التمرين» — owner, 2026-08-08.
 *
 * Two rules, and they pull in opposite directions, which is why both are
 * tested rather than assumed:
 *
 *   - the plan is private to the TWO parties, and a third subscriber gets
 *     nothing — not the plan, not a photo, not a 403 that confirms it exists;
 *   - the photos are illustrative and one-way. The captain shows the machine
 *     and the grip; the client reads them and cannot add one. That is enforced
 *     by there being no client-side route that writes an image at all.
 */
class TrainingPlanImagesTest extends TestCase
{
    use DatabaseTransactions;

    private User $trainer;

    private User $client;

    private TrainingPlan $plan;

    private PlanExercise $exercise;

    private PlanMeal $meal;

    /** @var array<int,string> */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->trainer = $this->user(User::TYPE_BUSINESS, 'كابتن الاختبار');
        $this->client = $this->user(User::TYPE_CLIENT, 'متدرب');

        $this->plan = TrainingPlan::create([
            'trainer_id' => $this->trainer->id,
            'client_id' => $this->client->id,
            'title' => 'خطة تجريبية',
            'status' => TrainingPlan::STATUS_ACTIVE,
        ]);

        $this->exercise = $this->plan->exercises()->create([
            'name' => 'سكوات',
            'day_of_week' => 1,
            'sets' => 4,
            'reps' => '12',
        ]);

        $this->meal = $this->plan->meals()->create([
            'meal_type' => 'breakfast',
            'name' => 'شوفان بالموز',
            'calories' => 400,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    /** The captain illustrates the movement, and the trainee sees it. */
    public function test_the_captain_attaches_a_photo_and_the_client_reads_it(): void
    {
        $image = $this->attachToExercise();
        $this->track($image['image']);

        $exercises = $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/v2/training-plans/{$this->plan->id}")
            ->assertOk()
            ->json('data.plan.exercises');

        $this->assertCount(1, $exercises[0]['images']);
        $this->assertSame($image['image'], $exercises[0]['images'][0]['image']);
    }

    /** The nutrition half too — «الخطة التدريبية والنظام الغذائي». */
    public function test_a_meal_carries_its_own_picture(): void
    {
        $image = $this->actingAs($this->trainer, 'sanctum')
            ->postJson("/api/v2/business/training-plans/{$this->plan->id}/meals/{$this->meal->id}/images", [
                'images' => [$this->file('meal.png')],
            ])
            ->assertStatus(201)
            ->json('data.images.0');

        $this->track($image['image']);

        $meals = $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/v2/training-plans/{$this->plan->id}")
            ->assertOk()
            ->json('data.plan.meals');

        $this->assertCount(1, $meals[0]['images']);
    }

    /**
     * The one-way rule. Not «the client's upload is rejected» — there is no
     * upload for the client to make, on either half of the plan.
     */
    public function test_the_client_has_no_way_to_add_a_photo(): void
    {
        foreach ([
            "/api/v2/training-plans/{$this->plan->id}/exercises/{$this->exercise->id}/images",
            "/api/v2/training-plans/{$this->plan->id}/meals/{$this->meal->id}/images",
        ] as $url) {
            $this->actingAs($this->client, 'sanctum')
                ->postJson($url, ['images' => [$this->file('mine.png')]])
                ->assertStatus(404);
        }

        // And the trainer's own route refuses him too — he is not the trainer.
        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/business/training-plans/{$this->plan->id}/exercises/{$this->exercise->id}/images", [
                'images' => [$this->file('mine.png')],
            ])
            ->assertStatus(403);
    }

    /**
     * A third subscriber sees nothing — and gets a 404, not a 403: a 403 would
     * confirm that this client trains with this captain.
     */
    public function test_a_third_party_reaches_neither_the_plan_nor_its_photos(): void
    {
        $this->track($this->attachToExercise()['image']);

        $stranger = $this->user(User::TYPE_CLIENT, 'مشترك آخر');

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v2/training-plans/{$this->plan->id}")
            ->assertStatus(404);

        $mine = $this->actingAs($stranger, 'sanctum')
            ->getJson('/api/v2/training-plans')
            ->assertOk()
            ->json('data.data');

        $this->assertSame([], $mine, 'another subscriber was shown a plan that is not theirs');

        // Another CAPTAIN is just as much a stranger to someone else's plan.
        $otherTrainer = $this->user(User::TYPE_BUSINESS, 'كابتن آخر');

        $this->actingAs($otherTrainer, 'sanctum')
            ->getJson("/api/v2/business/training-plans/{$this->plan->id}")
            ->assertStatus(404);
    }

    /** Deleting one photo takes the file with it. */
    public function test_deleting_a_photo_removes_the_file(): void
    {
        $image = $this->attachToExercise();
        $path = $this->track($image['image']);

        $this->assertFileExists($path);

        $this->actingAs($this->trainer, 'sanctum')
            ->deleteJson("/api/v2/business/training-plans/{$this->plan->id}/exercises/{$this->exercise->id}/images/{$image['id']}")
            ->assertOk();

        $this->assertFileDoesNotExist($path);
    }

    /**
     * Deleting the exercise takes its photos. This is the one that used to
     * leak: `->where('id', $x)->delete()` is a MASS delete and fires no model
     * event, so the pictures outlived what they illustrated with nothing left
     * that could find them.
     */
    public function test_deleting_the_exercise_takes_its_photos(): void
    {
        $path = $this->track($this->attachToExercise()['image']);

        $this->actingAs($this->trainer, 'sanctum')
            ->deleteJson("/api/v2/business/training-plans/{$this->plan->id}/exercises/{$this->exercise->id}")
            ->assertOk();

        $this->assertFileDoesNotExist($path, 'the illustration outlived the exercise');
        $this->assertSame(0, PlanExercise::query()->where('id', $this->exercise->id)->count());
    }

    /** @return array{id:int,image:string} */
    private function attachToExercise(): array
    {
        return $this->actingAs($this->trainer, 'sanctum')
            ->postJson("/api/v2/business/training-plans/{$this->plan->id}/exercises/{$this->exercise->id}/images", [
                'images' => [$this->file('squat.png')],
            ])
            ->assertStatus(201)
            ->json('data.images.0');
    }

    /** A real 1×1 PNG — this PHP build has no GD, so fake()->image() cannot run. */
    private function file(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }

    private function track(string $relative): string
    {
        $full = public_path($relative);
        $this->written[] = $full;

        return $full;
    }

    private function user(string $type, string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => 'train' . uniqid() . '@example.test',
            'password' => bcrypt('Test1234'),
            'type' => $type,
            'api_token' => Str::random(60),
            'phone' => '010' . random_int(10000000, 99999999),
        ]);
    }
}
