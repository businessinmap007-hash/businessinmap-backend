<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A business's own named group of other businesses — see BusinessGroupMember. */
class BusinessGroup extends Model
{
    protected $fillable = ['user_id', 'name'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(BusinessGroupMember::class);
    }
}
