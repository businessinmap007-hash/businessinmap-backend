<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        // ✅ غيّري اسم الجدول هنا حسب اسمك الحقيقي
        $tableName = 'deposits';

        Schema::table($tableName, function (Blueprint $table) {

            $table->index('client_id', 'esc_client_idx');
            $table->index('business_id', 'esc_business_idx');
            $table->index('status', 'esc_status_idx');

            $table->index(['target_type', 'target_id'], 'esc_target_idx');

            $table->index('released_at', 'esc_released_at_idx');
            $table->index('refunded_at', 'esc_refunded_at_idx');
        });

        // توحيد دقة الأموال إلى 12,2 (اختياري لكن أنصح به)
        // لو لا تريدين تعديل النوع، علّقي هذا الجزء
        try {
            DB::statement("ALTER TABLE {$tableName} MODIFY total_amount DECIMAL(12,2) NOT NULL");
            DB::statement("ALTER TABLE {$tableName} MODIFY client_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00");
            DB::statement("ALTER TABLE {$tableName} MODIFY business_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00");
        } catch (\Throwable $e) {
            // ignore if fails
        }
    }

    public function down(): void
    {
        $tableName = 'deposits';

        // رجوع الدقة إلى 10,2 (اختياري)
        try {
            DB::statement("ALTER TABLE {$tableName} MODIFY total_amount DECIMAL(10,2) NOT NULL");
            DB::statement("ALTER TABLE {$tableName} MODIFY client_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
            DB::statement("ALTER TABLE {$tableName} MODIFY business_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        } catch (\Throwable $e) {
            // ignore
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropIndex('esc_client_idx');
            $table->dropIndex('esc_business_idx');
            $table->dropIndex('esc_status_idx');
            $table->dropIndex('esc_target_idx');
            $table->dropIndex('esc_released_at_idx');
            $table->dropIndex('esc_refunded_at_idx');
        });
    }
};