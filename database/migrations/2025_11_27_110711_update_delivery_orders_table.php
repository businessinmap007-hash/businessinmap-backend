<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
        public function up()
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            
            if (!Schema::hasColumn('delivery_orders','type')) {
                $table->string('type')->default('parcel'); // parcel, food, documents...
            }

            if (!Schema::hasColumn('delivery_orders','distance_km')) {
                $table->decimal('distance_km', 8, 2)->nullable();
            }

            if (!Schema::hasColumn('delivery_orders','price')) {
                $table->decimal('price', 10, 2)->nullable();
            }

            if (!Schema::hasColumn('delivery_orders','status')) {
                $table->enum('status', [
                    'pending',          // بانتظار سائق
                    'driver_assigned',  // تم قبول الطلب
                    'driver_on_way',    // السائق في الطريق للمكان
                    'arrived_pickup',   // وصل مكان الاستلام
                    'picked',           // تم استلام الطلب
                    'on_the_way_dropoff', 
                    'delivered',
                    'canceled_by_user',
                    'canceled_by_driver'
                ])->default('pending');
            }

            if (!Schema::hasColumn('delivery_orders','notes')) {
                $table->text('notes')->nullable();
            }

            if (!Schema::hasColumn('delivery_orders','driver_lat')) {
                $table->decimal('driver_lat',10,7)->nullable();
                $table->decimal('driver_lng',10,7)->nullable();
            }
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            //
        });
    }
};
