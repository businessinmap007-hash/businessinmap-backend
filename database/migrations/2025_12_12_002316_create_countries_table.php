<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('iso2', 2)->unique();   // EG
            $table->string('iso3', 3)->nullable(); // EGY
            $table->string('phone_code', 10)->nullable(); // 20
            $table->string('currency', 10)->nullable();   // EGP
            $table->string('flag')->nullable();           // path أو كود
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('countries');
    }
};
