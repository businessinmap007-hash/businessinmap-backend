<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links prescription delivery onto the SAME driver pool as menu orders
 * (`delivery_drivers` — a driver is a driver, not an order-only concept).
 * Mirrors the 4 columns `2026_07_21_000000_create_delivery_system.php` added
 * to `orders`, 1:1, so `App\Services\Prescriptions\PrescriptionDeliveryService`
 * can mirror `DeliveryDispatchService`'s shape exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prescriptions')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('prescriptions', 'delivery_driver_id')) {
                $table->unsignedBigInteger('delivery_driver_id')->nullable()->after('priced_at');
            }
            if (! Schema::hasColumn('prescriptions', 'delivery_stage')) {
                $table->string('delivery_stage', 20)->nullable()->after('delivery_driver_id');
            }
            if (! Schema::hasColumn('prescriptions', 'pickup_token')) {
                $table->string('pickup_token', 64)->nullable()->after('delivery_stage');
            }
            if (! Schema::hasColumn('prescriptions', 'delivery_token')) {
                $table->string('delivery_token', 64)->nullable()->after('pickup_token');
            }
        });

        if (Schema::hasTable('delivery_drivers')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->foreign('delivery_driver_id')->references('id')->on('delivery_drivers')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            if (Schema::hasColumn('prescriptions', 'delivery_driver_id')) {
                $table->dropForeign(['delivery_driver_id']);
            }
            foreach (['delivery_driver_id', 'delivery_stage', 'pickup_token', 'delivery_token'] as $column) {
                if (Schema::hasColumn('prescriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
