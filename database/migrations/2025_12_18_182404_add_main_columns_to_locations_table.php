<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {

            // أسماء الموقع
            $table->string('name_ar')->after('parent_id');
            $table->string('name_en')->after('name_ar');

            // نوع الموقع
            $table->enum('type', ['country', 'governorate', 'city'])
                  ->after('name_en');

            // الإحداثيات (إن لم تكن موجودة)
            if (!Schema::hasColumn('locations', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable()->after('type');
            }

            if (!Schema::hasColumn('locations', 'lng')) {
                $table->decimal('lng', 10, 7)->nullable()->after('lat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_en', 'type']);
        });
    }

};
