<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {

            if (Schema::hasColumn('addresses', 'location_id')) {
                $table->dropColumn('location_id');
            }

            if (Schema::hasColumn('addresses', 'latitude')) {
                $table->dropColumn('latitude');
            }

            if (Schema::hasColumn('addresses', 'longitude')) {
                $table->dropColumn('longitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
        });
    }
};
