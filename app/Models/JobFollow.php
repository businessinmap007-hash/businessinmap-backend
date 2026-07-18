<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobFollow extends Model
{
    protected $table = 'job_follows';

    protected $fillable = [
        'user_id',
        'category_id',
        'category_child_id',
        'is_active',
        'last_matched_at',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_matched_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function categoryChild(): BelongsTo
    {
        return $this->belongsTo(CategoryChild::class, 'category_child_id');
    }
}
