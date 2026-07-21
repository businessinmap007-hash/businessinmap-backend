<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a delivery order point at a saved address book entry.
 *
 * Checkout kept the delivery address as a free string, so a courier got a line
 * with no city, governorate or coordinates behind it. Now that the address book
 * works, an order can reference the chosen address; the string is still written
 * as a human-readable snapshot so a later edit to the address never rewrites
 * history on an order already out for delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_address_id')->nullable()->after('address');
            $table->index('delivery_address_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['delivery_address_id']);
            $table->dropColumn('delivery_address_id');
        });
    }
};
