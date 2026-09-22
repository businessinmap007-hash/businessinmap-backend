<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_drivers', function (Blueprint $table) {
            // A freelance driver's own flat rate - used as the checkout fee
            // ONLY as a fallback, when the order's business never set its own
            // delivery_fee_amount and this driver ends up accepting the order
            // from the open pool (DeliveryDispatchService::acceptOrder). A
            // business-owned driver's own rate here is unused - the business's
            // own delivery_fee_amount always wins for its private team.
            $table->decimal('delivery_fee_amount', 10, 2)->nullable()->after('fast_delivery_count');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_drivers', function (Blueprint $table) {
            $table->dropColumn('delivery_fee_amount');
        });
    }
};
