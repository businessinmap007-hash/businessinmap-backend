<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('location_id_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('old_id')->index();
            $table->unsignedBigInteger('new_id')->index();
            $table->string('code')->index();
            $table->string('type')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_id_mappings');
    }
};
