<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use App\Services\Media\ImageUploadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The v2 posts surface, ported from a v1 controller that 500s for every
 * signed-in user (`User::byToken()` is undefined) and whose audience helper
 * throws BadMethodCallException.
 *
 * Uploads write real files into public/files/uploads — the database rolls back
 * but the filesystem does not, so every path this test creates is deleted in
 * tearDown.
 */
class PostsApiV2Test extends TestCase
{
    use DatabaseTransactions;

    /** @var list<string> */
    private array $writtenPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->writtenPaths as $path) {
            $full = public_path($path);

            if (is_file($full)) {
                @unlink($full);
            }
        }

        $this->writtenPaths = [];

        parent::tearDown();
    }

    private function trackUploadsOf(Post $post): void
    {
        if ($post->image) {
            $this->writtenPaths[] = $post->image;
        }

        foreach ($post->images as $image) {
            $this->writtenPaths[] = $image->image;
        }
    }

    private function user(string $type = 'client', ?int $categoryId = null): User
    {
        return User::query()->forceCreate([
            'name' => 'Test '.$type.' '.uniqid(),
            'phone' => '01'.random_int(100000000, 999999999),
            'email' => $type.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'api_token' => Str::random(60),
            'type' => $type,
            'category_id' => $categoryId,
        ]);
    }

    private function makePost(User $author, array $attributes = []): Post
    {
        return Post::create(array_merge([
            'type' => 'post',
            'user_id' => $author->id,
            'is_active' => true,
            'share_count' => 0,
            'title' => 'منشور اختبار',
            'body' => 'نص المنشور',
        ], $attributes));
    }

    // ───────────────────────────── the feed ─────────────────────────────

    public function test_guest_can_browse_the_feed(): void
    {
        $author = $this->user('business');
        $post = $this->makePost($author);

        $response = $this->getJson('/api/v2/posts?per_page=50');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'title', 'body', 'author', 'likes_count']]]);
        $this->assertContains($post->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_feed_is_scoped_to_the_audience_and_never_shows_your_own_posts(): void
    {
        $viewer = $this->user('client');
        $followed = $this->user('business');
        $stranger = $this->user('business');

        $visible = $this->makePost($followed);
        $hidden = $this->makePost($stranger);
        $ownPost = $this->makePost($viewer);

        DB::table('follow_user')->insert(['user_id' => $viewer->id, 'follow_id' => $followed->id]);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/v2/posts?per_page=50');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($visible->id, $ids, 'A followed account\'s post must appear.');
        $this->assertNotContains($hidden->id, $ids, 'An unrelated account\'s post must not.');
        $this->assertNotContains($ownPost->id, $ids, 'v1 excluded your own posts; so do we.');
    }

    public function test_an_account_with_no_audience_gets_an_empty_feed_not_the_whole_table(): void
    {
        $loner = $this->user('client');
        $this->makePost($this->user('business'));

        $response = $this->actingAs($loner, 'sanctum')->getJson('/api/v2/posts');

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    public function test_jobs_never_leak_into_the_posts_surface(): void
    {
        $business = $this->user('business');
        $job = $this->makePost($business, ['type' => 'job']);

        $this->getJson('/api/v2/posts/'.$job->id)->assertNotFound();

        $ids = collect($this->getJson('/api/v2/posts?per_page=50')->json('data'))->pluck('id')->all();
        $this->assertNotContains($job->id, $ids);
    }

    // ─────────────────────────── publishing ───────────────────────────

    public function test_publishing_persists_the_body_and_stores_the_images(): void
    {
        $author = $this->user('business');

        $response = $this->actingAs($author, 'sanctum')->postJson('/api/v2/posts', [
            'title' => 'عنوان',
            'body' => 'نص حقيقي للمنشور',
            'image' => UploadedFile::fake()->create('main.jpg', 64, 'image/jpeg'),
            'images' => [
                UploadedFile::fake()->create('one.jpg', 64, 'image/jpeg'),
                UploadedFile::fake()->create('two.png', 64, 'image/png'),
            ],
        ]);

        $response->assertCreated();

        $post = Post::findOrFail($response->json('data.id'));
        $post->load('images');
        $this->trackUploadsOf($post);

        // The Post::$fillable body_ar/body_en bug silently discarded this.
        $this->assertSame('نص حقيقي للمنشور', $post->body);
        $this->assertNotNull($post->image);
        $this->assertCount(2, $post->images);

        // Files really landed, under the shared uploads directory.
        $this->assertStringStartsWith(ImageUploadService::PUBLIC_DIR.'/', $post->image);
        $this->assertFileExists(public_path($post->image));

        foreach ($post->images as $image) {
            $this->assertFileExists(public_path($image->image));
        }
    }

    public function test_publishing_requires_a_title_and_a_body(): void
    {
        $author = $this->user('business');

        $this->actingAs($author, 'sanctum')
            ->postJson('/api/v2/posts', ['body' => 'بدون عنوان'])
            ->assertStatus(422);

        $this->actingAs($author, 'sanctum')
            ->postJson('/api/v2/posts', ['title' => 'بدون نص'])
            ->assertStatus(422);
    }

    public function test_an_executable_upload_is_rejected(): void
    {
        $author = $this->user('business');

        $response = $this->actingAs($author, 'sanctum')->postJson('/api/v2/posts', [
            'title' => 'عنوان',
            'body' => 'نص',
            'image' => UploadedFile::fake()->create('shell.php', 16, 'application/x-httpd-php'),
        ]);

        $response->assertStatus(422);
    }

    // ──────────────────────── ownership + edits ────────────────────────

    public function test_only_the_author_can_edit_or_delete(): void
    {
        $author = $this->user('business');
        $intruder = $this->user('business');
        $post = $this->makePost($author);

        $this->actingAs($intruder, 'sanctum')
            ->postJson('/api/v2/posts/'.$post->id, ['body' => 'مخترق'])
            ->assertForbidden();

        $this->actingAs($intruder, 'sanctum')
            ->deleteJson('/api/v2/posts/'.$post->id)
            ->assertForbidden();

        $this->assertSame('نص المنشور', $post->fresh()->body);
    }

    public function test_uploading_a_gallery_image_appends_unless_replace_is_asked_for(): void
    {
        $author = $this->user('business');
        $post = $this->makePost($author);

        $existing = new Image();
        $existing->image = ImageUploadService::PUBLIC_DIR.'/keep-me.jpg';
        $post->images()->save($existing);

        // Append (v1 wiped the gallery on any update carrying images).
        $this->actingAs($author, 'sanctum')->postJson('/api/v2/posts/'.$post->id, [
            'images' => [UploadedFile::fake()->create('added.jpg', 64, 'image/jpeg')],
        ])->assertOk();

        $post->load('images');
        $this->trackUploadsOf($post);
        $this->assertCount(2, $post->images);

        // Replace, explicitly.
        $this->actingAs($author, 'sanctum')->postJson('/api/v2/posts/'.$post->id, [
            'replace_images' => true,
            'images' => [UploadedFile::fake()->create('only.jpg', 64, 'image/jpeg')],
        ])->assertOk();

        $post->load('images');
        $this->trackUploadsOf($post);
        $this->assertCount(1, $post->images);
    }

    public function test_deleting_a_post_removes_its_files(): void
    {
        $author = $this->user('business');

        $created = $this->actingAs($author, 'sanctum')->postJson('/api/v2/posts', [
            'title' => 'للحذف',
            'body' => 'نص',
            'image' => UploadedFile::fake()->create('gone.jpg', 64, 'image/jpeg'),
        ]);

        $post = Post::findOrFail($created->json('data.id'));
        $path = $post->image;
        $this->writtenPaths[] = $path;

        $this->assertFileExists(public_path($path));

        $this->actingAs($author, 'sanctum')
            ->deleteJson('/api/v2/posts/'.$post->id)
            ->assertOk();

        $this->assertFileDoesNotExist(public_path($path));
        $this->assertNull(Post::find($post->id));
    }

    // ───────────────────────── share + reactions ─────────────────────────

    public function test_sharing_increments_the_counter(): void
    {
        $author = $this->user('business');
        $post = $this->makePost($author, ['share_count' => 4]);

        $response = $this->actingAs($this->user('client'), 'sanctum')
            ->postJson('/api/v2/posts/'.$post->id.'/share');

        $response->assertOk();
        $this->assertSame(5, $response->json('data.share_count'));
    }

    public function test_reacting_likes_then_switches_then_clears(): void
    {
        $author = $this->user('business');
        $reader = $this->user('client');
        $post = $this->makePost($author);

        $like = $this->actingAs($reader, 'sanctum')
            ->postJson('/api/v2/posts/'.$post->id.'/react', ['reaction' => 1]);
        $like->assertOk();
        $this->assertSame(1, $like->json('data.likes_count'));

        // Switching must move the vote, not add a second one.
        $dislike = $this->actingAs($reader, 'sanctum')
            ->postJson('/api/v2/posts/'.$post->id.'/react', ['reaction' => -1]);
        $dislike->assertOk();
        $this->assertSame(0, $dislike->json('data.likes_count'));
        $this->assertSame(1, $dislike->json('data.dislikes_count'));
        $this->assertSame(1, Like::where('post_id', $post->id)->where('user_id', $reader->id)->count());

        $clear = $this->actingAs($reader, 'sanctum')
            ->postJson('/api/v2/posts/'.$post->id.'/react', ['reaction' => 0]);
        $clear->assertOk();
        $this->assertNull($clear->json('data.my_reaction'));
        $this->assertSame(0, Like::where('post_id', $post->id)->where('user_id', $reader->id)->count());
    }

    public function test_my_reaction_comes_back_on_the_feed(): void
    {
        $viewer = $this->user('client');
        $author = $this->user('business');
        $post = $this->makePost($author);

        DB::table('follow_user')->insert(['user_id' => $viewer->id, 'follow_id' => $author->id]);

        $this->actingAs($viewer, 'sanctum')
            ->postJson('/api/v2/posts/'.$post->id.'/react', ['reaction' => 1])
            ->assertOk();

        $feed = $this->actingAs($viewer, 'sanctum')->getJson('/api/v2/posts?per_page=50');

        $row = collect($feed->json('data'))->firstWhere('id', $post->id);
        $this->assertNotNull($row);
        $this->assertSame(1, $row['my_reaction']);
    }

    public function test_mine_returns_only_my_posts(): void
    {
        $author = $this->user('business');
        $mine = $this->makePost($author);
        $theirs = $this->makePost($this->user('business'));

        $response = $this->actingAs($author, 'sanctum')->getJson('/api/v2/posts/mine?per_page=50');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }
}
