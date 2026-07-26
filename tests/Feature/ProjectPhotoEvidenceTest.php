<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Build/manufacturing evidence: a stage's progress photo must be captured live
 * by the camera, and a stage that requires a photo cannot be completed without
 * one. The `source` flag is recorded on the image row.
 */
class ProjectPhotoEvidenceTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<string> public-relative paths written during the test */
    private array $writtenFiles = [];

    protected function tearDown(): void
    {
        // ImageUploadService writes to public/ directly (not a fakeable disk),
        // so the fake uploads persist past the DB rollback — clean them up.
        foreach ($this->writtenFiles as $path) {
            $full = public_path($path);
            if (is_file($full)) {
                @unlink($full);
            }
        }

        parent::tearDown();
    }

    /** Record any stored photo so tearDown can remove it. */
    private function trackPhoto($response): void
    {
        $url = $response->json('data.photo.url');
        if ($url) {
            $this->writtenFiles[] = ltrim(parse_url($url, PHP_URL_PATH) ?: '', '/');
        }
    }

    private function makeBusiness(): User
    {
        $u = new User();
        $u->name = 'Proj Photo ' . Str::random(4);
        $u->email = 'projphoto-' . uniqid() . '@example.test';
        $u->phone = '0101' . random_int(1000000, 9999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_BUSINESS;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    /** @return array{0:int,1:int} [projectId, taskId] */
    private function projectWithTask(bool $requiresPhoto = true): array
    {
        $projectId = $this->postJson('/api/v2/business/projects', ['title' => 'Cabinet batch'])
            ->json('data.project.id');

        $taskId = $this->postJson("/api/v2/business/projects/{$projectId}/tasks", [
            'title' => 'Finishing',
            'requires_photo' => $requiresPhoto,
        ])->json('data.task.id');

        return [(int) $projectId, (int) $taskId];
    }

    public function test_a_camera_photo_is_accepted_and_flagged_camera(): void
    {
        Sanctum::actingAs($this->makeBusiness());
        [$projectId, $taskId] = $this->projectWithTask();

        $res = $this->postJson("/api/v2/business/projects/{$projectId}/tasks/{$taskId}/photo", [
            'photo' => UploadedFile::fake()->create('stage.jpg', 64, 'image/jpeg'),
            'source' => 'camera',
        ])->assertCreated()
            ->assertJsonPath('data.photo.is_camera', true)
            ->assertJsonPath('data.photo.source', 'camera');
        $this->trackPhoto($res);

        $this->assertDatabaseHas('images', [
            'imageable_type' => ProjectTask::class,
            'imageable_id' => $taskId,
            'source' => Image::SOURCE_CAMERA,
        ]);
    }

    public function test_an_uploaded_photo_is_refused_as_evidence(): void
    {
        Sanctum::actingAs($this->makeBusiness());
        [$projectId, $taskId] = $this->projectWithTask();

        $this->postJson("/api/v2/business/projects/{$projectId}/tasks/{$taskId}/photo", [
            'photo' => UploadedFile::fake()->create('gallery.jpg', 64, 'image/jpeg'),
            'source' => 'upload',
        ])->assertStatus(422)->assertJsonValidationErrors('source');

        $this->assertDatabaseMissing('images', [
            'imageable_type' => ProjectTask::class,
            'imageable_id' => $taskId,
        ]);
    }

    public function test_a_stage_that_requires_a_photo_cannot_complete_without_one(): void
    {
        Sanctum::actingAs($this->makeBusiness());
        [$projectId, $taskId] = $this->projectWithTask(true);

        // No photo yet → completion is blocked.
        $this->patchJson("/api/v2/business/projects/{$projectId}/tasks/{$taskId}/progress", [
            'status' => 'done',
        ])->assertStatus(422)->assertJsonValidationErrors('photo');

        // Add camera evidence, then completion goes through and forces 100%.
        $this->trackPhoto($this->postJson("/api/v2/business/projects/{$projectId}/tasks/{$taskId}/photo", [
            'photo' => UploadedFile::fake()->create('done.jpg', 64, 'image/jpeg'),
            'source' => 'camera',
        ])->assertCreated());

        $this->patchJson("/api/v2/business/projects/{$projectId}/tasks/{$taskId}/progress", [
            'status' => 'done',
        ])->assertOk()
            ->assertJsonPath('data.task.status', 'done')
            ->assertJsonPath('data.task.progress', 100);
    }

    public function test_a_stage_without_the_photo_requirement_completes_freely(): void
    {
        Sanctum::actingAs($this->makeBusiness());
        [$projectId, $taskId] = $this->projectWithTask(false);

        $this->patchJson("/api/v2/business/projects/{$projectId}/tasks/{$taskId}/progress", [
            'progress' => 100,
        ])->assertOk()->assertJsonPath('data.task.status', 'done');
    }
}
