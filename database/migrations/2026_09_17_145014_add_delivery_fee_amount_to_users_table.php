<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A flat delivery charge the business sets for its own service -
            // applied automatically at checkout (CustomerCartService::placeOrder)
            // when the customer chooses delivery. Null means the business
            // hasn't set one yet, so delivery stays free (the existing default).
            $table->decimal('delivery_fee_amount', 10, 2)->nullable()->after('attendance_verification_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('delivery_fee_amount');
        });
    }
};
