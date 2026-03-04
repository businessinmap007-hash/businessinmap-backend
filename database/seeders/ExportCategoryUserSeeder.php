<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExportCategoryUserSeeder extends Seeder
{
    public function run()
    {
        $rows = DB::table('category_user')->get();
        $data = [];

        foreach ($rows as $row) {
            $data[] = [
                'id'         => $row->id,
                'user_id'    => $row->user_id,
                'category_id'=> $row->category_id,
            ];
        }

        file_put_contents(
            database_path('seeders/data/category_user.php'),
            "<?php\n\nreturn " . var_export($data, true) . ";\n"
        );

        echo "✔ File created: seeders/data/category_user.php\n";
    }
}
