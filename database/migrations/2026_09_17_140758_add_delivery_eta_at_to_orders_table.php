<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // The driver's own stated ETA (DeliveryDispatchService::notifyEta) -
            // persisted here, not just in the dispatched notification's JSON
            // meta, so confirmDelivery() can compare it against the real
            // completion time for the on-time badge.
            $table->timestamp('delivery_eta_at')->nullable()->after('delivery_token');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_eta_at');
        });
    }
};
