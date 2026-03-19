<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\PlatformService;
use App\Models\CategoryPlatformService;
use App\Models\CategoryBookingProfile;

class CategoryPlatformServiceSeeder extends Seeder
{
    public function run(): void
    {
        $booking  = PlatformService::where('key', 'booking')->first();
        $menu     = PlatformService::where('key', 'menu')->first();
        $delivery = PlatformService::where('key', 'delivery')->first();

        $hotel = Category::where('slug', 'hotel')->orWhere('name_en', 'hotel')->first();
        $restaurant = Category::where('slug', 'restaurant')->orWhere('name_en', 'restaurant')->first();
        $sports = Category::where('slug', 'sports-field')->orWhere('name_en', 'sports field')->first();

        if ($hotel && $booking) {
            CategoryPlatformService::updateOrCreate(
                [
                    'category_id' => $hotel->id,
                    'platform_service_id' => $booking->id,
                ],
                [
                    'is_active' => true,
                    'sort_order' => 1,
                ]
            );

            CategoryBookingProfile::updateOrCreate(
                [
                    'category_id' => $hotel->id,
                    'platform_service_id' => $booking->id,
                ],
                [
                    'is_active' => true,
                    'booking_mode' => CategoryBookingProfile::MODE_NIGHTLY,
                    'item_family' => 'hotel_room',
                    'requires_bookable_item' => true,
                    'requires_start_end' => true,
                    'supports_quantity' => true,
                    'supports_guest_count' => true,
                    'supports_extras' => true,
                    'allowed_item_types' => ['single_room', 'double_room', 'suite', 'family_room'],
                    'required_fields' => ['check_in', 'check_out', 'guests'],
                ]
            );
        }

        if ($restaurant) {
            if ($booking) {
                CategoryPlatformService::updateOrCreate(
                    [
                        'category_id' => $restaurant->id,
                        'platform_service_id' => $booking->id,
                    ],
                    [
                        'is_active' => true,
                        'sort_order' => 1,
                    ]
                );

                CategoryBookingProfile::updateOrCreate(
                    [
                        'category_id' => $restaurant->id,
                        'platform_service_id' => $booking->id,
                    ],
                    [
                        'is_active' => true,
                        'booking_mode' => CategoryBookingProfile::MODE_SLOT,
                        'item_family' => 'table',
                        'requires_bookable_item' => true,
                        'requires_start_end' => true,
                        'supports_quantity' => false,
                        'supports_guest_count' => true,
                        'supports_extras' => false,
                        'allowed_item_types' => ['table_2', 'table_4', 'table_6', 'vip_table'],
                        'required_fields' => ['reservation_time', 'guests'],
                    ]
                );
            }

            if ($menu) {
                CategoryPlatformService::updateOrCreate(
                    [
                        'category_id' => $restaurant->id,
                        'platform_service_id' => $menu->id,
                    ],
                    [
                        'is_active' => true,
                        'sort_order' => 2,
                    ]
                );
            }

            if ($delivery) {
                CategoryPlatformService::updateOrCreate(
                    [
                        'category_id' => $restaurant->id,
                        'platform_service_id' => $delivery->id,
                    ],
                    [
                        'is_active' => true,
                        'sort_order' => 3,
                    ]
                );
            }
        }

        if ($sports && $booking) {
            CategoryPlatformService::updateOrCreate(
                [
                    'category_id' => $sports->id,
                    'platform_service_id' => $booking->id,
                ],
                [
                    'is_active' => true,
                    'sort_order' => 1,
                ]
            );

            CategoryBookingProfile::updateOrCreate(
                [
                    'category_id' => $sports->id,
                    'platform_service_id' => $booking->id,
                ],
                [
                    'is_active' => true,
                    'booking_mode' => CategoryBookingProfile::MODE_HOURLY,
                    'item_family' => 'sports_field',
                    'requires_bookable_item' => true,
                    'requires_start_end' => true,
                    'supports_quantity' => false,
                    'supports_guest_count' => false,
                    'supports_extras' => false,
                    'allowed_item_types' => ['five_side_field', 'full_field', 'padel_court'],
                    'required_fields' => ['starts_at', 'ends_at'],
                ]
            );
        }
    }
}