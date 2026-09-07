<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How a business resolved a line it discovered it couldn't fulfil, per
     * the order's own out_of_stock_policy: 'substituted' (kept, note says
     * what it became) or 'removed' (dropped out of the total — see
     * MenuOrderService::recalc). Null = untouched, the overwhelming
     * majority of lines. A whole-order cancellation also marks the
     * triggering line 'removed' for the same audit trail, even though the
     * order itself carries the real outcome.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('unavailable_marked_at')->nullable()->after('total_price');
            $table->string('resolution', 20)->nullable()->after('unavailable_marked_at');
            $table->text('resolution_note')->nullable()->after('resolution');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['unavailable_marked_at', 'resolution', 'resolution_note']);
        });
    }
};
