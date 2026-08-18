<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * A young player's card: the `type = 'talent'` slice of the shared `posts`
 * table.
 *
 * Same shape as {@see JobPost} and for the same reason — one table, one set of
 * images, comments, likes and notification morphs, and a handful of columns
 * that only this slice sets. The difference is which way the advert points: a
 * vacancy is a business looking for a person, a talent card is a person looking
 * for a business, and «مستكشف لاعبين» under «الرياضة» is the business reading
 * it.
 *
 * The two overrides below are not optional and are not cosmetic; both were
 * learned the hard way on JobPost and are repeated here deliberately.
 */
class TalentPost extends Post
{
    protected $table = 'posts';

    /**
     * Existing rows store `App\Models\Post` in `images.imageable_type` and there
     * is no morph map to normalise names. Without this the subclass reports its
     * own FQCN, `$talent->images()` matches zero rows, and newly uploaded images
     * are written under a type nothing else queries.
     */
    public function getMorphClass(): string
    {
        return Post::class;
    }

    /**
     * Post's hasMany relations name their foreign key from the calling class's
     * basename, so from here Eloquent would look for `talent_post_id` — a column
     * that does not exist. The parent's identity has to survive the subclass.
     */
    public function getForeignKey(): string
    {
        return 'post_id';
    }

    protected static function booted(): void
    {
        static::addGlobalScope('talent', fn (Builder $q) => $q->where('type', 'talent'));

        // So callers cannot create a TalentPost that is not one.
        static::creating(function (self $post) {
            $post->type = 'talent';
        });
    }

    /** Scouts search on age, not on a date of birth. */
    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->diffInYears(now());
    }
}
