<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recreates the deposits table's original CREATE migration.
 *
 * This table existed in the database with no corresponding create_* migration
 * in the repo (only later ALTER migrations referencing it) - a fresh install
 * running `php artisan migrate` would fail before ever reaching those ALTERs.
 * Schema below matches the live table exactly (via SHOW CREATE TABLE).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deposits')) {
            return;
        }

        Schema::create('deposits', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('bookable_item_id')->nullable();
            $table->unsignedBigInteger('platform_service_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('category_child_id')->nullable();

            $table->string('target_type', 191);
            $table->unsignedBigInteger('target_id');

            $table->enum('mode', ['wallet_hold', 'external_verification', 'both'])->default('wallet_hold');
            $table->enum('calculation_base', ['first_day', 'total'])->default('first_day');
            $table->enum('deposit_type', ['percent', 'fixed'])->default('percent');
            $table->decimal('deposit_value', 12, 2)->default(0);
            $table->decimal('deposit_percent_used', 5, 2)->default(0);
            $table->decimal('deposit_base_amount', 12, 2)->default(0);
            $table->decimal('deposit_amount', 12, 2)->default(0);

            $table->boolean('wallet_hold_required')->default(false);
            $table->decimal('wallet_hold_amount', 12, 2)->default(0);
            $table->enum('wallet_hold_status', ['not_required', 'pending', 'held', 'released', 'refunded', 'captured', 'failed'])->default('not_required');
            $table->unsignedBigInteger('client_wallet_transaction_id')->nullable();

            $table->boolean('business_counter_hold_required')->default(false);
            $table->decimal('business_counter_hold_percent', 5, 2)->default(0);
            $table->decimal('business_counter_hold_amount', 12, 2)->default(0);
            $table->enum('business_counter_hold_status', ['not_required', 'pending', 'held', 'released', 'refunded', 'captured', 'failed'])->default('not_required');
            $table->unsignedBigInteger('business_wallet_transaction_id')->nullable();

            $table->boolean('external_deposit_required')->default(false);
            $table->decimal('external_deposit_amount', 12, 2)->default(0);
            $table->enum('external_deposit_status', ['not_required', 'pending', 'submitted', 'verified', 'rejected', 'cancelled'])->default('not_required');
            $table->string('external_reference', 190)->nullable();
            $table->dateTime('external_paid_at')->nullable();
            $table->dateTime('external_verified_at')->nullable();
            $table->unsignedBigInteger('external_verified_by')->nullable();
            $table->string('external_proof_path', 500)->nullable();
            $table->text('external_notes')->nullable();

            $table->boolean('affects_remaining_amount')->default(false);
            $table->decimal('remaining_amount_before_external', 12, 2)->default(0);
            $table->decimal('remaining_amount_after_external', 12, 2)->default(0);

            $table->json('policy_snapshot')->nullable();

            $table->decimal('total_amount', 12, 2);
            $table->unsignedTinyInteger('client_percent')->default(0);
            $table->unsignedTinyInteger('business_percent')->default(0);
            $table->decimal('client_amount', 12, 2)->default(0);
            $table->decimal('business_amount', 12, 2)->default(0);

            $table->enum('status', ['frozen', 'released', 'refunded', 'dispute'])->default('frozen');
            $table->timestamp('dispute_opened_at')->nullable();
            $table->enum('dispute_opened_by', ['client', 'business', 'admin'])->nullable();
            $table->text('dispute_reason')->nullable();

            $table->boolean('client_confirmed')->default(false);
            $table->boolean('business_confirmed')->default(false);
            $table->tinyInteger('release_agreed_client')->default(0);
            $table->tinyInteger('release_agreed_business')->default(0);
            $table->tinyInteger('refund_agreed_client')->default(0);
            $table->tinyInteger('refund_agreed_business')->default(0);
            $table->boolean('client_outside_bim')->default(false);
            $table->boolean('business_outside_bim')->default(false);

            $table->timestamp('released_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();

            $table->index(['target_type', 'target_id'], 'deposits_target_type_target_id_index');
            $table->index('client_id', 'deposits_client_id_index');
            $table->index('business_id', 'deposits_business_id_index');
            $table->index('client_id', 'esc_client_idx');
            $table->index('business_id', 'esc_business_idx');
            $table->index('status', 'esc_status_idx');
            $table->index(['target_type', 'target_id'], 'esc_target_idx');
            $table->index('released_at', 'esc_released_at_idx');
            $table->index('refunded_at', 'esc_refunded_at_idx');
            $table->index('booking_id', 'idx_deposits_booking_id');
            $table->index('external_deposit_status', 'idx_deposits_external_status');
            $table->index('wallet_hold_status', 'idx_deposits_wallet_hold_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
