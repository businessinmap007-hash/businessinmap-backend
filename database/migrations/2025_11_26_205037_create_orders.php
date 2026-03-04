<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id'); 
    $table->unsignedBigInteger('business_id'); 
    $table->decimal('total', 10, 2);
    $table->string('status')->default('pending'); // pending / accepted / preparing / delivering / completed / canceled
    $table->string('payment_method')->default('cash');
    $table->string('address');
    $table->timestamps();

    $table->foreign('user_id')->references('id')->on('users');
    $table->foreign('business_id')->references('id')->on('users');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
