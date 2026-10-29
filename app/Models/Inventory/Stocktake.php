<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stocktake extends Model
{
    public const COUNTING = 'counting';
    public const REVIEW = 'review';
    public const APPLIED = 'applied';
    public const CANCELLED = 'cancelled';

    protected $table = 'inv_stocktakes';

    protected $guarded = ['id'];

    protected $casts = ['blind' => 'boolean', 'started_at' => 'datetime', 'applied_at' => 'datetime'];

    public function lines(): HasMany
    {
        return $this->hasMany(StocktakeLine::class, 'stocktake_id')->orderBy('id');
    }
}
