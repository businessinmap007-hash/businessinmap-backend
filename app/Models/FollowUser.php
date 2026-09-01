<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One user following another (a client following a business, most of the
 * time). The table has existed since v1 — PostAudienceService already reads
 * it to build the personal feed's audience — but nothing could ever write to
 * it: v1's follow relations on User don't exist, and no v2 route touched it
 * either. This model plus Api\V2\FollowController is that missing write path.
 */
class FollowUser extends Model
{
    protected $table = 'follow_user';

    public $timestamps = false;

    protected $fillable = ['user_id', 'follow_id'];
}
