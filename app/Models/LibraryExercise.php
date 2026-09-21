<?php

namespace App\Models;

use App\Models\Concerns\HasOwnedImages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry of the shared exercise catalogue a trainer picks from. */
class LibraryExercise extends Model
{
    /** Demonstration photos: rows AND files die with the exercise. */
    use HasOwnedImages;

    protected $table = 'exercise_library';

    public const KINDS = [
        'strength' => ['ar' => 'قوة', 'en' => 'Strength'],
        'cardio' => ['ar' => 'كارديو', 'en' => 'Cardio'],
        'flexibility' => ['ar' => 'مرونة وإطالة', 'en' => 'Flexibility'],
        'functional' => ['ar' => 'وظيفي', 'en' => 'Functional'],
        'warmup' => ['ar' => 'إحماء', 'en' => 'Warm-up'],
    ];

    public const EQUIPMENT = [
        'barbell' => ['ar' => 'بار', 'en' => 'Barbell'],
        'dumbbell' => ['ar' => 'دمبل', 'en' => 'Dumbbell'],
        'machine' => ['ar' => 'جهاز', 'en' => 'Machine'],
        'cable' => ['ar' => 'كابل', 'en' => 'Cable'],
        'bodyweight' => ['ar' => 'وزن الجسم', 'en' => 'Bodyweight'],
        'kettlebell' => ['ar' => 'كيتل بيل', 'en' => 'Kettlebell'],
        'band' => ['ar' => 'مطاط مقاومة', 'en' => 'Resistance band'],
        'other' => ['ar' => 'أخرى', 'en' => 'Other'],
    ];

    protected $fillable = [
        'exercise_category_id', 'name_ar', 'name_en', 'kind', 'equipment',
        'default_sets', 'default_reps', 'sort_order', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExerciseCategory::class, 'exercise_category_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function label(): string
    {
        $ar = trim((string) $this->name_ar);
        $en = trim((string) $this->name_en);
        $primary = app()->getLocale() === 'en' ? $en : $ar;

        return $primary !== '' ? $primary : ($ar !== '' ? $ar : $en);
    }

    /**
     * Fill a new plan/template exercise from its catalogue entry: the name
     * when the trainer typed none, and the suggested sets/reps when unset.
     * Explicit values always win — the entry is a starting point.
     */
    public static function withDefaults(array $data): array
    {
        if (empty($data['library_exercise_id'])) {
            unset($data['library_exercise_id']);

            return $data;
        }

        $entry = static::query()->active()->find($data['library_exercise_id']);

        if (! $entry) {
            unset($data['library_exercise_id']);

            return $data;
        }

        if (trim((string) ($data['name'] ?? '')) === '') {
            $data['name'] = $entry->name_ar;
        }
        $data['sets'] ??= $entry->default_sets;
        $data['reps'] ??= $entry->default_reps;

        return $data;
    }

    /** kind / equipment key => label in the request locale. */
    public static function labels(array $map): array
    {
        $key = app()->getLocale() === 'en' ? 'en' : 'ar';

        return collect($map)->map(fn ($names, $k) => ['key' => $k, 'label' => $names[$key]])->values()->all();
    }
}
