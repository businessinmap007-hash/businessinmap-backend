<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `delivery_drivers` was one global, self-registering pool (any user could
 * register and pick up ANY business's ready order — see DeliveryDispatchService).
 * A restaurant/supermarket now wants its OWN roster: drivers who work only for
 * them, that they add/manage themselves, scoped to their own orders.
 *
 * NULL business_id = the existing platform-wide freelance pool, unchanged.
 * A set business_id = a private driver, linked by an EXISTING user's phone
 * (same pattern as business_staff — never mints a new account on the spot).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('delivery_drivers', 'business_id')) {
            Schema::table('delivery_drivers', function (Blueprint $table) {
                $table->foreignId('business_id')->nullable()->after('user_id')
                    ->constrained('users')->nullOnDelete();
                $table->index(['business_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('delivery_drivers', 'business_id')) {
            Schema::table('delivery_drivers', function (Blueprint $table) {
                $table->dropIndex(['business_id', 'is_active']);
                $table->dropConstrainedForeignId('business_id');
            });
        }
    }
};
