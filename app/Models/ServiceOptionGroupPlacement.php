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
 *                  When `branches_as_sections` is also set, each OPTION
 *                  becomes its own section instead — the group itself
 *                  stops being a department and just bundles siblings that
 *                  each get their own storefront section («موبايل», «تابلت»,
 *                  «ساعة ذكية» each standalone rather than all living inside
 *                  one «أجهزة الموبايل وملحقاتها» section).
 *   descriptive    a plain field on the product (brand, …) — never priced.
 *   price_variant  the SAME product has one price per option («كاش / قسط»,
 *                  «جديد / مستعمل / كسر زيرو»).
 *   component      a part the product is MADE OF («أنواع الأخشاب» at a furniture maker, the fabric of
 *                  a sheet) — chosen per item like a descriptive field, but a material, not a style.
 *                  The same group is the product line («section») at the trade that sells it by itself
 *                  (a timber merchant). The role belongs to the trade, never to the group.
 *   store_terms    a POLICY of the store — returns, minimum order, delivery, trade scope — chosen once
 *                  in the store's profile, shown on its page, shown at checkout and frozen on the order.
 *                  Never asked per item.
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

    /** A part the product is made of — described per item like {@see USAGE_DESCRIPTIVE}. */
    public const USAGE_COMPONENT = 'component';

    /** A policy of the STORE (returns, minimum order, delivery, scope): set once, shown at checkout. */
    public const USAGE_STORE_TERMS = 'store_terms';

    /** The usages that describe an ITEM, chosen per item. */
    public const ITEM_DESCRIBING = [self::USAGE_DESCRIPTIVE, self::USAGE_COMPONENT];

    /** A general setting of the STORE itself (pickup/delivery, payment methods…), shown in the cart. */
    public const USAGE_STORE_CART = 'store_cart';

    /** A general setting of the STORE itself, shown in the search filter. */
    public const USAGE_STORE_FILTER = 'store_filter';

    public const USAGES = [
        self::USAGE_SECTION,
        self::USAGE_DESCRIPTIVE,
        self::USAGE_PRICE_VARIANT,
        self::USAGE_COMPONENT,
        self::USAGE_STORE_TERMS,
        self::USAGE_STORE_CART,
        self::USAGE_STORE_FILTER,
    ];

    protected $fillable = [
        'platform_service_id',
        'option_group_id',
        'child_id',
        'item_type_key',
        'usage',
        'branches_as_sections',
        'is_active',
        'sort_order',
        'show_on_page',
        'display',
        'multiple',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'branches_as_sections' => 'boolean',
        'show_on_page' => 'boolean',
        'multiple' => 'boolean',
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
