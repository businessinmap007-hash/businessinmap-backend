<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('delivery_orders', function (Blueprint $table) {

            if (!Schema::hasColumn('delivery_orders', 'delivery_type')) {
                $table->string('delivery_type')->nullable()->after('dropoff_lng');
            }

            if (!Schema::hasColumn('delivery_orders', 'weight')) {
                $table->string('weight')->nullable()->after('delivery_type');
            }

            if (!Schema::hasColumn('delivery_orders', 'price_estimated')) {
                $table->decimal('price_estimated', 8, 2)->nullable()->after('weight');
            }

            if (!Schema::hasColumn('delivery_orders', 'price_final')) {
                $table->decimal('price_final', 8, 2)->nullable()->after('price_estimated');
            }

            if (!Schema::hasColumn('delivery_orders', 'payment_method')) {
                $table->enum('payment_method', ['cash', 'online', 'wallet'])
                      ->default('cash')
                      ->after('price_final');
            }

            if (!Schema::hasColumn('delivery_orders', 'delivered_image')) {
                $table->string('delivered_image')->nullable()->after('notes');
            }
        });
    }

    public function down()
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_type',
                'weight',
                'price_estimated',
                'price_final',
                'payment_method',
                'delivered_image',
            ]);
        });
    }
};
