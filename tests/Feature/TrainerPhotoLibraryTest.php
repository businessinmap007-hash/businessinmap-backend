<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\TrainerPhoto;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\Media\ImageUploadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «صور المدرب تكون متاحة للمدرب لمشاركتها مع أكثر من متدرب» — a private library the
 * trainer fills once and attaches to as many clients' exercises as he likes.
 * Attaching COPIES the file into the plan, which is what these tests protect:
 * one client's plan can never be broken by another's, or by the library.
 */
class TrainerPhotoLibraryTest extends TestCase
{
    use DatabaseTransactions;

    private User $trainer;

    private Carbon $startedAt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startedAt = now()->subSecond();
        $this->trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
    }

    /** Remove the private files THIS test wrote (and only those). */
    protected function tearDown(): void
    {
        $paths = array_merge(
            DB::table('images')->where('image', 'like', ImageUploadService::PRIVATE_DIR . '/%')
                ->where('created_at', '>=', $this->startedAt)->pluck('image')->all(),
            DB::table('trainer_photos')->where('created_at', '>=', $this->startedAt)->pluck('image')->all(),
        );

        foreach ($paths as $path) {
            @unlink(ImageUploadService::privatePath($path));
        }

        parent::tearDown();
    }

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

    private function png(string $name = 'p.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }

    private function planWithExercise(?User $trainer = null): array
    {
        $plan = TrainingPlan::create([
            'trainer_id' => ($trainer ?? $this->trainer)->id, 'client_id' => $this->user(User::TYPE_CLIENT, 'C')->id,
            'title' => 'Plan', 'status' => TrainingPlan::STATUS_ACTIVE,
        ]);

        return [$plan, $plan->exercises()->create(['name' => 'Squat', 'sets' => 3])];
    }

    private function addToLibrary(int $n = 1): array
    {
        Sanctum::actingAs($this->trainer);

        return $this->postJson('/api/v2/business/training/photos', [
            'images' => array_map(fn ($i) => $this->png("p{$i}.png"), range(1, $n)),
            'caption' => 'Leg press',
        ])->assertCreated()->json('data.photos');
    }

    public function test_the_trainer_fills_his_library_and_only_he_lists_it(): void
    {
        $photos = $this->addToLibrary(2);

        $this->assertCount(2, $photos);
        $this->assertStringContainsString('/trainer-photos/' . $photos[0]['id'], $photos[0]['image']);

        $listed = $this->getJson('/api/v2/business/training/photos')->assertOk()->json('data.photos');
        $this->assertEqualsCanonicalizing(array_column($photos, 'id'), array_column($listed, 'id'));

        // Another trainer sees none of it, and cannot delete it.
        Sanctum::actingAs($this->user(User::TYPE_BUSINESS, 'OtherGym'));
        $this->assertSame([], $this->getJson('/api/v2/business/training/photos')->assertOk()->json('data.photos'));
        $this->deleteJson('/api/v2/business/training/photos/' . $photos[0]['id'])->assertStatus(404);

        // A client has no library at all.
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'Client'));
        $this->getJson('/api/v2/business/training/photos')->assertStatus(403);
    }

    public function test_a_library_photo_is_private_and_served_by_a_signed_link(): void
    {
        $photo = $this->addToLibrary()[0];
        $stored = TrainerPhoto::query()->findOrFail($photo['id'])->image;

        $this->assertTrue(ImageUploadService::isPrivate($stored));
        $this->assertFileDoesNotExist(public_path($stored));

        $this->get($photo['image'])->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get("/api/v2/trainer-photos/{$photo['id']}")->assertStatus(403);
    }

    public function test_one_photo_can_be_shared_with_several_clients_and_each_plan_stays_independent(): void
    {
        $photo = $this->addToLibrary()[0];
        [$planA, $exA] = $this->planWithExercise();
        [$planB, $exB] = $this->planWithExercise();

        Sanctum::actingAs($this->trainer);
        $a = $this->postJson("/api/v2/business/training-plans/{$planA->id}/exercises/{$exA->id}/images/from-library", ['photo_ids' => [$photo['id']]])
            ->assertCreated()->json('data.images.0');
        $b = $this->postJson("/api/v2/business/training-plans/{$planB->id}/exercises/{$exB->id}/images/from-library", ['photo_ids' => [$photo['id']]])
            ->assertCreated()->json('data.images.0');

        $pathA = Image::query()->findOrFail($a['id'])->image;
        $pathB = Image::query()->findOrFail($b['id'])->image;
        $libraryPath = TrainerPhoto::query()->findOrFail($photo['id'])->image;

        // Three distinct private files: the library's and one per plan.
        $this->assertCount(3, array_unique([$pathA, $pathB, $libraryPath]));
        foreach ([$pathA, $pathB, $libraryPath] as $p) {
            $this->assertFileExists(ImageUploadService::privatePath($p));
        }

        // Each client reads it in their own plan.
        Sanctum::actingAs(User::query()->findOrFail($planA->client_id));
        $this->assertCount(1, $this->getJson("/api/v2/training-plans/{$planA->id}")->assertOk()->json('data.plan.exercises.0.images'));

        // Deleting the library photo and one client's exercise leaves the other plan intact.
        Sanctum::actingAs($this->trainer);
        $this->deleteJson('/api/v2/business/training/photos/' . $photo['id'])->assertOk();
        $this->deleteJson("/api/v2/business/training-plans/{$planA->id}/exercises/{$exA->id}")->assertOk();

        $this->assertFileDoesNotExist(ImageUploadService::privatePath($libraryPath));
        $this->assertFileDoesNotExist(ImageUploadService::privatePath($pathA));
        $this->assertFileExists(ImageUploadService::privatePath($pathB), 'the other client\'s photo must survive');
        $this->get($b['image'])->assertOk();
    }

    public function test_a_photo_that_is_not_in_my_library_is_a_404(): void
    {
        [$plan, $exercise] = $this->planWithExercise();

        Sanctum::actingAs($this->user(User::TYPE_BUSINESS, 'OtherGym'));
        $foreign = $this->postJson('/api/v2/business/training/photos', ['images' => [$this->png()]])->assertCreated()->json('data.photos.0.id');

        Sanctum::actingAs($this->trainer);
        $this->postJson("/api/v2/business/training-plans/{$plan->id}/exercises/{$exercise->id}/images/from-library", ['photo_ids' => [$foreign]])
            ->assertStatus(404);
        $this->assertSame(0, $exercise->images()->count());
    }

    public function test_the_per_item_photo_limit_still_applies(): void
    {
        $photos = $this->addToLibrary(4);
        [$plan, $exercise] = $this->planWithExercise();

        Sanctum::actingAs($this->trainer);
        $url = "/api/v2/business/training-plans/{$plan->id}/exercises/{$exercise->id}/images/from-library";

        $this->postJson($url, ['photo_ids' => array_column($photos, 'id')])->assertCreated();
        // 4 attached, 6 is the cap: three more would make 7.
        $this->postJson($url, ['photo_ids' => array_slice(array_column($photos, 'id'), 0, 3)])->assertStatus(422);
        $this->assertSame(4, $exercise->images()->count());
    }

    public function test_an_upload_can_also_be_kept_in_the_library(): void
    {
        [$plan, $exercise] = $this->planWithExercise();

        Sanctum::actingAs($this->trainer);
        $this->postJson("/api/v2/business/training-plans/{$plan->id}/exercises/{$exercise->id}/images", [
            'images' => [$this->png()], 'save_to_library' => true,
        ])->assertCreated();

        $this->assertSame(1, TrainerPhoto::query()->where('trainer_id', $this->trainer->id)->count());

        // And without the flag nothing is added.
        $this->postJson("/api/v2/business/training-plans/{$plan->id}/exercises/{$exercise->id}/images", ['images' => [$this->png()]])->assertCreated();
        $this->assertSame(1, TrainerPhoto::query()->where('trainer_id', $this->trainer->id)->count());
    }
}
