<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('wallet_transactions', 'note_id')) {
                $table->foreignId('note_id')
                    ->nullable()
                    ->after('idempotency_key')
                    ->constrained('wallet_note_templates')
                    ->nullOnDelete();

                $table->index('note_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('wallet_transactions', 'note_id')) {
                $table->dropConstrainedForeignId('note_id');
            }
        });
    }
};