<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Comments on posts. v1 could only READ them — store() and commentReplies()
 * were written but never routed — and applied the public/private rule by
 * fetching and merging in PHP, in two places, with a bug that hid a user's own
 * private replies from them.
 *
 * Most of what matters here is the visibility rule, so most of these tests are
 * about who can see what.
 */
class CommentsApiV2Test extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type = 'client'): User
    {
        return User::query()->forceCreate([
            'name' => 'Test '.$type.' '.uniqid(),
            'phone' => '01'.random_int(100000000, 999999999),
            'email' => $type.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'api_token' => Str::random(60),
            'type' => $type,
        ]);
    }

    private function makePost(User $author): Post
    {
        return Post::create([
            'type' => 'post',
            'user_id' => $author->id,
            'is_active' => true,
            'share_count' => 0,
            'title_ar' => 'منشور',
            'body' => 'نص',
        ]);
    }

    private function comment(Post $post, User $author, string $status = 'public', int $parentId = 0): Comment
    {
        return Comment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'parent_id' => $parentId,
            'comment' => $status.' comment by '.$author->id,
            'status' => $status,
        ]);
    }

    private function idsFrom($response): array
    {
        return collect($response->json('data'))->pluck('id')->all();
    }

    // ───────────────────────── the visibility rule ─────────────────────────

    public function test_guest_sees_only_public_comments(): void
    {
        $owner = $this->user('business');
        $post = $this->makePost($owner);

        $public = $this->comment($post, $this->user(), 'public');
        $private = $this->comment($post, $this->user(), 'private');

        $ids = $this->idsFrom($this->getJson('/api/v2/posts/'.$post->id.'/comments?per_page=50'));

        $this->assertContains($public->id, $ids);
        $this->assertNotContains($private->id, $ids);
    }

    public function test_the_post_owner_sees_every_comment_including_private_ones(): void
    {
        $owner = $this->user('business');
        $post = $this->makePost($owner);

        $public = $this->comment($post, $this->user(), 'public');
        $private = $this->comment($post, $this->user(), 'private');

        $ids = $this->idsFrom(
            $this->actingAs($owner, 'sanctum')->getJson('/api/v2/posts/'.$post->id.'/comments?per_page=50')
        );

        $this->assertContains($public->id, $ids);
        $this->assertContains($private->id, $ids, 'A private comment is addressed TO the post owner.');
    }

    public function test_a_reader_sees_public_comments_plus_their_own_private_one(): void
    {
        $owner = $this->user('business');
        $reader = $this->user();
        $post = $this->makePost($owner);

        $public = $this->comment($post, $this->user(), 'public');
        $myPrivate = $this->comment($post, $reader, 'private');
        $theirPrivate = $this->comment($post, $this->user(), 'private');

        $ids = $this->idsFrom(
            $this->actingAs($reader, 'sanctum')->getJson('/api/v2/posts/'.$post->id.'/comments?per_page=50')
        );

        $this->assertContains($public->id, $ids);
        $this->assertContains($myPrivate->id, $ids, 'You can always read what you wrote.');
        $this->assertNotContains($theirPrivate->id, $ids, "Another reader's private comment must stay hidden.");
    }

    public function test_a_reader_can_see_their_own_private_reply(): void
    {
        // v1's private branch filtered on parent_id = 0, so a user's own
        // private REPLY was invisible to its author. This is that regression.
        $owner = $this->user('business');
        $reader = $this->user();
        $post = $this->makePost($owner);

        $parent = $this->comment($post, $this->user(), 'public');
        $myPrivateReply = $this->comment($post, $reader, 'private', $parent->id);

        $ids = $this->idsFrom(
            $this->actingAs($reader, 'sanctum')->getJson('/api/v2/comments/'.$parent->id.'/replies?per_page=50')
        );

        $this->assertContains($myPrivateReply->id, $ids);
    }

    public function test_replies_under_a_private_comment_are_unreachable_to_outsiders(): void
    {
        $owner = $this->user('business');
        $asker = $this->user();
        $outsider = $this->user();
        $post = $this->makePost($owner);

        $private = $this->comment($post, $asker, 'private');

        $this->actingAs($outsider, 'sanctum')
            ->getJson('/api/v2/comments/'.$private->id.'/replies')
            ->assertNotFound();

        $this->actingAs($asker, 'sanctum')
            ->getJson('/api/v2/comments/'.$private->id.'/replies')
            ->assertOk();

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v2/comments/'.$private->id.'/replies')
            ->assertOk();
    }

    public function test_the_feed_counts_public_comments_only(): void
    {
        $owner = $this->user('business');
        $post = $this->makePost($owner);

        $this->comment($post, $this->user(), 'public');
        $this->comment($post, $this->user(), 'private');
        $this->comment($post, $this->user(), 'private');

        $response = $this->getJson('/api/v2/posts/'.$post->id);

        $response->assertOk();
        $this->assertSame(1, $response->json('data.comments_count'),
            'Counting private comments would advertise a number the reader cannot open.');
    }

    // ───────────────────────────── writing ─────────────────────────────

    public function test_a_signed_in_user_can_comment_and_the_owner_is_notified(): void
    {
        $owner = $this->user('business');
        $reader = $this->user();
        $post = $this->makePost($owner);

        $before = AppNotification::where('user_id', $owner->id)->count();

        $response = $this->actingAs($reader, 'sanctum')->postJson(
            '/api/v2/posts/'.$post->id.'/comments',
            ['comment' => 'تعليق جديد']
        );

        $response->assertCreated();
        $this->assertSame('تعليق جديد', $response->json('data.comment'));
        $this->assertSame('public', $response->json('data.status'));

        $this->assertSame(
            $before + 1,
            AppNotification::where('user_id', $owner->id)->count(),
            'The post owner should hear about a new comment.'
        );
    }

    public function test_commenting_on_your_own_post_does_not_notify_you(): void
    {
        $owner = $this->user('business');
        $post = $this->makePost($owner);

        $before = AppNotification::where('user_id', $owner->id)->count();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v2/posts/'.$post->id.'/comments', ['comment' => 'من صاحب المنشور'])
            ->assertCreated();

        $this->assertSame($before, AppNotification::where('user_id', $owner->id)->count());
    }

    public function test_a_reply_cannot_be_more_visible_than_the_comment_it_answers(): void
    {
        $owner = $this->user('business');
        $asker = $this->user();
        $post = $this->makePost($owner);

        $private = $this->comment($post, $asker, 'private');

        // Explicitly asking for a public reply on a private thread must not
        // leak the thread.
        $response = $this->actingAs($owner, 'sanctum')->postJson(
            '/api/v2/comments/'.$private->id.'/replies',
            ['comment' => 'رد', 'status' => 'public']
        );

        $response->assertCreated();
        $this->assertSame('private', $response->json('data.status'));
    }

    public function test_guests_cannot_write(): void
    {
        $post = $this->makePost($this->user('business'));

        $this->postJson('/api/v2/posts/'.$post->id.'/comments', ['comment' => 'x'])
            ->assertUnauthorized();
    }

    // ──────────────────────── editing + moderation ────────────────────────

    public function test_only_the_author_can_edit_their_comment(): void
    {
        $owner = $this->user('business');
        $author = $this->user();
        $post = $this->makePost($owner);
        $comment = $this->comment($post, $author, 'public');

        // Not even the post owner may rewrite someone's words.
        $this->actingAs($owner, 'sanctum')
            ->patchJson('/api/v2/comments/'.$comment->id, ['comment' => 'محرّف'])
            ->assertForbidden();

        $this->actingAs($author, 'sanctum')
            ->patchJson('/api/v2/comments/'.$comment->id, ['comment' => 'معدّل'])
            ->assertOk();

        $this->assertSame('معدّل', $comment->fresh()->comment);
    }

    public function test_the_post_owner_can_moderate_and_deleting_takes_the_replies(): void
    {
        $owner = $this->user('business');
        $author = $this->user();
        $post = $this->makePost($owner);

        $comment = $this->comment($post, $author, 'public');
        $reply = $this->comment($post, $this->user(), 'public', $comment->id);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson('/api/v2/comments/'.$comment->id)
            ->assertOk();

        $this->assertNull(Comment::find($comment->id));
        $this->assertNull(Comment::find($reply->id), 'Replies must not outlive their parent.');
    }

    public function test_an_unrelated_user_cannot_delete_a_comment(): void
    {
        $owner = $this->user('business');
        $post = $this->makePost($owner);
        $comment = $this->comment($post, $this->user(), 'public');

        $this->actingAs($this->user(), 'sanctum')
            ->deleteJson('/api/v2/comments/'.$comment->id)
            ->assertForbidden();

        $this->assertNotNull(Comment::find($comment->id));
    }

    public function test_jobs_do_not_accept_post_comments(): void
    {
        $business = $this->user('business');
        $job = Post::create([
            'type' => 'job', 'user_id' => $business->id, 'is_active' => true,
            'title_ar' => 'وظيفة', 'body' => 'وصف',
        ]);

        $this->getJson('/api/v2/posts/'.$job->id.'/comments')->assertNotFound();

        $this->actingAs($this->user(), 'sanctum')
            ->postJson('/api/v2/posts/'.$job->id.'/comments', ['comment' => 'x'])
            ->assertNotFound();
    }
}
