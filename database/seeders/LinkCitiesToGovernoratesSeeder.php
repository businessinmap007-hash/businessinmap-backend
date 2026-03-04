<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\City;

class LinkCitiesToGovernoratesSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = database_path('seeders/osm/egypt.json');

        if (!file_exists($jsonPath)) {
            $this->command->error('❌ egypt.json not found');
            return;
        }

        $json = json_decode(file_get_contents($jsonPath), true);

        // 1️⃣ خريطة المحافظات الرسمية (من جدولك)
        $governorates = DB::table('governorates')->get();
        $govByArabicName = [];

        foreach ($governorates as $gov) {
            $govByArabicName[$gov->name_ar] = $gov->id;
        }

        // 2️⃣ Mapping OSM → الاسم العربي الرسمي
        $osmToArabicGovernorate = [
            'al qahirah'       => 'القاهرة',
            'cairo'            => 'القاهرة',
            'cairo governorate'=> 'القاهرة',

            'al jizah'         => 'الجيزة',
            'giza'             => 'الجيزة',

            'al ismailiyah'    => 'الإسماعيلية',
            'ismailia'         => 'الإسماعيلية',

            'ash sharqiyah'    => 'الشرقية',
            'sharqia'          => 'الشرقية',

            'ad daqahliyah'    => 'الدقهلية',
            'dakahlia'         => 'الدقهلية',

            'al gharbiyah'     => 'الغربية',
            'gharbia'          => 'الغربية',

            'al minya'         => 'المنيا',
            'minya'            => 'المنيا',

            'as suways'        => 'السويس',
            'suez'             => 'السويس',

            'bur sa\'id'       => 'بورسعيد',
            'port said'        => 'بورسعيد',

            'dumyat'           => 'دمياط',
            'damietta'         => 'دمياط',

            'aswan'            => 'أسوان',
            'luxor'            => 'الأقصر',

            'al fayyum'        => 'الفيوم',
            'faiyum'           => 'الفيوم',

            'beni suef'        => 'بني سويف',

            'asyut'            => 'أسيوط',

            'sohag'            => 'سوهاج',

            'qena'             => 'قنا',

            'new valley'       => 'الوادي الجديد',
            'al wadi al jadid' => 'الوادي الجديد',

            'matrouh'          => 'مطروح',

            'north sinai'      => 'شمال سيناء',
            'south sinai'      => 'جنوب سيناء',

            'red sea'          => 'البحر الأحمر',

            'qaliubiya'        => 'القليوبية',
        ];

        $updated = 0;
        $skipped = 0;

        foreach ($json['elements'] as $el) {

            if (!isset($el['tags'], $el['lat'], $el['lon'])) {
                continue;
            }

            $tags = $el['tags'];

            $cityName = $tags['name:ar'] ?? $tags['name'] ?? null;
            if (!$cityName) {
                continue;
            }

            $state =
                $tags['addr:state']
                ?? $tags['is_in:state']
                ?? $tags['addr:province']
                ?? null;

            if (!$state) {
                $skipped++;
                continue;
            }

            $stateKey = mb_strtolower(trim($state));

            if (!isset($osmToArabicGovernorate[$stateKey])) {
                $skipped++;
                continue;
            }

            $arabicGovName = $osmToArabicGovernorate[$stateKey];

            if (!isset($govByArabicName[$arabicGovName])) {
                $skipped++;
                continue;
            }

            $govId = $govByArabicName[$arabicGovName];

            $affected = City::where('name_ar', trim($cityName))
                ->where('latitude', $el['lat'])
                ->where('longitude', $el['lon'])
                ->update([
                    'governorate_id' => $govId
                ]);

            if ($affected) {
                $updated++;
            }
        }

        $this->command->info("✅ Cities linked to governorates: {$updated}");
        $this->command->warn("⚠ Cities skipped: {$skipped}");
    }
}
