<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EgyptGovernoratesSeeder extends Seeder
{
    public function run(): void
    {
        $governorates = [
            1  => ['القاهرة','Cairo'],
            2  => ['الجيزة','Giza'],
            3  => ['الإسكندرية','Alexandria'],
            4  => ['الدقهلية','Dakahlia'],
            5  => ['الشرقية','Sharqia'],
            6  => ['الغربية','Gharbia'],
            7  => ['المنوفية','Monufia'],
            8  => ['البحيرة','Beheira'],
            9  => ['كفر الشيخ','Kafr El Sheikh'],
            10 => ['دمياط','Damietta'],
            11 => ['بورسعيد','Port Said'],
            12 => ['الإسماعيلية','Ismailia'],
            13 => ['السويس','Suez'],
            14 => ['الفيوم','Fayoum'],
            15 => ['بني سويف','Beni Suef'],
            16 => ['المنيا','Minya'],
            17 => ['أسيوط','Assiut'],
            18 => ['سوهاج','Sohag'],
            19 => ['قنا','Qena'],
            20 => ['الأقصر','Luxor'],
            21 => ['أسوان','Aswan'],
            22 => ['الوادي الجديد','New Valley'],
            23 => ['مطروح','Matrouh'],
            24 => ['شمال سيناء','North Sinai'],
            25 => ['جنوب سيناء','South Sinai'],
            26 => ['البحر الأحمر','Red Sea'],
            27 => ['القليوبية','Qalyubia'],
        ];

        foreach ($governorates as $id => [$ar,$en]) {
            DB::table('governorates')->updateOrInsert(
                ['id' => $id],
                [
                    'country_id' => 1,
                    'name_ar'    => $ar,
                    'name_en'    => $en,
                    'created_at'=> now(),
                    'updated_at'=> now(),
                ]
            );
        }

        $this->command->info('✅ Egypt governorates seeded');
    }
}
