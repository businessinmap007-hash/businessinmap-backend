<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'amount')) {
                $table->decimal('amount', 12, 2)->default(0)->after('price');
            }
            if (!Schema::hasColumn('payments', 'operation_id_int')) {
                $table->unsignedBigInteger('operation_id_int')->nullable()->after('operation_id');
            }
            if (!Schema::hasColumn('payments', 'note_template_id')) {
                $table->unsignedBigInteger('note_template_id')->nullable()->after('operation_id_int');
            }

            $table->index(['user_id', 'paid_at']);
            $table->index(['operation_type']);
        });

        // backfill amount من price (لأن price varchar)
        DB::statement("UPDATE payments SET amount = CAST(price AS DECIMAL(12,2)) WHERE (amount = 0 OR amount IS NULL) AND price IS NOT NULL AND price <> ''");

        // backfill operation_id_int من operation_id لو كان رقم
        DB::statement("UPDATE payments SET operation_id_int = CAST(operation_id AS UNSIGNED) WHERE operation_id_int IS NULL AND operation_id REGEXP '^[0-9]+$'");
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'amount')) $table->dropColumn('amount');
            if (Schema::hasColumn('payments', 'operation_id_int')) $table->dropColumn('operation_id_int');
            if (Schema::hasColumn('payments', 'note_template_id')) $table->dropColumn('note_template_id');
        });
    }
};