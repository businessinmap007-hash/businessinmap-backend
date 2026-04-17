<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_service_prices', function (Blueprint $table) {
            if (! Schema::hasColumn('business_service_prices', 'child_id')) {
                $table->unsignedBigInteger('child_id')->nullable()->after('business_id');
                $table->index('child_id', 'bsp_child_id_index');
            }

            $table->index(
                ['business_id', 'child_id', 'service_id', 'bookable_item_type'],
                'bsp_business_child_service_type_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('business_service_prices', function (Blueprint $table) {
            try {
                $table->dropIndex('bsp_business_child_service_type_index');
            } catch (\Throwable $e) {
            }

            if (Schema::hasColumn('business_service_prices', 'child_id')) {
                try {
                    $table->dropIndex('bsp_child_id_index');
                } catch (\Throwable $e) {
                }

                $table->dropColumn('child_id');
            }
        });
    }
};