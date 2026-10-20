<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who took an order (owner, 2026-10-20): when a business has several people receiving orders, each order says WHO accepted it
 * and WHO is preparing it («جارٍ تجهيزه من فلان»). Plain user ids: the order's receivers are staff with the orders capability,
 * which is not the same as «provider» (see the assignments table), so this does not go through AssignmentService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'accepted_by')) {
                $table->unsignedBigInteger('accepted_by')->nullable();
            }
            if (! Schema::hasColumn('orders', 'prepared_by')) {
                $table->unsignedBigInteger('prepared_by')->nullable();
            }
            if (! Schema::hasColumn('orders', 'prepared_at')) {
                $table->timestamp('prepared_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['accepted_by', 'prepared_by', 'prepared_at']));
    }
};
