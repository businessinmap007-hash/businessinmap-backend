<?php

namespace Database\Seeders;

use App\Models\ExerciseCategory;
use App\Models\LibraryExercise;
use Illuminate\Database\Seeder;

/**
 * «جدول التمارين العلاجية» — the therapeutic exercises a rehabilitation specialist picks from, next to the gym
 * catalogue that already existed.
 *
 *     php artisan db:seed --class=TherapeuticExercisesSeeder
 *
 * Add-only: a section or an exercise that exists is never rewritten, and a specialist's own exercises (`owner_id`
 * set) are never touched. The data is database/seeders/data/therapeutic_exercises.php.
 */
class TherapeuticExercisesSeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__ . '/data/therapeutic_exercises.php';
        $order = 1 + (int) ExerciseCategory::query()->max('sort_order');
        $added = 0;

        foreach ($data as $categoryName => $exercises) {
            $category = ExerciseCategory::query()->firstOrCreate(
                ['name_ar' => $categoryName],
                ['sort_order' => $order++, 'is_active' => true]
            );

            foreach ($exercises as $i => [$ar, $en, $equipment, $sets, $reps, $instructions]) {
                $exercise = LibraryExercise::query()->firstOrCreate(
                    ['exercise_category_id' => $category->id, 'name_ar' => $ar, 'owner_id' => null],
                    [
                        'name_en' => $en, 'kind' => 'rehab', 'equipment' => $equipment, 'default_sets' => $sets,
                        'default_reps' => $reps, 'instructions' => $instructions, 'sort_order' => $i + 1, 'is_active' => true,
                    ]
                );

                $added += $exercise->wasRecentlyCreated ? 1 : 0;
            }
        }

        $this->command?->info("Therapeutic exercises: {$added} تمرينًا أُضيف · الإجمالى " . LibraryExercise::query()->whereNull('owner_id')->count());
    }
}
