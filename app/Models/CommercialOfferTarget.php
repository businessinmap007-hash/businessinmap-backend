<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommercialOfferTarget extends Model
{
    public const TARGET_CATEGORY = 'category';
    public const TARGET_CATEGORY_CHILD = 'category_child';
    public const TARGET_BUSINESS = 'business';
    public const TARGET_USER_TYPE = 'user_type';
    public const TARGET_KEYWORD = 'keyword';

    protected $table = 'commercial_offer_targets';

    protected $fillable = [
        'offer_id',
        'target_type',
        'target_id',
        'keyword',
        'meta',
    ];

    protected $casts = [
        'offer_id' => 'integer',
        'target_id' => 'integer',
        'meta' => 'array',
    ];

    public static function targetTypes(): array
    {
        return [
            self::TARGET_CATEGORY,
            self::TARGET_CATEGORY_CHILD,
            self::TARGET_BUSINESS,
            self::TARGET_USER_TYPE,
            self::TARGET_KEYWORD,
        ];
    }

    public function offer()
    {
        return $this->belongsTo(CommercialOffer::class, 'offer_id');
    }
}
