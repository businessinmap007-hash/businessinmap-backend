<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the `grant` transaction type — an admin handing a business free coverage.
 * Distinct from `activate` (which is backed by a wallet lock) so the ledger and
 * reports can tell a paid guarantee from a gifted one.
 */
return new class extends Migration
{
    private const WITH_GRANT = "enum('lock','unlock','upgrade','downgrade','penalty','restore','coverage_use','coverage_release','suspend','activate','cancel','expiration','manual_suspend','manual_reactivate','manual_expiration','grant')";
    private const WITHOUT_GRANT = "enum('lock','unlock','upgrade','downgrade','penalty','restore','coverage_use','coverage_release','suspend','activate','cancel','expiration','manual_suspend','manual_reactivate','manual_expiration')";

    public function up(): void
    {
        DB::statement('ALTER TABLE guarantee_transactions MODIFY COLUMN `type` ' . self::WITH_GRANT . ' NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE guarantee_transactions MODIFY COLUMN `type` ' . self::WITHOUT_GRANT . ' NOT NULL');
    }
};
