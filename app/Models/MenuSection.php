<?php

namespace App\Models;

use App\Support\Concerns\HasLocalizedFields;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named group of menu items within one business's menu (مقبلات / رئيسي /
 * حلويات …). Owner-managed via the business panel; surfaced to customers by
 * MenuDiscoveryController.
 */
class MenuSection extends Model
{
    use HasLocalizedFields;

    protected $table = 'menu_sections';

    protected $fillable = [
        'business_id',
        'option_group_id',
        'name_ar',
        'name_en',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'option_group_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    /**
     * Null for a section the owner typed by hand (a restaurant's "مقبلات").
     * Set for one grown from a `line` option group — {@see
     * \App\Services\Menu\MenuSectionFromOptionGroup}.
     */
    public function optionGroup(): BelongsTo
    {
        return $this->belongsTo(OptionGroup::class, 'option_group_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'menu_section_id');
    }

    public function scopeForBusiness(Builder $query, ?int $businessId): Builder
    {
        if (! $businessId) {
            return $query;
        }

        return $query->where('business_id', $businessId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->name_ar ?: ($this->name_en ?: ('Section #' . $this->id)));
    }
}
