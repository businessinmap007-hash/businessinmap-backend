<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryChildOption extends Model
{
    protected $table = 'category_child_option';

    public $timestamps = false;

    protected $fillable = [
        'child_id',
        'option_id',
        'group_id',
        'reorder',
    ];

    protected $casts = [
        'child_id'  => 'integer',
        'option_id' => 'integer',
        'group_id'  => 'integer',
        'reorder'   => 'integer',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(CategoryChild::class, 'child_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'option_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CategoryChildOptionGroup::class, 'group_id');
    }
}