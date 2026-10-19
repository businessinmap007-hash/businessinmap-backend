<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A training / nutrition programme is a paid month (owner, 2026-10-27): the fee set in the control panel is taken when the
 * trainer shares it with a client, and again for every further month; a programme that is not renewed disappears from the
 * client. `paid_until` is that window (null = a legacy plan, never limited); `renewals` counts the months renewed after the
 * first, so month N charges under occurrence N+1.
 *
 * Deliberately NOT `ends_on`: that is the programme's own length in weeks (an 8-week programme runs over several paid months).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('training_plans', 'paid_until')) {
                $table->date('paid_until')->nullable()->after('ends_on');
                $table->unsignedSmallInteger('renewals')->default(0)->after('paid_until');
            }
        });
    }

    public function down(): void
    {
        Schema::table('training_plans', function (Blueprint $table) {
            $table->dropColumn(['paid_until', 'renewals']);
        });
    }
};
