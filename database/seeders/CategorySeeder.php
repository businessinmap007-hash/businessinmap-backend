<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $data = include database_path('seeders/data/categories.php');

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        DB::table('categories')->truncate();
        DB::table('categories')->insert($data);

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
