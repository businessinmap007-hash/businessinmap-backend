<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_service_prices', function (Blueprint $table) {
            if (!Schema::hasColumn('business_service_prices', 'deposit_enabled')) {
                $table->boolean('deposit_enabled')->default(false)->after('is_active');
            }

            if (!Schema::hasColumn('business_service_prices', 'deposit_percent')) {
                $table->unsignedTinyInteger('deposit_percent')->default(0)->after('deposit_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('business_service_prices', function (Blueprint $table) {
            if (Schema::hasColumn('business_service_prices', 'deposit_percent')) {
                $table->dropColumn('deposit_percent');
            }

            if (Schema::hasColumn('business_service_prices', 'deposit_enabled')) {
                $table->dropColumn('deposit_enabled');
            }
        });
    }
};