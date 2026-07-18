<?php

namespace App\Services\Posts;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who may read which comment.
 *
 * A comment carries `status` = public|private. Private means "between me and
 * the business" — a question you do not want the rest of the feed reading. The
 * rule, preserved from v1:
 *
 *   - the post's author sees EVERY comment on their post;
 *   - any other signed-in reader sees public comments, plus their own private
 *     ones;
 *   - a guest sees public comments only.
 *
 * v1 applied this inline in two controller methods, and its private branch
 * filtered on `['parent_id' => 0, 'user_id' => $user->id]` — so a user's own
 * private *replies* were hidden from the user who wrote them. Scoping it once,
 * here, fixes that and keeps the list and reply endpoints honest with each
 * other.
 */
final class CommentVisibilityService
{
    public const PUBLIC = 'public';
    public const PRIVATE = 'private';

    /**
     * Constrain a comment query to what $viewer is allowed to read.
     * $viewer may be null (guest).
     */
    public function scope(Builder $query, Post $post, ?User $viewer): Builder
    {
        if ($viewer !== null && (int) $viewer->id === (int) $post->user_id) {
            return $query;
        }

        return $query->where(function (Builder $w) use ($viewer) {
            $w->where('status', self::PUBLIC);

            if ($viewer !== null) {
                // Note: no parent_id constraint — a private reply belongs to
                // its author just as much as a private top-level comment does.
                $w->orWhere(fn (Builder $own) => $own
                    ->where('status', self::PRIVATE)
                    ->where('user_id', $viewer->id));
            }
        });
    }

    /** Whether $viewer may read one specific comment. */
    public function canRead(Comment $comment, Post $post, ?User $viewer): bool
    {
        if ($comment->status === self::PUBLIC) {
            return true;
        }

        if ($viewer === null) {
            return false;
        }

        return (int) $viewer->id === (int) $comment->user_id
            || (int) $viewer->id === (int) $post->user_id;
    }

    /**
     * Whether $viewer may delete it: its author, or the post's author acting
     * as moderator of their own thread.
     */
    public function canDelete(Comment $comment, Post $post, User $viewer): bool
    {
        return (int) $viewer->id === (int) $comment->user_id
            || (int) $viewer->id === (int) $post->user_id;
    }

    /** Only the author may edit the words; the post owner moderates, not rewrites. */
    public function canEdit(Comment $comment, User $viewer): bool
    {
        return (int) $viewer->id === (int) $comment->user_id;
    }
}
