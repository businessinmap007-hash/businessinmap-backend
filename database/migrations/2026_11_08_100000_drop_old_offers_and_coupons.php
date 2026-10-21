<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Owner, 2026-10-21: the V1 coupons and the old «commercial offers» system (ads, follows, boosts, tracking, B2B targeting) have no
 * use and are deleted — promotions (`promotions`) are the offers now, and they reach the price. Removes their tables; nothing else
 * read them (the partner allocations are read directly by the hotel-room search and by the booking).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['offer_follow_notifications', 'offer_follows', 'offer_tracking_events', 'offer_boost_purchases', 'offer_boost_packages', 'commercial_offer_targets', 'commercial_offers', 'coupons'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // not coming back
    }
};
