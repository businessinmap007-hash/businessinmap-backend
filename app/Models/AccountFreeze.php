<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One freeze of one account, with the reason recorded for it. Only the recent ones count towards deletion. */
class AccountFreeze extends Model
{
    protected $fillable = ['user_id', 'case_id', 'frozen_by', 'reason'];

    /** The freezes that still count: those inside `bim.moderation.freeze_window_months`. */
    public function scopeCounting($query)
    {
        return $query->where('created_at', '>=', now()->subMonths((int) config('bim.moderation.freeze_window_months', 12)));
    }
}
