<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use App\Services\Notifications\NotificationDispatcherService;
use App\Services\Posts\CommentVisibilityService;
use Illuminate\Http\Request;

/**
 * Comments on posts, with threaded replies.
 *
 * There was no way to write one. v1's `CommentController::store()` and
 * `commentReplies()` exist but are **not routed** — only the two read methods
 * are — so the API could list comments and never create them. The feed
 * meanwhile reported a `comments_count` nobody could add to.
 *
 * Reading is public; the visibility rule (see CommentVisibilityService) is
 * applied as a query scope rather than by fetching and filtering in PHP the
 * way v1 did.
 */
final class CommentController extends Controller
{
    public function __construct(
        private readonly CommentVisibilityService $visibility,
    ) {
    }

    /** GET /api/v2/posts/{post}/comments — top-level comments. */
    public function index(Request $request, Post $post)
    {
        abort_if($post->type === 'job', 404, 'Use /jobs for job postings.');

        $viewer = $this->viewer($request);

        $comments = $this->visibility
            ->scope($post->comments()->getQuery(), $post, $viewer)
            ->with('user:id,name,logo,image')
            ->withCount(['children' => fn ($q) => $this->visibility->scope($q, $post, $viewer)])
            ->orderByDesc('id')
            ->paginate((int) $request->get('per_page', 20))
            ->appends($request->query());

        $this->attachViewerState($comments->getCollection(), $post, $viewer);

        return CommentResource::collection($comments);
    }

    /** GET /api/v2/comments/{comment}/replies — one thread's replies. */
    public function replies(Request $request, Comment $comment)
    {
        $post = $comment->post;

        abort_if($post === null, 404);

        $viewer = $this->viewer($request);

        // A reply is only reachable if its parent is.
        abort_if(! $this->visibility->canRead($comment, $post, $viewer), 404);

        $replies = $this->visibility
            ->scope($comment->children()->getQuery(), $post, $viewer)
            ->with('user:id,name,logo,image')
            ->orderBy('id')
            ->paginate((int) $request->get('per_page', 20))
            ->appends($request->query());

        $this->attachViewerState($replies->getCollection(), $post, $viewer);

        return CommentResource::collection($replies);
    }

    /** POST /api/v2/posts/{post}/comments — add a comment. */
    public function store(Request $request, Post $post)
    {
        abort_if($post->type === 'job', 404, 'Use /jobs for job postings.');

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
            'status' => ['nullable', 'in:public,private'],
        ]);

        $user = $request->user();

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'parent_id' => 0,
            'comment' => $data['comment'],
            'status' => $data['status'] ?? CommentVisibilityService::PUBLIC,
        ]);

        $this->notify($post, (int) $post->user_id, (int) $user->id, 'post_commented', $comment);

        return $this->single($comment, $post, $user, 201);
    }

    /** POST /api/v2/comments/{comment}/replies — reply to a comment. */
    public function reply(Request $request, Comment $comment)
    {
        $post = $comment->post;

        abort_if($post === null, 404);

        $user = $request->user();

        abort_if(! $this->visibility->canRead($comment, $post, $user), 404);

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
            'status' => ['nullable', 'in:public,private'],
        ]);

        // A reply can never be more visible than the comment it answers:
        // making it public would expose the private thread it belongs to.
        $status = $comment->status === CommentVisibilityService::PRIVATE
            ? CommentVisibilityService::PRIVATE
            : ($data['status'] ?? CommentVisibilityService::PUBLIC);

        $reply = Comment::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'parent_id' => $comment->id,
            'comment' => $data['comment'],
            'status' => $status,
        ]);

        $this->notify($post, (int) $comment->user_id, (int) $user->id, 'comment_replied', $reply);

        return $this->single($reply, $post, $user, 201);
    }

    /** PATCH /api/v2/comments/{comment} — edit your own words. */
    public function update(Request $request, Comment $comment)
    {
        $user = $request->user();

        if (! $this->visibility->canEdit($comment, $user)) {
            abort(403, 'You can only edit your own comment.');
        }

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
        ]);

        $comment->comment = $data['comment'];
        $comment->save();

        return $this->single($comment, $comment->post, $user);
    }

    /**
     * DELETE /api/v2/comments/{comment} — the author deletes their own; the
     * post's author may also delete, moderating their own thread.
     */
    public function destroy(Request $request, Comment $comment)
    {
        $post = $comment->post;

        abort_if($post === null, 404);

        $user = $request->user();

        if (! $this->visibility->canDelete($comment, $post, $user)) {
            abort(403, 'You can only delete your own comment, or one on your own post.');
        }

        // Replies would otherwise be orphaned and unreachable.
        $comment->children()->delete();
        $comment->delete();

        return response()->json(['success' => true, 'message' => 'Comment deleted.']);
    }

    // ───────────────────────────── internals ─────────────────────────────

    /**
     * The viewer on a PUBLIC route. `$request->user()` resolves the default
     * guard, which is not sanctum, so a bearer token would be ignored here and
     * a signed-in reader would silently lose sight of their own private
     * comments.
     */
    private function viewer(Request $request)
    {
        return $request->user() ?: auth('sanctum')->user();
    }

    private function attachViewerState($comments, Post $post, $viewer): void
    {
        $comments->each(function (Comment $c) use ($post, $viewer) {
            $c->is_mine = $viewer !== null && (int) $c->user_id === (int) $viewer->id;
            $c->can_delete = $viewer !== null && $this->visibility->canDelete($c, $post, $viewer);
        });
    }

    private function single(Comment $comment, ?Post $post, $viewer, int $status = 200)
    {
        $comment->load('user:id,name,logo,image')->loadCount('children');

        if ($post !== null) {
            $this->attachViewerState(collect([$comment]), $post, $viewer);
        }

        return response()->json(
            ['success' => true, 'data' => new CommentResource($comment)],
            $status
        );
    }

    /** Never notify someone about their own action. */
    private function notify(Post $post, int $recipientId, int $actorId, string $event, Comment $comment): void
    {
        if ($recipientId === $actorId || $recipientId <= 0) {
            return;
        }

        app(NotificationDispatcherService::class)->dispatch($event, $recipientId, [
            'actor_id' => $actorId,
            'body_ar' => mb_substr((string) $comment->comment, 0, 120),
            'body_en' => mb_substr((string) $comment->comment, 0, 120),
            'action_type' => 'open_post',
            'action_url' => '/posts/'.$post->id,
            'notifiable_type' => Post::class,
            'notifiable_id' => (int) $post->id,
            'source_type' => $event,
            'source_id' => (int) $comment->id,
            'meta' => ['post_id' => (int) $post->id, 'comment_id' => (int) $comment->id],
        ]);
    }
}
