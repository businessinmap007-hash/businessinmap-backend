<?php

namespace App\Models;

use App\Models\Concerns\HasOfferingOptions;
use App\Support\Concerns\HasLocalizedFields;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use HasLocalizedFields;
    use HasOfferingOptions;

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
     * Precedence, most specific first:
     *   1. the section the merchant wrote himself — he asked for it by name
     *   2. the platform item type («مشويات») — a restaurant's food headings
     *   3. the line option («غرفة نوم») — a showroom's, whose item type is the
     *      useless «قطعة أثاث»
     *
     * Two families, two vocabularies, one heading: a restaurant's kinds of food
     * live in the item types while its options describe the venue (توصيل، واي
     * فاي، عائلي); a furniture child is the exact reverse. Neither can be
     * folded into the other without breaking the family it does not fit.
     *
     * @return array{key:string,label:string,source:string}|null
     */
    public function heading(): ?array
    {
        $section = $this->section;

        if ($section && $section->is_active) {
            return ['key' => 'section:' . $section->id, 'label' => (string) $section->loc('name'), 'source' => 'section'];
        }

        if ($this->item_type) {
            return [
                'key' => 'type:' . $this->item_type,
                'label' => static::itemTypeLabel($this->item_type),
                'source' => 'item_type',
            ];
        }

        $line = $this->lineOption();

        if ($line) {
            return ['key' => 'option:' . $line->id, 'label' => $line->displayName(), 'source' => 'line_option'];
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
