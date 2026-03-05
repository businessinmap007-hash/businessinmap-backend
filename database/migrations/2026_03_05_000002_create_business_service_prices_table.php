<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_service_prices', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('business_id'); // users.id (type=business)
            $table->unsignedBigInteger('service_id');  // services.id

            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['business_id', 'service_id'], 'bsp_business_service_unique');

            $table->index('business_id');
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_service_prices');
    }
};