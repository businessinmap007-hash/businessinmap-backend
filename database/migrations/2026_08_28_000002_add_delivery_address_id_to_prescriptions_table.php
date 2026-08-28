<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prescription delivery took only a free-text `delivery_address` string —
 * every other delivery-needing flow (menu/retail checkout) resolves against
 * the saved address book instead. This adds the same optional pointer;
 * `delivery_address` stays as the snapshot column (same shape as
 * orders.address), never rewritten by a later address-book edit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('prescriptions', 'delivery_address_id')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->foreignId('delivery_address_id')->nullable()->after('delivery_address')
                    ->constrained('addresses')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prescriptions', 'delivery_address_id')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('delivery_address_id');
            });
        }
    }
};
