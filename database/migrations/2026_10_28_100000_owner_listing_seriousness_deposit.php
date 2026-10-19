<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «جدية الإعلان» (owner, 2026-10-28): to publish an owner listing the advertiser must either hold a guarantee of at least
 * bronze, or leave a deposit — priced in the control panel (event `owner_listing_deposit`, role `owner`, a fixed amount;
 * disabled = no condition) — held in the wallet and handed back when the listing is sold, finished, deleted or ends.
 *
 * `deposit_amount` is what is (or was) held for this listing and `deposit_state` where it stands: held | released.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('owner_listings', 'deposit_amount')) {
                $table->decimal('deposit_amount', 10, 2)->default(0)->after('reports_count');
                $table->string('deposit_state', 10)->nullable()->after('deposit_amount');
            }
        });

        // the price row: disabled until the owner sets an amount, so nothing changes by itself
        DB::table('platform_event_fees')->updateOrInsert(
            ['event_key' => 'owner_listing_deposit', 'role' => 'owner'],
            ['enabled' => 0, 'calc_type' => 'fixed', 'amount' => 0, 'min_amount' => null, 'max_amount' => null, 'currency' => 'EGP', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('platform_event_fees')->where('event_key', 'owner_listing_deposit')->delete();

        Schema::table('owner_listings', fn (Blueprint $t) => $t->dropColumn(['deposit_amount', 'deposit_state']));
    }
};
