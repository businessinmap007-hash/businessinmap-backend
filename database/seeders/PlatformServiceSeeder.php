<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlatformService;

class PlatformServiceSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['key'=>'booking','name_ar'=>'الحجوزات','name_en'=>'Booking','supports_deposit'=>1,'max_deposit_percent'=>20,'is_active'=>1],
            ['key'=>'menu','name_ar'=>'المنيو والطلبات','name_en'=>'Menu','supports_deposit'=>0,'max_deposit_percent'=>0,'is_active'=>1],
            ['key'=>'delivery','name_ar'=>'التوصيل','name_en'=>'Delivery','supports_deposit'=>0,'max_deposit_percent'=>0,'is_active'=>1],
        ];

        foreach ($items as $it) {
            PlatformService::updateOrCreate(['key'=>$it['key']], $it);
        }
    }
}