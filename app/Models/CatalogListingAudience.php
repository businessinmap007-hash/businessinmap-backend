<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of «who may see this listing».
 *
 * Three kinds, because a manufacturer names his buyers three ways:
 *
 *   business        this company, by id — «الشركات التي يحددها»
 *   category_child  every business of this trade — «كل محلات الأدوات الصحية»
 *   category        every business under this root — «كل الشركات»
 *
 * The two classification kinds are not a convenience. A factory with four
 * hundred customers is not going to tick four hundred names, and a rule that
 * can only be written one row at a time is a rule nobody writes.
 */
class CatalogListingAudience extends Model
{
    public const TYPE_BUSINESS = 'business';

    public const TYPE_CATEGORY_CHILD = 'category_child';

    public const TYPE_CATEGORY = 'category';

    public const TYPES = [
        self::TYPE_BUSINESS,
        self::TYPE_CATEGORY_CHILD,
        self::TYPE_CATEGORY,
    ];

    protected $table = 'catalog_listing_audiences';

    protected $fillable = [
        'business_catalog_listing_id',
        'audience_type',
        'audience_id',
    ];

    protected $casts = [
        'business_catalog_listing_id' => 'integer',
        'audience_id' => 'integer',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(BusinessCatalogListing::class, 'business_catalog_listing_id');
    }
}
