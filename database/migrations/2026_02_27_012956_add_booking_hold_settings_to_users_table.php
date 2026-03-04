<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('booking_hold_enabled')
                ->default(false)
                ->after('pin_locked_until');

            $table->decimal('booking_hold_amount', 10, 2)
                ->default(0)
                ->after('booking_hold_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'booking_hold_enabled',
                'booking_hold_amount',
            ]);
        });
    }
};