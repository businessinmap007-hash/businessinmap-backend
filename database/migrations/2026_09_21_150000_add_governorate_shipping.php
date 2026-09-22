<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping between governorates: a Shipping & Delivery company keeps a fixed
 * price from its own governorate to each other one; a product order to another
 * governorate is shipped by a company the merchant picks, which sets the
 * appointment. Not the courier delivery loop (shipping_status stays null there).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('to_governorate_id');
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->unique(['company_id', 'to_governorate_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_status', 24)->nullable()->after('delivery_fee_decided_at');
            $table->unsignedBigInteger('shipping_company_id')->nullable()->after('shipping_status');
            $table->unsignedBigInteger('shipping_to_governorate_id')->nullable()->after('shipping_company_id');
            $table->decimal('shipping_fee', 10, 2)->nullable()->after('shipping_to_governorate_id');
            $table->timestamp('shipping_appointment_at')->nullable()->after('shipping_fee');
            $table->string('shipping_appointment_note', 500)->nullable()->after('shipping_appointment_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_status', 'shipping_company_id', 'shipping_to_governorate_id',
                'shipping_fee', 'shipping_appointment_at', 'shipping_appointment_note',
            ]);
        });
        Schema::dropIfExists('shipping_rates');
    }
};
