<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `fault_count` — how many times a ruling was decided AGAINST this party.
 *
 * Opening a dispute records `disputed` for both sides (a neutral fact: the
 * operation went to dispute). The ruling then names who was at fault, and only
 * the loser carries that. It is an overlay on the SAME operation the `disputed`
 * mark already counted, so it must never re-increment `total_operations` — the
 * rate reads fault_count / total_operations, and inflating the denominator
 * would understate everyone's fault.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_operation_ratings', function (Blueprint $table) {
            $table->unsignedInteger('fault_count')->default(0)->after('disputed_count');
        });
    }

    public function down(): void
    {
        Schema::table('user_operation_ratings', function (Blueprint $table) {
            $table->dropColumn('fault_count');
        });
    }
};
