<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the `order` dispute type. A menu order carries no escrow deposit, so a
 * dispute over one is neither a `deposit` nor a `booking` — it needs its own
 * label. Without it the order-dispute door (POST /orders/{order}/disputes) hit
 * "Data truncated for column 'type'" the moment it tried to persist.
 */
return new class extends Migration
{
    private const WITH_ORDER = "enum('booking','deposit','external_deposit','wallet_hold','order')";
    private const WITHOUT_ORDER = "enum('booking','deposit','external_deposit','wallet_hold')";

    public function up(): void
    {
        DB::statement('ALTER TABLE disputes MODIFY COLUMN `type` ' . self::WITH_ORDER . " NOT NULL DEFAULT 'booking'");
    }

    public function down(): void
    {
        // Any order disputes must fall back to a value the old enum accepts,
        // or the narrowing ALTER would truncate them.
        DB::table('disputes')->where('type', 'order')->update(['type' => 'deposit']);
        DB::statement('ALTER TABLE disputes MODIFY COLUMN `type` ' . self::WITHOUT_ORDER . " NOT NULL DEFAULT 'booking'");
    }
};
