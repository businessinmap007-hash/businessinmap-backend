<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A one-time loyalty perk per guarantee level: holding (or upgrading to) a
 * level shaves a small amount off every platform fee until the total shaved
 * reaches the level's own price (required_locked_amount) — a bounded,
 * self-limiting discount, not a standing markdown. Admin sets ONE of the two
 * columns per level; fixed amount wins if both are set (see WalletFeeService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guarantee_levels', function (Blueprint $table) {
            $table->decimal('fee_discount_amount', 10, 2)->nullable()->after('meta');
            $table->decimal('fee_discount_percent', 5, 2)->nullable()->after('fee_discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('guarantee_levels', function (Blueprint $table) {
            $table->dropColumn(['fee_discount_amount', 'fee_discount_percent']);
        });
    }
};
