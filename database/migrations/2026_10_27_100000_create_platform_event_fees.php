<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The platform's fee on an EVENT that has no child trade to hang a fee on (owner, 2026-10-27): swapping phone numbers
 * on an owner listing, changing a training or a nutrition program. Set from the control panel, like the fees of delivery
 * and bookings, for each party the event names — and charged only when every party has the balance for it.
 *
 * `platform_event_fees` is the price list (one row per event and party); `platform_event_charges` is what was taken, the
 * record a report reads and the clock a «one fee per change session» rule looks at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_event_fees', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 40);
            $table->string('role', 20);                          // buyer | seller | trainer | client …
            $table->boolean('enabled')->default(false);
            $table->string('calc_type', 8)->default('fixed');    // fixed | percent (of the event's base amount)
            $table->decimal('amount', 12, 2)->default(0);        // pounds, or the percent
            $table->decimal('min_amount', 12, 2)->nullable();
            $table->decimal('max_amount', 12, 2)->nullable();
            $table->string('currency', 8)->default('EGP');
            $table->timestamps();

            $table->unique(['event_key', 'role']);
        });

        Schema::create('platform_event_charges', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 40)->index();
            $table->string('role', 20);
            $table->unsignedBigInteger('user_id')->index();
            $table->string('reference_type', 40);                // owner_listing_inquiry | training_plan …
            $table->unsignedBigInteger('reference_id');
            $table->string('occurrence', 40)->default('1');
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('wallet_transaction_id')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id', 'event_key'], 'pec_reference_index');
        });

        $now = now();
        // what the first version of the owner-listing swap took: 1% of the price between 20 and 500, half from each side
        $rows = [
            ['owner_listing_contact', 'buyer', true, 'percent', 0.5, 10, 250],
            ['owner_listing_contact', 'seller', true, 'percent', 0.5, 10, 250],
            // a program changed after it started: off until the owner prices it
            ['training_program_change', 'trainer', false, 'fixed', 0, null, null],
            ['training_program_change', 'client', false, 'fixed', 0, null, null],
            ['nutrition_program_change', 'trainer', false, 'fixed', 0, null, null],
            ['nutrition_program_change', 'client', false, 'fixed', 0, null, null],
        ];

        foreach ($rows as [$event, $role, $enabled, $calc, $amount, $min, $max]) {
            DB::table('platform_event_fees')->insert([
                'event_key' => $event, 'role' => $role, 'enabled' => $enabled, 'calc_type' => $calc, 'amount' => $amount,
                'min_amount' => $min, 'max_amount' => $max, 'currency' => 'EGP', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_event_charges');
        Schema::dropIfExists('platform_event_fees');
    }
};
