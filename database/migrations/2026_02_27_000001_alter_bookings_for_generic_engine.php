<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Generic time range
            $table->dateTime('starts_at')->nullable()->after('service_id');
            $table->dateTime('ends_at')->nullable()->after('starts_at');

            $table->unsignedInteger('duration_value')->nullable()->after('ends_at');
            $table->enum('duration_unit', ['minute','hour','day','week','month','year'])->nullable()->after('duration_value');

            $table->boolean('all_day')->default(false)->after('duration_unit');
            $table->string('timezone', 64)->nullable()->after('all_day');

            // Quantity / party size (restaurants, hours, nights, etc.)
            $table->unsignedInteger('quantity')->nullable()->after('timezone');
            $table->unsignedInteger('party_size')->nullable()->after('quantity');

            // Polymorphic "bookable" target
            $table->string('bookable_type', 120)->nullable()->after('party_size');
            $table->unsignedBigInteger('bookable_id')->nullable()->after('bookable_type');

            // Meta for flexible attachments (menu items, prescription data, etc.)
            $table->json('meta')->nullable()->after('notes');

            $table->index(['starts_at']);
            $table->index(['bookable_type', 'bookable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['starts_at']);
            $table->dropIndex(['bookable_type', 'bookable_id']);

            $table->dropColumn([
                'starts_at','ends_at',
                'duration_value','duration_unit',
                'all_day','timezone',
                'quantity','party_size',
                'bookable_type','bookable_id',
                'meta',
            ]);
        });
    }
};