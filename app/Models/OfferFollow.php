<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OfferFollow extends Model
{
    public const FOLLOW_PRODUCT = 'product';
    public const FOLLOW_SERVICE = 'service';
    public const FOLLOW_BOOKABLE_ITEM = 'bookable_item';
    public const FOLLOW_PACKAGE = 'package';
    public const FOLLOW_BUSINESS = 'business';
    public const FOLLOW_KEYWORD = 'keyword';
    public const FOLLOW_CATEGORY_CHILD = 'category_child';

    protected $table = 'offer_follows';

    protected $fillable = [
        'user_id',
        'followable_type',
        'followable_id',
        'keyword',
        'category_id',
        'category_child_id',
        'audience_type',
        'min_price',
        'max_price',
        'is_active',
        'last_matched_at',
        'meta',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'followable_id' => 'integer',
        'category_id' => 'integer',
        'category_child_id' => 'integer',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'is_active' => 'boolean',
        'last_matched_at' => 'datetime',
        'meta' => 'array',
    ];

    public static function followableTypes(): array
    {
        return [
            self::FOLLOW_PRODUCT,
            self::FOLLOW_SERVICE,
            self::FOLLOW_BOOKABLE_ITEM,
            self::FOLLOW_PACKAGE,
            self::FOLLOW_BUSINESS,
            self::FOLLOW_KEYWORD,
            self::FOLLOW_CATEGORY_CHILD,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function notifications()
    {
        return $this->hasMany(OfferFollowNotification::class, 'follow_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }
}
