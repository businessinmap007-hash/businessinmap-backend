<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EgyptCitiesSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/cities.csv');

        if (!file_exists($path)) {
            $this->command->error('❌ cities.csv not found');
            return;
        }

        // تنظيف الجدول قبل الإدخال
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('cities')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle); // تجاهل الهيدر

        $rows = [];
        $inserted = 0;
        $skipped  = 0;

        while (($row = fgetcsv($handle)) !== false) {

            [
                $governorateId,
                $nameAr,
                $nameEn,
                $lat,
                $lon
            ] = $row;

            // تحقق بسيط
            if (!$governorateId || !$nameAr) {
                $skipped++;
                continue;
            }

            $rows[] = [
                'governorate_id' => (int) $governorateId,
                'name_ar'        => trim($nameAr),
                'name_en'        => $nameEn ?: null,
                'latitude'       => $lat ?: null,
                'longitude'      => $lon ?: null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            // إدخال دفعات
            if (count($rows) >= 1000) {
                DB::table('cities')->insert($rows);
                $inserted += count($rows);
                $rows = [];
            }
        }

        if (!empty($rows)) {
            DB::table('cities')->insert($rows);
            $inserted += count($rows);
        }

        fclose($handle);

        $this->command->info("✅ Cities inserted: {$inserted}");
        $this->command->warn("⚠ Cities skipped: {$skipped}");
    }
}
