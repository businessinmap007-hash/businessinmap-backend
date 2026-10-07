<?php

namespace Database\Seeders;

use App\Models\FoodCategory;
use App\Models\FoodItem;
use Illuminate\Database\Seeder;

/**
 * «جدول التغذية» — the shared food catalogue a nutrition specialist picks a meal from.
 *
 *     php artisan db:seed --class=FoodLibrarySeeder
 *
 * Add-only ([[seeder-must-withdraw]]): a section or a food that exists is never rewritten, so what the platform's
 * curators changed survives a re-seed. A specialist's own foods (`owner_id` set) are never touched. The data is
 * database/seeders/data/food_library.php.
 */
class FoodLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__ . '/data/food_library.php';
        $added = 0;
        $order = 0;

        foreach ($data as $categoryName => $foods) {
            $category = FoodCategory::query()->firstOrCreate(
                ['name_ar' => $categoryName],
                ['sort_order' => ++$order, 'is_active' => true]
            );

            foreach ($foods as $i => [$ar, $en, $serving, $grams, $kcal, $protein, $carbs, $fat]) {
                $food = FoodItem::query()->firstOrCreate(
                    ['food_category_id' => $category->id, 'name_ar' => $ar, 'owner_id' => null],
                    [
                        'name_en' => $en, 'serving_label' => $serving, 'serving_grams' => $grams, 'calories' => $kcal,
                        'protein_g' => $protein, 'carbs_g' => $carbs, 'fat_g' => $fat, 'sort_order' => $i + 1, 'is_active' => true,
                    ]
                );

                $added += $food->wasRecentlyCreated ? 1 : 0;
            }
        }

        $this->command?->info("Food library: {$added} أصناف أُضيفت · الإجمالى " . FoodItem::query()->whereNull('owner_id')->count());
    }
}
