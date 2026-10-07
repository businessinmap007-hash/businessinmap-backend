<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_deposit_policies', function (Blueprint $table) {
            // An optional term: the business is willing to take the customer's frozen deposit AS a payment toward the
            // booking, when the customer asks, instead of being paid by a transfer outside the platform. Off unless the
            // business turns it on — a deposit is a seriousness measure, not part of the price.
            $table->boolean('accept_deposit_as_payment')->default(false)->after('forfeit_to_business');
        });
    }

    public function down(): void
    {
        Schema::table('business_deposit_policies', function (Blueprint $table) {
            $table->dropColumn('accept_deposit_as_payment');
        });
    }
};
