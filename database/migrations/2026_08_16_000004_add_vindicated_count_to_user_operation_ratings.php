<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `vindicated_count` — how many disputes a ruling was decided IN THIS PARTY'S
 * FAVOUR. The mirror of `fault_count`: when a ruling names a loser, the other
 * side is the winner and carries this instead. It lets a party show that a
 * dispute on their record was one they won, not one they caused — the `disputed`
 * mark alone can't tell the two apart.
 *
 * Like fault_count it is an overlay on the already-counted `disputed` operation,
 * so it never re-increments total_operations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_operation_ratings', function (Blueprint $table) {
            $table->unsignedInteger('vindicated_count')->default(0)->after('fault_count');
        });
    }

    public function down(): void
    {
        Schema::table('user_operation_ratings', function (Blueprint $table) {
            $table->dropColumn('vindicated_count');
        });
    }
};
