<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where an option group shows inside a service, and how it is used with an item
 * type of that service — see the create migration.
 */
class ServiceOptionGroupPlacement extends Model
{
    public const ALL_CHILDREN = 0;

    public const SURFACE_ITEM_FORM = 'item_form';
    public const SURFACE_PRICING = 'pricing';
    public const SURFACE_SEARCH_FILTER = 'search_filter';
    public const SURFACE_RESULT_CARD = 'result_card';
    public const SURFACE_ITEM_DETAIL = 'item_detail';
    public const SURFACE_BUSINESS_PAGE = 'business_page';

    public const SURFACES = [
        self::SURFACE_ITEM_FORM,
        self::SURFACE_PRICING,
        self::SURFACE_SEARCH_FILTER,
        self::SURFACE_RESULT_CARD,
        self::SURFACE_ITEM_DETAIL,
        self::SURFACE_BUSINESS_PAGE,
    ];

    public const USAGE_DEFINES_ITEM = 'defines_item';
    public const USAGE_CHANGES_PRICE = 'changes_price';
    public const USAGE_DESCRIPTIVE = 'descriptive';
    public const USAGE_FILTER_ONLY = 'filter_only';

    public const USAGES = [
        self::USAGE_DEFINES_ITEM,
        self::USAGE_CHANGES_PRICE,
        self::USAGE_DESCRIPTIVE,
        self::USAGE_FILTER_ONLY,
    ];

    public const INPUT_SINGLE = 'single';
    public const INPUT_MULTIPLE = 'multiple';
    public const INPUT_CHECKBOX = 'checkbox';

    public const INPUT_TYPES = [self::INPUT_SINGLE, self::INPUT_MULTIPLE, self::INPUT_CHECKBOX];

    protected $fillable = [
        'platform_service_id',
        'option_group_id',
        'child_id',
        'item_type_key',
        'surfaces',
        'usage',
        'input_type',
        'is_required',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'surfaces' => 'array',
        'is_required' => 'boolean',
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
