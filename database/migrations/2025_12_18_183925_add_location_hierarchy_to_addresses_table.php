<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {

            $table->foreignId('country_id')
                  ->nullable()
                  ->after('location_id')
                  ->constrained('locations')
                  ->nullOnDelete();

            $table->foreignId('governorate_id')
                  ->nullable()
                  ->after('country_id')
                  ->constrained('locations')
                  ->nullOnDelete();

            $table->foreignId('city_id')
                  ->nullable()
                  ->after('governorate_id')
                  ->constrained('locations')
                  ->nullOnDelete();

            $table->string('address_line')->nullable()->after('zip_code');

            $table->decimal('lat', 10, 7)->nullable()->after('address_line');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['governorate_id']);
            $table->dropForeign(['city_id']);

            $table->dropColumn([
                'country_id',
                'governorate_id',
                'city_id',
                'address_line',
                'lat',
                'lng',
            ]);
        });
    }
};
