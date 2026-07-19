<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * A vacancy: the `type = 'job'` slice of the shared `posts` table.
 *
 * Posts and jobs live in one table on purpose — they share body, images,
 * comments, likes and the notification morphs, and only three columns are
 * job-only (salary, requirements, interview_starts_at). What was missing was
 * not a table, it was a guarantee: the `type` filter was written by hand in
 * six places, in three different spellings, and the admin post list simply
 * forgot it. Reading through this model makes the filter unforgettable —
 * queries, inserts and route-model binding are all constrained for free.
 */
class JobPost extends Post
{
    protected $table = 'posts';

    /**
     * Existing rows store `App\Models\Post` in images.imageable_type (857 of
     * them) and there is no morph map to normalise names. Without this, the
     * subclass reports its own FQCN, so `$job->images()` matches zero rows and
     * newly uploaded images are written under a type nothing else queries.
     * Pin the morph name to the parent.
     *
     * Must be a getMorphClass() override, not a `$morphClass` property — that
     * property does not exist in Eloquent and is silently ignored (confirmed
     * the hard way: the relation returned 0 images for a job that has them).
     */
    public function getMorphClass(): string
    {
        return Post::class;
    }

    /**
     * Post's hasMany relations (comments, likes, applies) name their foreign key
     * from the calling class's basename, so from here Eloquent would look for
     * `job_post_id` — a column that does not exist. Same trap as getMorphClass()
     * above: the parent's identity has to survive the subclass. Caught by
     * PostsApiV2Test, which failed on `Unknown column 'likes.feed_post_id'`.
     */
    public function getForeignKey(): string
    {
        return 'post_id';
    }

    protected static function booted(): void
    {
        static::addGlobalScope('job', fn (Builder $q) => $q->where('type', 'job'));

        // So callers cannot create a JobPost that is not a job.
        static::creating(function (self $post) {
            $post->type = 'job';
        });
    }
}
