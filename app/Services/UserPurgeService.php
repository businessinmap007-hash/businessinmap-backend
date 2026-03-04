<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserPurgeService
{
    public function purge(int $userId): void
    {
        // داخل transaction خارجي (من الكنترولر) أو هنا لو حابب
        $tablesUserId = [
            'addresses',
            'albums',
            'applies',
            'bookings',
            'business_gifts',
            'category_target',
            'comments',
            'couriers',
            'delivery_orders',
            'likes',
            'menu_carts',
            'notifications',
            'orders',
            'payments',
            'posts',
            'products',
            'ratings',
            'rides',
            'socials',
            'sponsors',
            'subscriptions',
            'target_user',
        ];

        foreach ($tablesUserId as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('user_id', $userId)->delete();
            }
        }

        // pivots
        $pivotTables = ['category_user', 'option_user', 'follow_user'];
        foreach ($pivotTables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('user_id', $userId)->delete();
            }
        }

        // لو عندك follow_user فيها follower_id / following_id أضف هنا:
        // if (Schema::hasTable('follow_user')) {
        //     DB::table('follow_user')->where('follower_id', $userId)->orWhere('following_id', $userId)->delete();
        // }
    }
}
