<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Image;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * v2 self-service photo albums — first v2 surface for the legacy Album
 * model. Own albums only; every other account's albums 403/404. Rolls back.
 */
class AlbumApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::query()->orderBy('id')->firstOrFail();
        $this->other = User::query()->orderBy('id', 'desc')->firstOrFail();
    }

    private const A_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    public function test_can_create_an_album_and_it_appears_in_the_index(): void
    {
        // Album::title picks title_ar/title_en by the CURRENT locale (see
        // Album::getTitleAttribute) — pinned explicitly since the test
        // environment's default app locale isn't 'ar'.
        $response = $this->withHeaders(['Accept-Language' => 'ar'])
            ->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/profile/albums', ['title_ar' => 'رحلة الإسكندرية'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'رحلة الإسكندرية')
            ->assertJsonPath('data.photos_count', 0);

        $albumId = $response->json('data.id');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v2/profile/albums')
            ->assertOk()
            ->assertJsonFragment(['id' => $albumId]);
    }

    public function test_adding_a_photo_sets_the_cover_and_increments_the_count(): void
    {
        $album = $this->user->albums()->create(['title_ar' => 'ألبوم']);
        $file = UploadedFile::fake()->createWithContent('a.png', base64_decode(self::A_PNG));

        $response = $this->actingAs($this->user, 'sanctum')
            ->post("/api/v2/profile/albums/{$album->id}/photos", ['image' => $file, 'source' => Image::SOURCE_CAMERA])
            ->assertCreated();

        $this->assertSame(1, $response->json('data.photos_count'));
        $this->assertNotNull($response->json('data.cover'));
        $this->assertSame(Image::SOURCE_CAMERA, $response->json('data.photos.0.source'));

        @unlink(public_path($album->fresh()->image));
    }

    public function test_removing_the_cover_photo_promotes_the_next_one(): void
    {
        $album = $this->user->albums()->create(['title_ar' => 'ألبوم']);
        $first = $album->images()->create(['image' => 'files/uploads/does-not-exist-1.png', 'source' => Image::SOURCE_UPLOAD]);
        $second = $album->images()->create(['image' => 'files/uploads/does-not-exist-2.png', 'source' => Image::SOURCE_UPLOAD]);
        $album->update(['image' => $first->image]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v2/profile/albums/{$album->id}/photos/{$first->id}")
            ->assertOk()
            ->assertJsonPath('data.cover', $second->image)
            ->assertJsonPath('data.photos_count', 1);
    }

    public function test_deleting_an_album_removes_its_photo_rows(): void
    {
        $album = $this->user->albums()->create(['title_ar' => 'ألبوم للحذف']);
        $album->images()->create(['image' => 'files/uploads/does-not-exist.png', 'source' => Image::SOURCE_UPLOAD]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v2/profile/albums/{$album->id}")
            ->assertOk();

        $this->assertNull(Album::find($album->id));
        $this->assertSame(0, Image::where('imageable_type', Album::class)->where('imageable_id', $album->id)->count());
    }

    public function test_cannot_view_or_modify_another_accounts_album(): void
    {
        $album = $this->other->albums()->create(['title_ar' => 'ليس ملكي']);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v2/profile/albums/{$album->id}")
            ->assertForbidden();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v2/profile/albums/{$album->id}")
            ->assertForbidden();
    }

    public function test_albums_require_auth(): void
    {
        $this->getJson('/api/v2/profile/albums')->assertUnauthorized();
    }
}
