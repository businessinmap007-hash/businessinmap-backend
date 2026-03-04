<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {

            // Indexes for performance
            $table->index(['wallet_id', 'created_at'], 'wtx_wallet_created_idx');
            $table->index(['user_id', 'created_at'], 'wtx_user_created_idx');
            $table->index(['type', 'status'], 'wtx_type_status_idx');

            // Idempotency: MySQL يسمح بتكرار NULL لكن يمنع تكرار القيمة
            $table->unique('idempotency_key', 'wtx_idempotency_unique');
        });

        // Try convert meta to JSON if it's LONGTEXT
        // NOTE: This is safe-ish; if your MySQL doesn't support JSON, comment it.
        try {
            DB::statement("ALTER TABLE wallet_transactions MODIFY meta JSON NULL");
        } catch (\Throwable $e) {
            // leave as-is (LONGTEXT) if JSON not supported / fails
        }
    }

    public function down(): void
    {
        // Revert meta back to LONGTEXT (best-effort)
        try {
            DB::statement("ALTER TABLE wallet_transactions MODIFY meta LONGTEXT NULL");
        } catch (\Throwable $e) {
            // ignore
        }

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex('wtx_wallet_created_idx');
            $table->dropIndex('wtx_user_created_idx');
            $table->dropIndex('wtx_type_status_idx');
            $table->dropUnique('wtx_idempotency_unique');
        });
    }
};