<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDriverLocationsTable extends Migration
{
    public function up()
    {
        Schema::create('driver_locations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('driver_id');

            $table->double('lat', 10, 6);
            $table->double('lng', 10, 6);

            $table->timestamps();

            $table->foreign('driver_id')
                  ->references('id')
                  ->on('drivers')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('driver_locations');
    }
}
