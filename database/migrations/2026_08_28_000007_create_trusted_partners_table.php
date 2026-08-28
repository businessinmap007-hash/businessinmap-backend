<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business's standing vouch for a specific, already-known user — "we've
 * dealt before, I trust him" — waiving the deposit_required_above check
 * (see CustomerCartService::assessDeposit) between exactly these two
 * parties, for every future order, until revoked. Pure trust: no money
 * moves and nothing is frozen on the voucher's side, unlike a friend
 * co-guarantor's coverage. See TrustedPartnerService.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trusted_partners')) {
            Schema::create('trusted_partners', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['business_id', 'user_id']);
                $table->index(['business_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_partners');
    }
};
