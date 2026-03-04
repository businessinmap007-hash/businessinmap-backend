<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeliveryOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');        // العميل
            $table->unsignedBigInteger('business_id')->nullable(); // المتجر (اختياري)

            $table->text('pickup_address');
            $table->double('pickup_lat');
            $table->double('pickup_lng');

            $table->text('dropoff_address');
            $table->double('dropoff_lat');
            $table->double('dropoff_lng');

            $table->decimal('price', 8, 2)->nullable();
            $table->enum('status', [
                'pending', 'accepted', 'delivering', 'delivered', 'canceled'
            ])->default('pending');

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('courier_id')->nullable(); // الساعي

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('delivery_orders');
    }
}
