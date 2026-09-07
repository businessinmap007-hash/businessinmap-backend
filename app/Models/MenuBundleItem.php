<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One fixed component of a {@see MenuBundle} — a menu item + how many of it. */
class MenuBundleItem extends Model
{
    protected $table = 'menu_bundle_items';

    protected $fillable = [
        'menu_bundle_id',
        'menu_item_id',
        'qty',
    ];

    protected $casts = [
        'menu_bundle_id' => 'integer',
        'menu_item_id' => 'integer',
        'qty' => 'integer',
    ];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(MenuBundle::class, 'menu_bundle_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }
}
