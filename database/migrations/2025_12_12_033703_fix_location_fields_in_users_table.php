<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // حذف location_id إن وُجد
            if (Schema::hasColumn('users', 'location_id')) {
                $table->dropColumn('location_id');
            }

            // تعديل latitude / longitude
            if (Schema::hasColumn('users', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->change();
            }

            if (Schema::hasColumn('users', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('location_id')->nullable();
            $table->string('latitude', 55)->nullable()->change();
            $table->string('longitude', 55)->nullable()->change();
        });
    }
};
