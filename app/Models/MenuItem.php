<?php

namespace App\Models;

use App\Models\Concerns\HasOfferingOptions;
use App\Models\Concerns\HasOwnedImages;
use App\Models\Concerns\RecordsPriceHistory;
use App\Support\Concerns\HasLocalizedFields;

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
        'item_type',
        'category_id',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'image',
        'base_price',
        'is_active',
        'sort_order',
        'is_featured',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'menu_section_id' => 'integer',
        'category_id' => 'integer',
        'base_price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'is_featured' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(MenuSection::class, 'menu_section_id');
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
     * @return array{key:string,label:string,source:string,option_ids:array<int,int>}|null
     */
    public function heading(): ?array
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

        $line = $this->lineOption();

        if ($line) {
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
