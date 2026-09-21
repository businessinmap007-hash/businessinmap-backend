<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the trainer was last told a trainee had lapsed (the newest missed day
 * that alert covered). It is what makes the alert once per lapse: no new alert
 * until the trainee has trained again since, and missed a fresh run of days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_plans', function (Blueprint $table) {
            $table->date('lapse_alerted_on')->nullable()->after('duration_weeks');
        });
    }

    public function down(): void
    {
        Schema::table('training_plans', function (Blueprint $table) {
            $table->dropColumn('lapse_alerted_on');
        });
    }
};
