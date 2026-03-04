<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarsTable extends Migration
{
    public function up()
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('driver_id');

            $table->string('car_type');    // Sedan, SUV, Bike...
            $table->string('car_model');   // Corolla, Civic ...
            $table->string('car_number');  // رقم اللوحة
            $table->string('color')->nullable();
            $table->integer('year')->nullable();
            $table->string('image')->nullable(); // صورة السيارة إن حبيت

            $table->timestamps();

            $table->foreign('driver_id')
                  ->references('id')
                  ->on('drivers')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cars');
    }
}
