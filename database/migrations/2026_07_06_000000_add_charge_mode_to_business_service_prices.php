<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the per-(business, child, service, item type) table charge mode.
 *
 * standard        -> the normal price applies (rooms, fields, ...).
 * free            -> the unit is free; only the food/order is charged.
 * reservation_fee -> a fixed booking fee (charge_amount) applies.
 * minimum_charge  -> a minimum spend (charge_amount); food tops up to it.
 *
 * charge_amount holds the fee/minimum for the two table modes. Existing rows
 * default to 'standard', so behaviour is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_service_prices')) {
            return;
        }

        Schema::table('business_service_prices', function (Blueprint $table) {
            if (! Schema::hasColumn('business_service_prices', 'charge_mode')) {
                $table->string('charge_mode', 30)->default('standard')->after('price');
            }
            if (! Schema::hasColumn('business_service_prices', 'charge_amount')) {
                $table->decimal('charge_amount', 10, 2)->default(0)->after('charge_mode');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_service_prices')) {
            return;
        }

        Schema::table('business_service_prices', function (Blueprint $table) {
            foreach (['charge_mode', 'charge_amount'] as $column) {
                if (Schema::hasColumn('business_service_prices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
