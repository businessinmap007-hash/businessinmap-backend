<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What an option group IS inside a service for one child (or for every child
 * when child_id is 0) — see the create migration.
 *
 *   section        the group's name is a department («أجهزة كهربائية»,
 *                  «مواد غذائية») and its options are that department's
 *                  branches («ثلاجات», «غسالات»). A child may carry several.
 *   descriptive    a plain field on the product (brand, …) — never priced.
 *   price_variant  the SAME product has one price per option («كاش / قسط»,
 *                  «جديد / مستعمل / كسر زيرو»).
 *   store_cart     a general setting of the store itself, shown in the cart
 *                  («التسليم والاستلام», payment methods).
 *   store_filter   a general setting of the store itself, shown in the search
 *                  filter.
 *
 * Where each one shows follows from what it is; nothing else is configured.
 */
class ServiceOptionGroupPlacement extends Model
{
    public const ALL_CHILDREN = 0;

    public const USAGE_SECTION = 'section';
    public const USAGE_DESCRIPTIVE = 'descriptive';
    public const USAGE_PRICE_VARIANT = 'price_variant';

    /** A general setting of the STORE itself (pickup/delivery, payment methods…), shown in the cart. */
    public const USAGE_STORE_CART = 'store_cart';

    /** A general setting of the STORE itself, shown in the search filter. */
    public const USAGE_STORE_FILTER = 'store_filter';

    public const USAGES = [
        self::USAGE_SECTION,
        self::USAGE_DESCRIPTIVE,
        self::USAGE_PRICE_VARIANT,
        self::USAGE_STORE_CART,
        self::USAGE_STORE_FILTER,
    ];

    protected $fillable = [
        'platform_service_id',
        'option_group_id',
        'child_id',
        'item_type_key',
        'usage',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'child_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function optionGroup(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
    }
}
