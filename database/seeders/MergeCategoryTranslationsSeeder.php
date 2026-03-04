<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MergeCategoryTranslationsSeeder extends Seeder
{
    public function run()
    {
        $categories = DB::table('categories')->get();

        foreach ($categories as $cat) {

            // الترجمة العربية
            $ar = DB::table('category_translations')
                ->where('category_id', $cat->id)
                ->where('locale', 'ar')
                ->first();

            // الترجمة الإنجليزية
            $en = DB::table('category_translations')
                ->where('category_id', $cat->id)
                ->where('locale', 'en')
                ->first();

            DB::table('categories')->where('id', $cat->id)->update([
                'name_ar' => $ar?->name ?? null,
                'name_en' => $en?->name ?? null,
            ]);
        }

        echo "✔ تم دمج الترجمات داخل جدول categories بنجاح.\n";
    }
}
