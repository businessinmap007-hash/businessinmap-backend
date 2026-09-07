<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "لو الصنف نفذ، تحب نعمل إيه؟" — asked once at menu checkout (product
     * plan, "منيو ومطاعم" section), not per line: substitute (business's
     * judgement) / remove just that line / cancel the whole order. Null on
     * every order placed before this and on non-menu orders (bookings never
     * touch this column) — the business-side "mark item unavailable" action
     * refuses to guess when it's null, see OrderController.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('out_of_stock_policy', 20)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('out_of_stock_policy');
        });
    }
};
