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

    /**
     * The order a customer meets the groups in: what is bought, then what
     * changes its price, then what merely describes it.
     *
     * A filter list ordered by `reorder` alone put «واي فاي مجاني» above «غرفة
     * مزدوجة» — a facility above the thing being paid for. The role already
     * says which is which, so the sort follows it and `reorder` decides only
     * WITHIN a tier, which is what an admin actually curates.
     */
    public const ROLE_RANK = [
        self::ROLE_LINE => 0,
        self::ROLE_MODIFIER => 1,
        self::ROLE_DESCRIPTIVE => 2,
    ];

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

    /**
     * The one ordering. Applied to a query over `option_groups` directly, or —
     * by passing the table/alias the columns live under — to a join that
     * carries them.
     */
    public function scopeInDisplayOrder($query, string $table = 'option_groups')
    {
        return $query
            ->orderByRaw(self::displayOrderSql($table))
            ->orderByRaw("COALESCE({$table}.reorder, 999999) ASC")
            ->orderBy("{$table}.id");
    }

    /** The role-rank expression, for callers that build their own ORDER BY. */
    public static function displayOrderSql(string $table = 'option_groups'): string
    {
        $whens = '';

        foreach (self::ROLE_RANK as $role => $rank) {
            $whens .= " WHEN '{$role}' THEN {$rank}";
        }

        // Anything unrecognised sorts with the descriptive tail rather than
        // ahead of the priced groups — an unknown role is not a claim to sell.
        return "CASE {$table}.price_role{$whens} ELSE 99 END ASC";
    }

    /** Where this group sits in the three tiers. */
    public function roleRank(): int
    {
        return self::ROLE_RANK[$this->price_role] ?? 99;
    }

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