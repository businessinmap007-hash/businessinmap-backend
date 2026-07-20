<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When each side said it had agreed.
 *
 * Two columns rather than one "settled" flag, because a settlement is only a
 * settlement when BOTH said so, and the gap between the first tap and the
 * second is the meaningful part: it is what the other party is being shown and
 * asked to confirm. A single flag would lose who moved first, which is exactly
 * what an arbitrator would want to know if the agreement later falls apart.
 *
 * Separate from `*_cooperated_at`, which says "I am engaging with this" — a far
 * weaker claim than "I accept this is over".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->timestamp('client_settlement_agreed_at')->nullable()->after('business_cooperated_at');
            $table->timestamp('business_settlement_agreed_at')->nullable()->after('client_settlement_agreed_at');
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn(['client_settlement_agreed_at', 'business_settlement_agreed_at']);
        });
    }
};
