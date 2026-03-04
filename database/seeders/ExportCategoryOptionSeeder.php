<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExportCategoryOptionSeeder extends Seeder
{
    public function run()
    {
        $rows = DB::table('category_option')->get();
        $data = [];

        foreach ($rows as $row) {
            $data[] = [
                'id'         => $row->id,
                'category_id'=> $row->category_id,
                'option_id'  => $row->option_id,
            ];
        }

        file_put_contents(
            database_path('seeders/data/category_option.php'),
            "<?php\n\nreturn " . var_export($data, true) . ";\n"
        );

        echo "✔ File created: seeders/data/category_option.php\n";
    }
}
