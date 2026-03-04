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
    Schema::create('services', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('business_id'); // صاحب الخدمة
        $table->string('name_ar');
        $table->string('name_en');
        $table->decimal('price', 10, 2)->default(0);
        $table->integer('duration')->default(30); // مدة الخدمة بالدقائق
        $table->text('description')->nullable();
        $table->timestamps();

        $table->foreign('business_id')->references('id')->on('users')->onDelete('cascade');
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
