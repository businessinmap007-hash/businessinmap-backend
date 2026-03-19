<?php

namespace Database\Seeders;

use App\Models\PlatformService;
use Illuminate\Database\Seeder;

class PlatformServiceSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'key' => 'booking',
                'name_ar' => 'الحجز',
                'name_en' => 'Booking',
                'is_active' => true,
                'sort_order' => 1,
                'supports_deposit' => true,
                'max_deposit_percent' => 100,
            ],
            [
                'key' => 'menu',
                'name_ar' => 'القائمة',
                'name_en' => 'Menu',
                'is_active' => true,
                'sort_order' => 2,
                'supports_deposit' => false,
                'max_deposit_percent' => 0,
            ],
            [
                'key' => 'delivery',
                'name_ar' => 'التوصيل',
                'name_en' => 'Delivery',
                'is_active' => true,
                'sort_order' => 3,
                'supports_deposit' => false,
                'max_deposit_percent' => 0,
            ],
        ];

        foreach ($rows as $row) {
            PlatformService::updateOrCreate(
                ['key' => $row['key']],
                $row
            );
        }
    }
}