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
            [
                'key' => 'retail',
                'name_ar' => 'التجزئة',
                'name_en' => 'Retail',
                'is_active' => true,
                'sort_order' => 4,
                'supports_deposit' => false,
                'max_deposit_percent' => 0,
            ],
            [
                'key' => 'schedules',
                'name_ar' => 'الجدولة والخطوط',
                'name_en' => 'Scheduling & Routes',
                'is_active' => true,
                'sort_order' => 5,
                'supports_deposit' => true,
                'max_deposit_percent' => 100,
            ],
            [
                // A gym or a coach sells the PLAN, and until now could only
                // deliver one: `training` was a staff capability (who may
                // manage it) and nothing said the business offers it at all,
                // so no customer could find it and no price could be set.
                'key' => 'training',
                'name_ar' => 'التدريب والتغذية',
                'name_en' => 'Training & Nutrition',
                'is_active' => true,
                'sort_order' => 6,
                // A month is paid for before it is trained.
                'supports_deposit' => true,
                'max_deposit_percent' => 100,
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