<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoryOptionSeeder extends Seeder
{
    public function run()
    {
        $data = include database_path('seeders/data/category_option.php');

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('category_option')->truncate();
        DB::table('category_option')->insert($data);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;'); 
    }
}
