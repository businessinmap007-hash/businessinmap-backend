<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An admin-granted guarantee: coverage handed to a business for free (no wallet
 * lock backing it) and CLOSED — it can never be unlocked back to wallet balance,
 * and the funding-based downgrade leaves it alone (it has no funding to fall to).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_guarantees', function (Blueprint $table) {
            $table->boolean('is_granted')->default(false)->after('is_boosted');
            $table->unsignedBigInteger('granted_by')->nullable()->after('is_granted'); // the admin
        });
    }

    public function down(): void
    {
        Schema::table('user_guarantees', function (Blueprint $table) {
            $table->dropColumn(['is_granted', 'granted_by']);
        });
    }
};
