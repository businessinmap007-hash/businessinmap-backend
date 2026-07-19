<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * A feed post: the `type = 'post'` slice of the shared `posts` table.
 *
 * The counterpart to {@see JobPost} — see that class for why one table.
 */
class FeedPost extends Post
{
    protected $table = 'posts';

    /** @see JobPost::getMorphClass() — same reason: keep the morph name as Post. */
    public function getMorphClass(): string
    {
        return Post::class;
    }

    /** @see JobPost::getForeignKey() — otherwise relations look for `feed_post_id`. */
    public function getForeignKey(): string
    {
        return 'post_id';
    }

    protected static function booted(): void
    {
        // `type = 'post'`, not `type != 'job'` (the spelling Api\V2\PostController
        // used). The two are equivalent only while no row is NULL — verified 0 of
        // 880 are, and the column is enum('post','job') — but `!=` drops NULLs in
        // SQL, so the positive form is the one that stays correct if that changes.
        static::addGlobalScope('feed', fn (Builder $q) => $q->where('type', 'post'));

        static::creating(function (self $post) {
            $post->type = 'post';
        });
    }
}
