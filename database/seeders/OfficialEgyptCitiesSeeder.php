<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OfficialEgyptCitiesSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * Official Administrative Cities of Egypt
         * Source: CAPMAS + official governorate portals
         * ISO: ISO-3166-2:EG (Governorate level)
         */

        $data = [

            // ===============================
            // Cairo — EG-C
            // ===============================
            'القاهرة' => [
                'iso' => 'EG-C',
                'cities' => [
                    ['ar'=>'القاهرة','en'=>'Cairo'],
                    ['ar'=>'مدينة نصر','en'=>'Nasr City'],
                    ['ar'=>'المعادي','en'=>'Maadi'],
                    ['ar'=>'حلوان','en'=>'Helwan'],
                    ['ar'=>'شبرا','en'=>'Shubra'],
                    ['ar'=>'عين شمس','en'=>'Ain Shams'],
                    ['ar'=>'المطرية','en'=>'El Mataria'],
                    ['ar'=>'القاهرة الجديدة','en'=>'New Cairo'],
                    ['ar'=>'الشروق','en'=>'El Shorouk'],
                    ['ar'=>'بدر','en'=>'Badr City'],
                ],
            ],

            // ===============================
            // Giza — EG-GZ
            // ===============================
            'الجيزة' => [
                'iso' => 'EG-GZ',
                'cities' => [
                    ['ar'=>'الجيزة','en'=>'Giza'],
                    ['ar'=>'6 أكتوبر','en'=>'6th of October'],
                    ['ar'=>'الشيخ زايد','en'=>'Sheikh Zayed'],
                    ['ar'=>'البدرشين','en'=>'Al Badrashin'],
                    ['ar'=>'العياط','en'=>'Al Ayyat'],
                    ['ar'=>'أبو النمرس','en'=>'Abu El Nomros'],
                    ['ar'=>'كرداسة','en'=>'Kerdasa'],
                    ['ar'=>'أوسيم','en'=>'Awsim'],
                    ['ar'=>'الصف','en'=>'El Saff'],
                    ['ar'=>'أطفيح','en'=>'Atfih'],
                ],
            ],

            // ===============================
            // Alexandria — EG-ALX
            // ===============================
            'الإسكندرية' => [
                'iso' => 'EG-ALX',
                'cities' => [
                    ['ar'=>'الإسكندرية','en'=>'Alexandria'],
                    ['ar'=>'برج العرب','en'=>'Borg El Arab'],
                    ['ar'=>'برج العرب الجديدة','en'=>'New Borg El Arab'],
                ],
            ],

            // ===============================
            // Dakahlia — EG-DK
            // ===============================
            'الدقهلية' => [
                'iso' => 'EG-DK',
                'cities' => [
                    ['ar'=>'المنصورة','en'=>'Mansoura'],
                    ['ar'=>'طلخا','en'=>'Talkha'],
                    ['ar'=>'ميت غمر','en'=>'Mit Ghamr'],
                    ['ar'=>'دكرنس','en'=>'Dikirnis'],
                    ['ar'=>'أجا','en'=>'Aga'],
                    ['ar'=>'السنبلاوين','en'=>'El Senbellawein'],
                    ['ar'=>'المنزلة','en'=>'El Manzala'],
                ],
            ],

            // ===============================
            // Damietta — EG-DT
            // ===============================
            'دمياط' => [
                'iso' => 'EG-DT',
                'cities' => [
                    ['ar'=>'دمياط','en'=>'Damietta'],
                    ['ar'=>'دمياط الجديدة','en'=>'New Damietta'],
                    ['ar'=>'رأس البر','en'=>'Ras El Bar'],
                    ['ar'=>'كفر سعد','en'=>'Kafr Saad'],
                    ['ar'=>'كفر البطيخ','en'=>'Kafr El-Bateekh'],
                    ['ar'=>'فارسكور','en'=>'Farskur'],
                    ['ar'=>'الزرقا','en'=>'Al Zarqa'],
                    ['ar'=>'ميت أبو غالب','en'=>'Meet Abou Ghalib'],
                    ['ar'=>'الروضة','en'=>'Al Rawda'],
                    ['ar'=>'عزبة البرج','en'=>'Ezbet El Borg'],
                ],
            ],

            // ===============================
            // Sharqia — EG-SHR
            // ===============================
            'الشرقية' => [
                'iso' => 'EG-SHR',
                'cities' => [
                    ['ar'=>'الزقازيق','en'=>'Zagazig'],
                    ['ar'=>'بلبيس','en'=>'Belbeis'],
                    ['ar'=>'منيا القمح','en'=>'Minya El Qamh'],
                    ['ar'=>'العاشر من رمضان','en'=>'10th of Ramadan'],
                    ['ar'=>'أبو كبير','en'=>'Abu Kabir'],
                    ['ar'=>'ههيا','en'=>'Hehia'],
                ],
            ],

            // ===============================
            // Qalyubia — EG-KB
            // ===============================
            'القليوبية' => [
                'iso' => 'EG-KB',
                'cities' => [
                    ['ar'=>'بنها','en'=>'Banha'],
                    ['ar'=>'قليوب','en'=>'Qalyub'],
                    ['ar'=>'شبرا الخيمة','en'=>'Shubra El Kheima'],
                    ['ar'=>'القناطر الخيرية','en'=>'El Qanater El Khayreya'],
                    ['ar'=>'الخانكة','en'=>'El Khanka'],
                ],
            ],

            // ===============================
            // Monufia — EG-MNF
            // ===============================
            'المنوفية' => [
                'iso' => 'EG-MNF',
                'cities' => [
                    ['ar'=>'شبين الكوم','en'=>'Shebin El Kom'],
                    ['ar'=>'منوف','en'=>'Menouf'],
                    ['ar'=>'السادات','en'=>'Sadat City'],
                    ['ar'=>'أشمون','en'=>'Ashmoun'],
                ],
            ],

            // ===============================
            // Gharbia — EG-GH
            // ===============================
            'الغربية' => [
                'iso' => 'EG-GH',
                'cities' => [
                    ['ar'=>'طنطا','en'=>'Tanta'],
                    ['ar'=>'المحلة الكبرى','en'=>'El Mahalla El Kubra'],
                    ['ar'=>'كفر الزيات','en'=>'Kafr El Zayat'],
                    ['ar'=>'زفتى','en'=>'Zefta'],
                ],
            ],

            // ===============================
            // Kafr El Sheikh — EG-KFS
            // ===============================
            'كفر الشيخ' => [
                'iso' => 'EG-KFS',
                'cities' => [
                    ['ar'=>'كفر الشيخ','en'=>'Kafr El Sheikh'],
                    ['ar'=>'دسوق','en'=>'Desouk'],
                    ['ar'=>'سيدي سالم','en'=>'Sidi Salem'],
                ],
            ],

            // ===============================
            // Beheira — EG-BH
            // ===============================
            'البحيرة' => [
                'iso' => 'EG-BH',
                'cities' => [
                    ['ar'=>'دمنهور','en'=>'Damanhour'],
                    ['ar'=>'كفر الدوار','en'=>'Kafr El Dawwar'],
                    ['ar'=>'رشيد','en'=>'Rosetta'],
                ],
            ],

            // ===============================
            // Fayoum — EG-FYM
            // ===============================
            'الفيوم' => [
                'iso' => 'EG-FYM',
                'cities' => [
                    ['ar'=>'الفيوم','en'=>'Faiyum'],
                    ['ar'=>'سنورس','en'=>'Senoures'],
                    ['ar'=>'إطسا','en'=>'Itsa'],
                ],
            ],

            // ===============================
            // Beni Suef — EG-BNS
            // ===============================
            'بني سويف' => [
                'iso' => 'EG-BNS',
                'cities' => [
                    ['ar'=>'بني سويف','en'=>'Beni Suef'],
                    ['ar'=>'الواسطى','en'=>'Al Wasta'],
                    ['ar'=>'ناصر','en'=>'Naser'],
                ],
            ],

            // ===============================
            // Minya — EG-MN
            // ===============================
            'المنيا' => [
                'iso' => 'EG-MN',
                'cities' => [
                    ['ar'=>'المنيا','en'=>'Minya'],
                    ['ar'=>'ملوي','en'=>'Mallawi'],
                    ['ar'=>'سمالوط','en'=>'Samalut'],
                ],
            ],

            // ===============================
            // Assiut — EG-AST
            // ===============================
            'أسيوط' => [
                'iso' => 'EG-AST',
                'cities' => [
                    ['ar'=>'أسيوط','en'=>'Assiut'],
                    ['ar'=>'ديروط','en'=>'Dairut'],
                    ['ar'=>'منفلوط','en'=>'Manfalut'],
                ],
            ],

            // ===============================
            // Sohag — EG-SHG
            // ===============================
            'سوهاج' => [
                'iso' => 'EG-SHG',
                'cities' => [
                    ['ar'=>'سوهاج','en'=>'Sohag'],
                    ['ar'=>'أخميم','en'=>'Akhmim'],
                    ['ar'=>'جرجا','en'=>'Girga'],
                ],
            ],

            // ===============================
            // Qena — EG-QN
            // ===============================
            'قنا' => [
                'iso' => 'EG-QN',
                'cities' => [
                    ['ar'=>'قنا','en'=>'Qena'],
                    ['ar'=>'نجع حمادي','en'=>'Nag Hammadi'],
                ],
            ],

            // ===============================
            // Luxor — EG-LX
            // ===============================
            'الأقصر' => [
                'iso' => 'EG-LX',
                'cities' => [
                    ['ar'=>'الأقصر','en'=>'Luxor'],
                ],
            ],

            // ===============================
            // Aswan — EG-ASN
            // ===============================
            'أسوان' => [
                'iso' => 'EG-ASN',
                'cities' => [
                    ['ar'=>'أسوان','en'=>'Aswan'],
                    ['ar'=>'كوم أمبو','en'=>'Kom Ombo'],
                ],
            ],

            // ===============================
            // Red Sea — EG-BA
            // ===============================
            'البحر الأحمر' => [
                'iso' => 'EG-BA',
                'cities' => [
                    ['ar'=>'الغردقة','en'=>'Hurghada'],
                    ['ar'=>'سفاجا','en'=>'Safaga'],
                    ['ar'=>'القصير','en'=>'El Qoseir'],
                ],
            ],

            // ===============================
            // Matrouh — EG-MT
            // ===============================
            'مطروح' => [
                'iso' => 'EG-MT',
                'cities' => [
                    ['ar'=>'مرسى مطروح','en'=>'Marsa Matruh'],
                    ['ar'=>'الحمام','en'=>'El Hammam'],
                ],
            ],

            // ===============================
            // New Valley — EG-WAD
            // ===============================
            'الوادي الجديد' => [
                'iso' => 'EG-WAD',
                'cities' => [
                    ['ar'=>'الخارجة','en'=>'Kharga'],
                    ['ar'=>'الداخلة','en'=>'Dakhla'],
                ],
            ],

            // ===============================
            // North Sinai — EG-NS
            // ===============================
            'شمال سيناء' => [
                'iso' => 'EG-NS',
                'cities' => [
                    ['ar'=>'العريش','en'=>'Arish'],
                ],
            ],

            // ===============================
            // South Sinai — EG-SS
            // ===============================
            'جنوب سيناء' => [
                'iso' => 'EG-SS',
                'cities' => [
                    ['ar'=>'شرم الشيخ','en'=>'Sharm El Sheikh'],
                    ['ar'=>'دهب','en'=>'Dahab'],
                    ['ar'=>'نويبع','en'=>'Nuweiba'],
                    ['ar'=>'طابا','en'=>'Taba'],
                ],
            ],

            // ===============================
            // Ismailia — EG-IS
            // ===============================
            'الإسماعيلية' => [
                'iso' => 'EG-IS',
                'cities' => [
                    ['ar'=>'الإسماعيلية','en'=>'Ismailia'],
                    ['ar'=>'فايد','en'=>'Fayed'],
                ],
            ],

            // ===============================
            // Suez — EG-SUZ
            // ===============================
            'السويس' => [
                'iso' => 'EG-SUZ',
                'cities' => [
                    ['ar'=>'السويس','en'=>'Suez'],
                ],
            ],

            // ===============================
            // Port Said — EG-PTS
            // ===============================
            'بورسعيد' => [
                'iso' => 'EG-PTS',
                'cities' => [
                    ['ar'=>'بورسعيد','en'=>'Port Said'],
                    ['ar'=>'بورفؤاد','en'=>'Port Fuad'],
                ],
            ],
        ];

        foreach ($data as $govAr => $govData) {

            $governorate = DB::table('governorates')
                ->where('name_ar', $govAr)
                ->first();

            if (!$governorate) {
                $this->command->warn("❌ Governorate not found: {$govAr}");
                continue;
            }

            foreach ($govData['cities'] as $city) {
                DB::table('cities')
                    ->where('name_ar', $city['ar'])
                    ->update([
                        'governorate_id' => $governorate->id,
                        'name_en'        => $city['en'],
                    ]);
            }

            $this->command->info("✅ {$govAr} ({$govData['iso']}) done");
        }

        $this->command->info('🏁 All Egyptian governorates processed successfully');
    }
}
