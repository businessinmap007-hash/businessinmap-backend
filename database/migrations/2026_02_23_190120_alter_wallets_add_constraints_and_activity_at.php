<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {

            if (!Schema::hasColumn('wallets', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('status');
            }

            // Indexes (safe)
            $table->index('status', 'wallets_status_idx');

            // 1 wallet per user (إذا عندك أكتر من Wallet لنفس user احذف السطر ده)
            $table->unique('user_id', 'wallets_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {

            if (Schema::hasColumn('wallets', 'last_activity_at')) {
                $table->dropColumn('last_activity_at');
            }

            $table->dropIndex('wallets_status_idx');
            $table->dropUnique('wallets_user_id_unique');
        });
    }
};