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
        'rehab' => ['ar' => 'علاجي وتأهيلي', 'en' => 'Therapeutic / rehab'],
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
        'default_sets', 'default_reps', 'instructions', 'owner_id', 'sort_order', 'is_active',
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
     * Fill a new plan/template exercise from its catalogue entry: the name
     * when the trainer typed none, and the suggested sets/reps when unset.
     * Explicit values always win — the entry is a starting point.
     */
    public static function withDefaults(array $data, ?int $userId = null): array
    {
        if (empty($data['library_exercise_id'])) {
            unset($data['library_exercise_id']);

            return $data;
        }

        // an entry is the shared catalogue or the caller's own; another specialist's is not reachable
        $entry = static::query()->active()->visibleTo((int) $userId)->find($data['library_exercise_id']);

        if (! $entry) {
            unset($data['library_exercise_id']);

            return $data;
        }

        if (trim((string) ($data['name'] ?? '')) === '') {
            $data['name'] = $entry->name_ar;
        }
        $data['sets'] ??= $entry->default_sets;
        $data['reps'] ??= $entry->default_reps;
        if (trim((string) ($data['notes'] ?? '')) === '' && trim((string) $entry->instructions) !== '') {
            $data['notes'] = $entry->instructions;
        }

        return $data;
    }

    /** kind / equipment key => label in the request locale. */
    public static function labels(array $map): array
    {
        $key = app()->getLocale() === 'en' ? 'en' : 'ar';

        return collect($map)->map(fn ($names, $k) => ['key' => $k, 'label' => $names[$key]])->values()->all();
    }
}
