<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «إعلانك سينتهي قريبًا»: the owner of a listing is told once, a few days before it runs out of its days. `expiry_alerted_for`
 * holds the `expires_at` already announced, so renewing (a new `expires_at`) arms the next notice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('owner_listings', 'expiry_alerted_for')) {
                $table->timestamp('expiry_alerted_for')->nullable()->after('expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('owner_listings', fn (Blueprint $t) => $t->dropColumn('expiry_alerted_for'));
    }
};
