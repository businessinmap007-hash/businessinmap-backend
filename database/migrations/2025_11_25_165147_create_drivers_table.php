<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDriversTable extends Migration
{
    public function up()
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();

            // البزنس صاحب الحساب (من جدول users)
            $table->unsignedBigInteger('business_id');

            // pending: لسه تحت المراجعة
            // approved: مسموح يشتغل
            // blocked: موقوف
            $table->enum('status', ['pending', 'approved', 'blocked'])->default('pending');

            // online / offline / busy
            $table->enum('availability', ['online', 'offline', 'busy'])->default('offline');

            $table->timestamps();

            $table->foreign('business_id')
                  ->references('id')
                  ->on('users')   // هنا بنفترض إن البزنس من جدول users
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('drivers');
    }
}
