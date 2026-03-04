<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoryUserSeeder extends Seeder
{
    public function run()
    {
        $data = include database_path('seeders/data/category_user.php');
        
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('category_user')->truncate();
        DB::table('category_user')->insert($data);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
