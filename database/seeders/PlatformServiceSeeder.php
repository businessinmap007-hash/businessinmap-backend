<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlatformService;

class PlatformServiceSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'key' => 'booking',
                'name_ar' => 'الحجوزات',
                'name_en' => 'Booking',
                'is_active' => 1,
                'supports_deposit' => 1,
                'max_deposit_percent' => 20,
                'fee_type' => 'percent',
                'fee_value' => 5,
                'rules' => null,
            ],
            [
                'key' => 'menu',
                'name_ar' => 'المنيو',
                'name_en' => 'Menu',
                'is_active' => 1,
                'supports_deposit' => 0,
                'max_deposit_percent' => 0,
                'fee_type' => 'percent',
                'fee_value' => 3,
                'rules' => null,
            ],
            [
                'key' => 'delivery',
                'name_ar' => 'التوصيل',
                'name_en' => 'Delivery',
                'is_active' => 1,
                'supports_deposit' => 0,
                'max_deposit_percent' => 0,
                'fee_type' => 'fixed',
                'fee_value' => 10,
                'rules' => null,
            ],
        ];

        foreach ($items as $item) {
            PlatformService::updateOrCreate(
                ['key' => $item['key']],
                $item
            );
        }
    }
}