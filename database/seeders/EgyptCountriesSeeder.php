<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EgyptCountriesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('countries')->updateOrInsert(
            ['id' => 1],
            [
                'name_ar'    => 'مصر',
                'name_en'    => 'Egypt',
                'iso2'       => 'EG',
                'iso3'       => 'EGY',
                'phone_code' => '+20',
                'currency'   => 'EGP',
                'latitude'   => 26.8206,
                'longitude'  => 30.8025,
                'created_at'=> now(),
                'updated_at'=> now(),
            ]
        );

        $this->command->info('✅ Egypt country seeded');
    }
}
