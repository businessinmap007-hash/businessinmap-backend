<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_carts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('business_id')->index();

            $table->timestamps();

            $table->unique(['user_id', 'business_id'], 'menu_carts_user_business_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_carts');
    }
};
