<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A talent card, with the boy's identity behind the paywall.
 *
 * Owner, 2026-08-18: «اخفى الاسم والنادى والفيديو قبل الدفع».
 *
 * The three hidden fields are the three that make him findable OUTSIDE the
 * platform — his name, the club he plays for, and the five minutes of video
 * that carry his face. What is left is enough to decide whether to pay and
 * useless for anything else: the sport, the position, the age, the physique.
 *
 * The hiding is done HERE and nowhere else, so there is one place to audit. A
 * caller who forgets to pass `$revealed` gets the safe answer, because the
 * default is false rather than true — the direction a mistake falls in matters
 * more than the mistake being impossible.
 *
 * `age` is exposed instead of `birth_date`: a scout filters on age, and a date
 * of birth plus a first name is most of an identity.
 */
class TalentCardResource extends JsonResource
{
    public function __construct($resource, private readonly bool $revealed = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'talent',

            // What a scout decides on.
            'sport' => $this->sport,
            'playing_position' => $this->playing_position,
            'age' => $this->birth_date?->diffInYears(now()),
            'height_cm' => $this->height_cm,
            'weight_kg' => $this->weight_kg,
            'preferred_foot' => $this->preferred_foot,
            'body' => $this->body,
            'created_at' => $this->created_at,

            // …and what he pays to see.
            'revealed' => $this->revealed,
            'name' => $this->when($this->revealed, fn () => $this->title),
            'current_club' => $this->when($this->revealed, fn () => $this->current_club),
            'video_url' => $this->when($this->revealed, fn () => $this->video_url),
            'contact' => $this->when($this->revealed, fn () => [
                'user_id' => $this->user_id,
                'phone' => $this->user?->phone,
                'name' => $this->user?->name,
            ]),

            /*
             * Always present so the client can render the lock without a second
             * call, and never a hint at the value behind it.
             */
            'locked_fields' => $this->when(! $this->revealed, ['name', 'current_club', 'video_url', 'contact']),
            'reveal_fee' => $this->when(! $this->revealed, (float) config('bim.talent.reveal_fee', 0)),
        ];
    }
}
