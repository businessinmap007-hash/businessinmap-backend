<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The main airports as places of their own (owner, 2026-10-25: a driver going from Damietta to Cairo airport comes back
 * from the AIRPORT, not from «Cairo»): a ride, a request or an empty return can start or end at one. They sit in their
 * governorate's city list, so every place picker and the distance quote already work with them. Idempotent.
 */
return new class extends Migration
{
    /** @var list<array{0:int,1:string,2:string,3:float,4:float}> governorate id, Arabic, English, latitude, longitude */
    private array $airports = [
        [1, 'مطار القاهرة الدولي', 'Cairo International Airport', 30.1219, 31.4056],
        [3, 'مطار برج العرب الدولي', 'Borg El Arab International Airport', 30.9177, 29.6964],
        [23, 'مطار العلمين الدولي', 'El Alamein International Airport', 30.9244, 28.4614],
        [25, 'مطار شرم الشيخ الدولي', 'Sharm El Sheikh International Airport', 27.9773, 34.3949],
        [25, 'مطار طابا الدولي', 'Taba International Airport', 29.5878, 34.7781],
        [26, 'مطار الغردقة الدولي', 'Hurghada International Airport', 27.1783, 33.7994],
        [26, 'مطار مرسى علم الدولي', 'Marsa Alam International Airport', 25.5571, 34.5837],
        [20, 'مطار الأقصر الدولي', 'Luxor International Airport', 25.6710, 32.7066],
        [21, 'مطار أسوان الدولي', 'Aswan International Airport', 23.9644, 32.8200],
        [21, 'مطار أبو سمبل', 'Abu Simbel Airport', 22.3676, 31.6117],
    ];

    public function up(): void
    {
        foreach ($this->airports as [$governorate, $ar, $en, $lat, $lng]) {
            if (! DB::table('governorates')->where('id', $governorate)->exists()) {
                continue;
            }

            if (DB::table('cities')->where('governorate_id', $governorate)->where('name_ar', $ar)->exists()) {
                continue;
            }

            DB::table('cities')->insert([
                'governorate_id' => $governorate, 'sort_order' => 0, 'name_ar' => $ar, 'name_en' => $en,
                'latitude' => $lat, 'longitude' => $lng, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->airports as [$governorate, $ar]) {
            DB::table('cities')->where('governorate_id', $governorate)->where('name_ar', $ar)->delete();
        }
    }
};
