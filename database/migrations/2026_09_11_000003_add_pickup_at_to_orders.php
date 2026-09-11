<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The customer's requested pickup time, set at checkout when
 * fulfillment_type=pickup — required upfront rather than left for the
 * merchant to guess, so "استلام من المكان" carries a real appointment the
 * same way a delivery carries an address.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'pickup_at')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('pickup_at')->nullable()->after('fulfillment_type');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('pickup_at');
        });
    }
};
