<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_service_prices', function (Blueprint $table) {
            if (!Schema::hasColumn('business_service_prices', 'discount_enabled')) {
                $table->boolean('discount_enabled')->default(false)->after('deposit_percent');
            }

            if (!Schema::hasColumn('business_service_prices', 'discount_percent')) {
                $table->unsignedInteger('discount_percent')->default(0)->after('discount_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('business_service_prices', function (Blueprint $table) {
            if (Schema::hasColumn('business_service_prices', 'discount_percent')) {
                $table->dropColumn('discount_percent');
            }

            if (Schema::hasColumn('business_service_prices', 'discount_enabled')) {
                $table->dropColumn('discount_enabled');
            }
        });
    }
};