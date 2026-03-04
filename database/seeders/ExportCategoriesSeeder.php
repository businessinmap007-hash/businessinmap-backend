<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExportCategoriesSeeder extends Seeder
{
    public function run()
    {
        $rows = DB::table('categories')->get();

        $data = [];

        foreach ($rows as $row) {
            $data[] = [
                'id'         => $row->id,
                'parent_id'  => $row->parent_id,
                'image'      => $row->image,
                'is_active'  => $row->is_active,
                'per_month'  => $row->per_month,
                'per_year'   => $row->per_year,
                'reorder'    => $row->reorder,
                'name_ar'    => $row->name_ar,
                'name_en'    => $row->name_en,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        if (!is_dir(database_path('seeders/data'))) {
            mkdir(database_path('seeders/data'), 0755, true);
        }

        file_put_contents(
            database_path('seeders/data/categories.php'),
            "<?php\n\nreturn " . var_export($data, true) . ";\n"
        );

        echo "✔ File created: seeders/data/categories.php\n";
    }
}
