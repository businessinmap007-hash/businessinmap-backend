<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_fees', function (Blueprint $table) {
            if (! Schema::hasColumn('service_fees', 'child_id')) {
                $table->unsignedBigInteger('child_id')->nullable()->after('business_id');
                $table->index('child_id', 'service_fees_child_id_index');
            }

            $table->index(
                ['business_id', 'child_id', 'service_id', 'fee_code', 'payer'],
                'service_fees_context_payer_index'
            );

            $table->index(
                ['business_id', 'child_id', 'service_id', 'fee_code'],
                'service_fees_context_group_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('service_fees', function (Blueprint $table) {
            try {
                $table->dropIndex('service_fees_context_payer_index');
            } catch (\Throwable $e) {
            }

            try {
                $table->dropIndex('service_fees_context_group_index');
            } catch (\Throwable $e) {
            }

            if (Schema::hasColumn('service_fees', 'child_id')) {
                try {
                    $table->dropIndex('service_fees_child_id_index');
                } catch (\Throwable $e) {
                }

                $table->dropColumn('child_id');
            }
        });
    }
};