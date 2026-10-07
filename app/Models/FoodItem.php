<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry of the food catalogue a nutrition specialist picks from. `owner_id` NULL is the shared catalogue; a user id
 * is that specialist's own entry — they alone see, edit and delete it.
 */
class FoodItem extends Model
{
    protected $table = 'food_library';

    protected $fillable = [
        'food_category_id', 'owner_id', 'name_ar', 'name_en', 'serving_label', 'serving_grams',
        'calories', 'protein_g', 'carbs_g', 'fat_g', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'protein_g' => 'float',
        'carbs_g' => 'float',
        'fat_g' => 'float',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FoodCategory::class, 'food_category_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** The shared catalogue plus this user's own entries — never anyone else's. */
    public function scopeVisibleTo(Builder $q, int $userId): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('owner_id')->orWhere('owner_id', $userId));
    }

    public function label(): string
    {
        $ar = trim((string) $this->name_ar);
        $en = trim((string) $this->name_en);
        $primary = app()->getLocale() === 'en' ? $en : $ar;

        return $primary !== '' ? $primary : ($ar !== '' ? $ar : $en);
    }

    /**
     * Fill a new plan/template meal from its catalogue entry: the name and the calories (the entry's × the servings).
     * The numbers come from the catalogue, never from the client — «يجب ان يختار من الاصناف حتى تكون نسبة الخطأ صفر».
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public static function withDefaults(array $data, int $userId): array
    {
        if (empty($data['food_id'])) {
            unset($data['food_id'], $data['servings']);

            return $data;
        }

        $food = static::query()->active()->visibleTo($userId)->find($data['food_id']);

        if (! $food) {
            unset($data['food_id'], $data['servings']);

            return $data;
        }

        $servings = round(max((float) ($data['servings'] ?? 1), 0.25), 2);
        $data['servings'] = $servings;
        $data['name'] = trim((string) ($data['name'] ?? '')) !== '' ? $data['name'] : $food->name_ar;
        $data['calories'] = (int) round($food->calories * $servings);

        return $data;
    }
}
