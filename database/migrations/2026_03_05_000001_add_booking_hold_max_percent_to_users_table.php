<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // لا نضيف booking_hold_enabled/amount لأنهم موجودين بالفعل عندك
            if (!Schema::hasColumn('users', 'booking_hold_max_percent')) {
                $table->unsignedTinyInteger('booking_hold_max_percent')
                    ->default(20)
                    ->after('booking_hold_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'booking_hold_max_percent')) {
                $table->dropColumn('booking_hold_max_percent');
            }
        });
    }
};