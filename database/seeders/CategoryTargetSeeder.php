<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoryTargetSeeder extends Seeder
{
    public function run()
    {
        $data = include database_path('seeders/data/category_target.php');
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('category_target')->truncate();
        DB::table('category_target')->insert($data);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
