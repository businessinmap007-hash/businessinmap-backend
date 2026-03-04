<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         Schema::table('bookings', function (Blueprint $table) {

        if (!Schema::hasColumn('bookings', 'service_id')) {
            $table->unsignedBigInteger('service_id')->nullable()->after('business_id');
        }

        if (!Schema::hasColumn('bookings', 'time')) {
            $table->time('time')->nullable()->after('date');
        }

        if (!Schema::hasColumn('bookings', 'status')) {
            $table->enum('status', [
                'pending', 'accepted', 'rejected', 'canceled', 'completed'
            ])->default('pending');
        }

        if (!Schema::hasColumn('bookings', 'notes')) {
            $table->text('notes')->nullable();
        }

        // مثال: لو جدولك لا يحتوي على علاقة business_id
        if (!Schema::hasColumn('bookings', 'business_id')) {
            $table->unsignedBigInteger('business_id')->nullable();
        }
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
