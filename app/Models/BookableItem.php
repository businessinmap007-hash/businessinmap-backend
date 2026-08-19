<?php

namespace App\Models;

use App\Models\Concerns\HasOfferingOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

class BookableItem extends Model
{
    /**
     * الوحدةُ تصف نفسها بكلمات المنصّة، لا بكودها وحده.
     *
     * «غرفة ١٠١ — إطلالة بحرية — بلكونة». وليست زينةً على الاسم: سطرُ السعر
     * يحمل زيادةً لكل مُوصِّف منذ 2026-08-19، فغرفةٌ تعلن إطلالتَها تُسعِّر
     * نفسها — ولا يُطلب من النزيل أن يؤشّر صفةً هى فى الغرفة أصلًا.
     *
     * و`line_option_id` يبقى العمودَ الذى يشير إلى السطر المسعَّر؛ السِّمة
     * تكتبه بنفسها حين تُزامن، فلا يفترق العمودُ عن الصفوف.
     */
    use HasOfferingOptions;

    /** المفتاح الأجنبىّ هنا يرفض الصفر — «بلا نوع» تُكتب NULL. */
    protected function lineOptionColumnIsNullable(): bool
    {
        return true;
    }

    protected $table = 'bookable_items';

    // Units are inventory only. Price and deposit are single-source in
    // business_service_prices (per item type); the legacy price/deposit_*
    // columns were dropped from bookable_items. See services-blueprint.md.
    protected $fillable = [
        'business_id',
        'service_id',
        'item_type',
        // WHICH kind this unit is. The item type says «حجز إقامة» for every room
        // in the hotel; only this separates room 101 from suite س301, and so
        // only this can point the unit at its own priced row.
        'line_option_id',
        'title',
        'code',
        'capacity',
        'quantity',

        'is_active',
        'meta',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'service_id' => 'integer',
        'item_type' => 'string',
        'line_option_id' => 'integer',
        'capacity' => 'integer',
        'quantity' => 'integer',

        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(PlatformService::class, 'service_id');
    }

    public function lineOption(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'line_option_id');
    }

    /**
     * ما يميّز هذه الوحدة بعينها — «إطلالة بحرية»، «على المسبح».
     *
     * النوعُ (`line_option_id`) يقول ما هى: غرفة مزدوجة. وهذا يقول ما يفرّقها
     * عن مزدوجةٍ أخرى — والزيادةُ المكتوبة على «إطلالة بحرية» فى سطر السعر
     * تُقرأ منه فتصير غرفةُ ١٠١ أغلى من ١٠٢ بلا سطرٍ ثانٍ.
     *
     * ويسكن `offering_options` نفسه الذى يحمل مفردات صفوف السعر وأصناف المنيو،
     * فلا آليةَ ثانية: `HasOfferingOptions` مضافةٌ إلى هذا النموذج أصلًا،
     * و`syncOfferingOptions()` تكتب السطرَ والمُوصِّفات وتُطابق `line_option_id`
     * بنفسها.
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    public function modifierOptionIds(): Collection
    {
        return $this->offeringOptions()
            ->where('role', OfferingOption::ROLE_MODIFIER)
            ->pluck('option_id')
            ->map(fn ($id) => (int) $id);
    }

    public function blockedSlots(): HasMany
    {
        return $this->hasMany(BookableItemBlockedSlot::class, 'bookable_item_id');
    }

    public function activeBlockedSlots(): HasMany
    {
        return $this->hasMany(BookableItemBlockedSlot::class, 'bookable_item_id')
            ->where('is_active', true)
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(BookableItemPriceRule::class, 'bookable_item_id');
    }

    public function activePriceRules(): HasMany
    {
        return $this->hasMany(BookableItemPriceRule::class, 'bookable_item_id')
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderByDesc('id');
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

    public function scopeForService(Builder $query, ?int $serviceId): Builder
    {
        if (! $serviceId) {
            return $query;
        }

        return $query->where('service_id', $serviceId);
    }

    public function scopeForItemType(Builder $query, ?string $itemType): Builder
    {
        $itemType = trim((string) $itemType);

        if ($itemType === '') {
            return $query;
        }

        return $query->where('item_type', $itemType);
    }

    public function scopeForLineOption(Builder $query, ?int $lineOptionId): Builder
    {
        if (! $lineOptionId) {
            return $query;
        }

        return $query->where('line_option_id', $lineOptionId);
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->title ?: ($this->code ?: ('Item #' . $this->id)));
    }

    /**
     * «جناح — س301». The kind first, because the code alone («س301») says
     * nothing to a customer and the kind alone cannot be booked.
     */
    public function displayLabel(): string
    {
        $kind = (string) (optional($this->lineOption)->name_ar ?: optional($this->lineOption)->name_en ?: '');
        $unit = (string) ($this->code ?: $this->title);

        if ($kind === '') {
            return $unit !== '' ? $unit : ('#' . $this->id);
        }

        return $unit !== '' ? ($kind . ' — ' . $unit) : $kind;
    }

    /**
     * The unit's base price, single-sourced from the BusinessServicePrice for
     * its item type (bookable_items no longer carries a price column). Resolved
     * lazily; call only where a single unit's base price is needed, not in lists.
     */
    public function resolvedBasePrice(): float
    {
        $price = app(\App\Services\BusinessServicePriceResolver::class)
            ->resolveForBookableItem($this);

        return round((float) ($price?->baseUnitPrice() ?? 0), 2);
    }
}