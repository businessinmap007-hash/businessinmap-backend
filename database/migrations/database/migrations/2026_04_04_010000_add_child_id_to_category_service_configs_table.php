<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_service_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('category_service_configs', 'child_id')) {
                $table->unsignedBigInteger('child_id')->nullable()->after('category_id');
                $table->index('child_id', 'csc_child_id_index');
            }

            $table->index(['category_id', 'platform_service_id'], 'csc_category_service_index');
            $table->index(['child_id', 'platform_service_id'], 'csc_child_service_index');
        });
    }

    public function down(): void
    {
        Schema::table('category_service_configs', function (Blueprint $table) {
            try {
                $table->dropIndex('csc_category_service_index');
            } catch (\Throwable $e) {
            }

            try {
                $table->dropIndex('csc_child_service_index');
            } catch (\Throwable $e) {
            }

            if (Schema::hasColumn('category_service_configs', 'child_id')) {
                try {
                    $table->dropIndex('csc_child_id_index');
                } catch (\Throwable $e) {
                }

                $table->dropColumn('child_id');
            }
        });
    }
};