<?php

namespace App\Models;

use App\Models\Concerns\HasOfferingOptions;
use App\Models\Concerns\HasOwnedImages;
use App\Models\Concerns\RecordsPriceHistory;
use App\Support\Concerns\HasLocalizedFields;
use App\Support\MarketCatalogChildren;
use App\Support\SaleUnits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use HasLocalizedFields;
    use HasOfferingOptions;

    /**
     * `menu_items.image` is a single legacy column that nothing ever wrote, so
     * a restaurant could not show its dish, a showroom its car, an estate agent
     * the flat. One photo would not have been enough anyway.
     */
    use HasOwnedImages;
    use RecordsPriceHistory;

    /**
     * Every move of this number is remembered — {@see RecordsPriceHistory}.
     *
     * A discount offer is checked against what the row used to cost, and that
     * check is worth nothing unless it is complete: several screens write this
     * price, so the recording lives on the model rather than in whichever of
     * them somebody remembered.
     */
    protected string $priceHistoryColumn = 'base_price';

    protected string $priceHistoryBusinessColumn = 'business_id';

    protected $table = 'menu_items';

    protected $fillable = [
        'business_id',
        'menu_section_id',
        'medicine_id',
        'item_type',
        'category_id',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'image',
        'base_price',
        'supply_price',
        'sale_unit',
        'brand_name',
        'available_quantity',
        'is_active',
        'sort_order',
        'is_featured',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'menu_section_id' => 'integer',
        'medicine_id' => 'integer',
        'category_id' => 'integer',
        'base_price' => 'decimal:2',
        // What the merchant paid, never the customer's business — see the
        // 2026-08-26 migration. Kept off every customer-facing payload by
        // omission, the same way `MenuItemResource` already whitelists.
        'supply_price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'is_featured' => 'boolean',
    ];

    /**
     * «٤٥ ج / كجم» — the price, and what it is the price OF.
     *
     * Null is not «unknown»: it is «by the item», which is what a sandwich is
     * and what most menus are. Only a shop that weighs what it sells has to
     * say anything.
     */
    public function priceUnitLabel(): ?string
    {
        return SaleUnits::label($this->sale_unit);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(MenuSection::class, 'menu_section_id');
    }

    /** The dictionary row this row prices, for a pharmacy's «قاموس الأدوية». */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MenuItemVariant::class, 'menu_item_id');
    }

    public function activeVariants(): HasMany
    {
        return $this->hasMany(MenuItemVariant::class, 'menu_item_id')
            ->where('is_active', true);
    }

    public function extras(): HasMany
    {
        return $this->hasMany(MenuItemExtra::class, 'menu_item_id');
    }

    public function activeExtras(): HasMany
    {
        return $this->hasMany(MenuItemExtra::class, 'menu_item_id')
            ->where('is_active', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForBusiness(Builder $query, ?int $businessId): Builder
    {
        if (! $businessId) {
            return $query;
        }

        return $query->where('business_id', $businessId);
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->name_ar ?: ($this->name_en ?: ('Item #' . $this->id)));
    }

    /**
     * The heading this item belongs under, and where it comes from.
     *
     * **The heading is the whole option COMBINATION, not just the line.** A
     * furniture merchant ticks غرفة نوم، ركنة and مودرن، كلاسيك، ألترا مودرن
     * once at registration; his menu then reads
     *
     *     غرفة نوم — مودرن        (3 items)
     *     غرفة نوم — كلاسيك       (5 items)
     *     ركنة — ألترا مودرن      (7 items)
     *
     * so a customer picks ONE thing instead of narrowing by option and then
     * again by service. That extra step was the whole complaint: محافظة →
     * تصنيف → ابن → خيارات → خدمات is too long a road to a bedroom.
     *
     * Precedence, most specific first:
     *   1. the section the merchant wrote himself — he asked for it by name
     *   2. the option combination — one vocabulary, the same one he is
     *      searched by
     *   3. the platform item type, for a child that has no line options yet
     *
     * The item types are NOT dead: `allowed_item_types` still gates what a
     * child may list, and retail's entire catalog scoping rides on it. Only
     * the heading moved.
     *
     * @param  bool|null  $isGoodsCatalog  Whether this item's OWN business is a
     *        `menu_market` trade — {@see \App\Support\MarketCatalogChildren}.
     *        Pass it when a caller already knows the answer for every item in
     *        a batch (one business, one lookup); left null it is worked out
     *        here from `$this->business`, a fine cost for the rare one-item
     *        call but a query-per-item N+1 for a whole menu's worth.
     * @return array{key:string,label:string,source:string,option_ids:array<int,int>}|null
     */
    public function heading(?bool $isGoodsCatalog = null): ?array
    {
        $section = $this->section;

        if ($section && $section->is_active) {
            return [
                'key' => 'section:' . $section->id,
                'label' => (string) $section->loc('name'),
                'source' => 'section',
                'option_ids' => [],
            ];
        }

        /*
         * «قاموس الأدوية» — one shared, 25,065-row dictionary, not a `line`
         * vocabulary a child carries. There is no per-drug group to browse by
         * the way a market's shelf has one per product category; the
         * dictionary itself is the one list, so every priced drug falls
         * under a single heading regardless of which one it is.
         *
         * @see \App\Models\Medicine
         */
        if ($this->medicine_id) {
            return [
                'key' => 'medicine_catalog',
                'label' => __('قاموس الأدوية'),
                'source' => 'medicine_catalog',
                'option_ids' => [],
            ];
        }

        $line = $this->lineOption();

        if ($line) {
            /*
             * «تعبئة الرفوف» — the market's shelf is browsed by PRODUCT
             * CATEGORY («أنواع الزيوت والسمن»), not by one option's own name.
             * The general combo heading below answers a different question
             * («غرفة نوم — مودرن» as one heading per combination); a market
             * has no modifier layered onto its options at all, so reusing it
             * would put every item under its own one-item heading — the
             * option's own name — instead of the shelf it sits on.
             *
             * @see \App\Support\MarketCatalogChildren
             */
            $isGoodsCatalog ??= MarketCatalogChildren::includes($this->business);

            if ($isGoodsCatalog) {
                return [
                    'key' => 'group:' . (int) $line->group_id,
                    'label' => (string) ($line->group?->name_ar ?: $line->group?->name_en),
                    'source' => 'catalog_group',
                    'option_ids' => [(int) $line->id],
                ];
            }

            $modifiers = $this->modifierOptions();

            // Sorted, so two items that carry the same options in a different
            // order land under one heading instead of two identical ones.
            $ids = $modifiers->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();

            return [
                'key' => 'combo:' . $line->id . ($ids->isEmpty() ? '' : ':' . $ids->implode(',')),
                'label' => collect([$line])->merge($modifiers)->map(fn (Option $o) => $o->displayName())->implode(' — '),
                'source' => 'option_combo',
                'option_ids' => $ids->prepend((int) $line->id)->all(),
            ];
        }

        if ($this->item_type) {
            return [
                'key' => 'type:' . $this->item_type,
                'label' => static::itemTypeLabel($this->item_type),
                'source' => 'item_type',
                'option_ids' => [],
            ];
        }

        return null;
    }

    /** Menu item-type key → its Arabic/English name, resolved once per request. */
    public static function itemTypeLabel(string $key): string
    {
        static $cache = null;

        if ($cache === null) {
            $cache = PlatformServiceItemType::query()
                ->whereHas('service', fn ($q) => $q->where('key', PlatformService::KEY_MENU))
                ->get(['key', 'name_ar', 'name_en'])
                ->keyBy('key');
        }

        $row = $cache->get($key);

        return $row ? $row->displayName() : $key;
    }
}
