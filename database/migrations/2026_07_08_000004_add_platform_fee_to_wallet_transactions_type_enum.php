<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WalletFeeService writes wallet_transactions.type = 'platform_fee', but the
 * enum never included it (the 2026_03_09 platform-fee upgrade added auxiliary
 * columns yet left the type enum untouched), so every platform-fee charge
 * failed with "Data truncated for column 'type'". Add the missing value.
 */
return new class extends Migration
{
    private const WITH_FEE = "ENUM('deposit','withdraw','hold','release','refund','adjustment','transfer','platform_fee')";

    private const WITHOUT_FEE = "ENUM('deposit','withdraw','hold','release','refund','adjustment','transfer')";

    public function up(): void
    {
        if (Schema::hasColumn('wallet_transactions', 'type')) {
            DB::statement('ALTER TABLE `wallet_transactions` MODIFY `type` ' . self::WITH_FEE . ' NOT NULL');
        }
    }

    public function down(): void
    {
        // Only revert if nothing relies on the new value, to avoid data loss.
        if (Schema::hasColumn('wallet_transactions', 'type')
            && DB::table('wallet_transactions')->where('type', 'platform_fee')->doesntExist()) {
            DB::statement('ALTER TABLE `wallet_transactions` MODIFY `type` ' . self::WITHOUT_FEE . ' NOT NULL');
        }
    }
};
