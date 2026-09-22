<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Out-of-city delivery is priced per order: the courier proposes an amount by
 * distance, the customer accepts or declines it, and the merchant - who knows
 * the going rate - recommends it as suitable or not. In-city orders keep the
 * business's fixed fee and never use these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_fee_status', 20)->nullable()->after('delivery_fee');
            $table->decimal('delivery_fee_proposed', 10, 2)->nullable()->after('delivery_fee_status');
            $table->string('delivery_fee_recommendation', 20)->nullable()->after('delivery_fee_proposed');
            $table->string('delivery_fee_recommendation_note', 500)->nullable()->after('delivery_fee_recommendation');
            $table->timestamp('delivery_fee_decided_at')->nullable()->after('delivery_fee_recommendation_note');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_fee_status', 'delivery_fee_proposed', 'delivery_fee_recommendation',
                'delivery_fee_recommendation_note', 'delivery_fee_decided_at',
            ]);
        });
    }
};
