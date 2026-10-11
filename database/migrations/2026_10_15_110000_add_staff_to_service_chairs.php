<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The person at a chair may be an account — a customer the business took on as staff, like a clinic's secretary — so
 * the chair shows that account's own photo and name instead of ones typed by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_chairs', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_user_id')->nullable()->index()->after('person_name');
        });
    }

    public function down(): void
    {
        Schema::table('service_chairs', function (Blueprint $table) {
            $table->dropColumn('staff_user_id');
        });
    }
};
