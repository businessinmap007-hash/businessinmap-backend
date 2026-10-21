<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What a promotion covers: the whole shop (`all`), a menu section, an item, a retail listing, or a retail category. */
class PromotionTarget extends Model
{
    public $timestamps = false;

    protected $fillable = ['promotion_id', 'target_type', 'target_id'];
}
