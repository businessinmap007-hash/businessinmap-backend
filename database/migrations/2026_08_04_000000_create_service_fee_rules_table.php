<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BIM-3.5 — dynamic service-fee rules.
 *
 * The fee a party pays was static: a fixed or percent amount per
 * (category, child, service) in category_child_service_fees. That row stays the
 * BASE; a rule here adjusts it for the circumstances of the specific operation —
 * its value, where it happens, when (peak hours), how proven the party is, and
 * whether the business subscribes.
 *
 * Layering, cheapest thing first: base fee → these rules (pricing policy) →
 * platform_service_fee_promotions (marketing discount, applied last so a
 * promotion always discounts the real policy price).
 *
 * `conditions` is JSON so a rule can test several things at once without a
 * column per predicate; ServiceFeeRule::matches() is the only reader. Every key
 * is optional and an absent key means "don't care".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Scope: null = applies to every service / category / child.
            $table->unsignedBigInteger('platform_service_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('child_id')->nullable();

            // Which side's fee this rule adjusts. 'any' = both.
            $table->enum('payer', ['business', 'client', 'any'])->default('any');
            $table->string('fee_code')->nullable(); // null = any fee code

            // Lower priority evaluates first; effects compound unless stop_on_match.
            $table->integer('priority')->default(0);
            $table->boolean('stop_on_match')->default(false);

            $table->json('conditions')->nullable();

            $table->enum('effect', [
                'percent_adjust',  // +/- N% of the running fee (10 = +10%, -10 = -10%)
                'fixed_adjust',    // +/- N currency on the running fee
                'multiply',        // running fee × N
                'override_fixed',  // fee becomes exactly N
                'override_percent',// fee becomes N% of the operation's base amount
                'waive',           // fee becomes 0
            ]);
            $table->decimal('effect_value', 12, 2)->nullable();

            // Clamps applied after this rule's effect.
            $table->decimal('min_fee', 12, 2)->nullable();
            $table->decimal('max_fee', 12, 2)->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'priority'], 'sfr_active_priority_idx');
            $table->index(['platform_service_id', 'child_id', 'payer'], 'sfr_scope_idx');
            $table->index(['starts_at', 'ends_at'], 'sfr_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_fee_rules');
    }
};
