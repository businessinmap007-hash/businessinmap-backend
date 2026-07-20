<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why a fine was imposed.
 *
 * A fine is no longer a free-hand tool an arbitrator may reach for because a
 * party lost. It is available on exactly two grounds — misconduct in the room,
 * or refusing to comply with the ruling — and the ground has to be on the
 * record, because "they were fined" without a stated reason is unappealable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arbitration_sessions', function (Blueprint $table) {
            $table->enum('platform_fine_reason', ['conduct', 'non_compliance'])
                ->nullable()
                ->after('platform_fine_on');
        });
    }

    public function down(): void
    {
        Schema::table('arbitration_sessions', function (Blueprint $table) {
            $table->dropColumn('platform_fine_reason');
        });
    }
};
