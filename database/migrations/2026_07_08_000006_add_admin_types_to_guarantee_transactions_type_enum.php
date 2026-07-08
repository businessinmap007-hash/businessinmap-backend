<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The guarantee admin/expiration flows write transaction types
 * (manual_suspend, manual_reactivate, manual_expiration, expiration) that the
 * guarantee_transactions.type enum never included, so every such action failed
 * with "Data truncated for column 'type'". Add the missing values.
 */
return new class extends Migration
{
    private const WITH_ADMIN = "ENUM('lock','unlock','upgrade','downgrade','penalty','restore','coverage_use','coverage_release','suspend','activate','cancel','expiration','manual_suspend','manual_reactivate','manual_expiration')";

    private const WITHOUT_ADMIN = "ENUM('lock','unlock','upgrade','downgrade','penalty','restore','coverage_use','coverage_release','suspend','activate','cancel')";

    public function up(): void
    {
        if (Schema::hasColumn('guarantee_transactions', 'type')) {
            DB::statement('ALTER TABLE `guarantee_transactions` MODIFY `type` ' . self::WITH_ADMIN . ' NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('guarantee_transactions', 'type')
            && DB::table('guarantee_transactions')
                ->whereIn('type', ['expiration', 'manual_suspend', 'manual_reactivate', 'manual_expiration'])
                ->doesntExist()) {
            DB::statement('ALTER TABLE `guarantee_transactions` MODIFY `type` ' . self::WITHOUT_ADMIN . ' NOT NULL');
        }
    }
};
