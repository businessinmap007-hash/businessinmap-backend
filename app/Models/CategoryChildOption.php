<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryChildOption extends Model
{
    protected $table = 'category_child_option';

    public $timestamps = false;

    /** 0 = granted under every root the child sits under; a real id = that root alone. */
    public const ALL_ROOTS = 0;

    protected $fillable = [
        'child_id',
        'category_id',
        'option_id',
        'reorder',
    ];

    protected $casts = [
        'child_id'    => 'integer',
        'category_id' => 'integer',
        'option_id'   => 'integer',
        'reorder'     => 'integer',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(CategoryChild::class, 'child_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'option_id');
    }
}