<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRidesTable extends Migration
{
    public function up()
    {
        Schema::create('rides', function (Blueprint $table) {
            $table->id();

            // طالب الرحلة (مستخدم عادي)
            $table->unsignedBigInteger('user_id');

            // السائق (business driver)
            $table->unsignedBigInteger('driver_id')->nullable();

            $table->double('start_lat', 10, 6);
            $table->double('start_lng', 10, 6);

            $table->double('end_lat', 10, 6);
            $table->double('end_lng', 10, 6);

            $table->enum('status', [
                'pending',          // طلب جديد
                'driver_assigned',  // تم إسناد سائق
                'arrival',          // السائق وصل لمكان العميل
                'in_progress',      // الرحلة بدأت
                'completed',        // الرحلة انتهت
                'cancelled'         // ملغية
            ])->default('pending');

            $table->decimal('price', 10, 2)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('user_id')
                  ->references('id')->on('users')->onDelete('cascade');

            $table->foreign('driver_id')
                  ->references('id')->on('drivers')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rides');
    }
}
