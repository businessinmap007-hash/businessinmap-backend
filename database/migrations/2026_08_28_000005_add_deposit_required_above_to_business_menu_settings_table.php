<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders had no financial protection at all — a client can refuse to
 * receive a delivered order and the business eats the shipping, and
 * nothing deterred a business from shipping mismatched goods either. The
 * merchant, not the platform, decides the threshold: null means "never
 * require one", matching min_order_amount's own null-is-off convention on
 * this same table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('business_menu_settings', 'deposit_required_above')) {
            Schema::table('business_menu_settings', function (Blueprint $table) {
                $table->decimal('deposit_required_above', 10, 2)->nullable()->after('min_order_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('business_menu_settings', 'deposit_required_above')) {
            Schema::table('business_menu_settings', function (Blueprint $table) {
                $table->dropColumn('deposit_required_above');
            });
        }
    }
};
