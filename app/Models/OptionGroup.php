<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OptionGroup extends Model
{
    protected $table = 'option_groups';

    /** The option IS what the customer buys — «كشف عظام», «غرفة نوم». */
    public const ROLE_LINE = 'line';

    /** Never bought alone, but it changes a line's price — «مودرن», «إيجار». */
    public const ROLE_MODIFIER = 'modifier';

    /** Never priced at all — «كاش», «ممنوع التدخين». The safe default. */
    public const ROLE_DESCRIPTIVE = 'descriptive';

    public const ROLES = [self::ROLE_LINE, self::ROLE_MODIFIER, self::ROLE_DESCRIPTIVE];

    protected $fillable = [
        'name_ar',
        'name_en',
        'reorder',
        'is_active',
        'price_role',
    ];

    protected $casts = [
        'reorder'   => 'integer',
        'is_active' => 'boolean',
    ];

    /** Groups a pricing screen may show: the lines and what qualifies them. */
    public function scopePriced($query)
    {
        return $query->whereIn('price_role', [self::ROLE_LINE, self::ROLE_MODIFIER]);
    }

    public function scopeLines($query)
    {
        return $query->where('price_role', self::ROLE_LINE);
    }

    public function scopeModifiers($query)
    {
        return $query->where('price_role', self::ROLE_MODIFIER);
    }

    public function isPriced(): bool
    {
        return in_array($this->price_role, [self::ROLE_LINE, self::ROLE_MODIFIER], true);
    }

    public function roleLabel(): string
    {
        return match ($this->price_role) {
            self::ROLE_LINE => __('سطر مُسعَّر'),
            self::ROLE_MODIFIER => __('مُعدِّل للسعر'),
            default => __('وصفي'),
        };
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class, 'group_id')
            ->orderBy('id');
    }

    public function activeOptions(): HasMany
    {
        return $this->hasMany(Option::class, 'group_id')
            ->when(method_exists(Option::query()->getModel(), 'scopeActive'), function ($q) {
                $q->active();
            })
            ->orderBy('id');
    }

    public function displayName(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale === 'ar') {
            return (string) ($this->name_ar ?: $this->name_en ?: ('Group #' . $this->id));
        }

        return (string) ($this->name_en ?: $this->name_ar ?: ('Group #' . $this->id));
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->displayName();
    }
}