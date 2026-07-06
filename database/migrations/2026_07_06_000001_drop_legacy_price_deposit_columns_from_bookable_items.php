<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the legacy price/deposit columns from bookable_items.
 *
 * Pricing and deposit are now single-source in business_service_prices (per
 * item type) and the business deposit policy; bookable_items is inventory only
 * (code / capacity / quantity / active). The per-unit deposit override that
 * used to read these columns has been removed. See docs/services-blueprint.md.
 *
 * Idempotent: each column is guarded by Schema::hasColumn so re-running (or
 * running against a DB that already lacks a column) is a no-op.
 */
return new class extends Migration
{
    /**
     * All legacy price/deposit columns to remove from bookable_items.
     */
    private array $columns = [
        'price',
        'deposit_enabled',
        'deposit_percent',
        'deposit_policy_mode',
        'deposit_mode',
        'deposit_calculation_base',
        'deposit_type',
        'deposit_value',
        'max_deposit_percent',
        'min_deposit_amount',
        'max_deposit_amount',
        'external_verification_enabled',
        'wallet_hold_enabled',
        'business_counter_hold_enabled',
        'business_counter_hold_percent',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('bookable_items')) {
            return;
        }

        $present = array_values(array_filter(
            $this->columns,
            fn (string $column) => Schema::hasColumn('bookable_items', $column)
        ));

        if ($present === []) {
            return;
        }

        Schema::table('bookable_items', function (Blueprint $table) use ($present) {
            $table->dropColumn($present);
        });
    }

    /**
     * Best-effort restore. These were inventory-only remnants with no
     * authoritative source, so they come back as defaulted/nullable columns.
     */
    public function down(): void
    {
        if (! Schema::hasTable('bookable_items')) {
            return;
        }

        Schema::table('bookable_items', function (Blueprint $table) {
            if (! Schema::hasColumn('bookable_items', 'price')) {
                $table->decimal('price', 12, 2)->default(0)->after('code');
            }
            if (! Schema::hasColumn('bookable_items', 'deposit_enabled')) {
                $table->boolean('deposit_enabled')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('bookable_items', 'deposit_percent')) {
                $table->unsignedTinyInteger('deposit_percent')->default(0)->after('deposit_enabled');
            }
            if (! Schema::hasColumn('bookable_items', 'deposit_policy_mode')) {
                $table->string('deposit_policy_mode', 50)->nullable()->after('deposit_percent');
            }
            if (! Schema::hasColumn('bookable_items', 'deposit_mode')) {
                $table->string('deposit_mode', 50)->nullable()->after('deposit_policy_mode');
            }
            if (! Schema::hasColumn('bookable_items', 'deposit_calculation_base')) {
                $table->string('deposit_calculation_base', 50)->nullable()->after('deposit_mode');
            }
            if (! Schema::hasColumn('bookable_items', 'deposit_type')) {
                $table->string('deposit_type', 50)->nullable()->after('deposit_calculation_base');
            }
            if (! Schema::hasColumn('bookable_items', 'deposit_value')) {
                $table->decimal('deposit_value', 12, 2)->nullable()->after('deposit_type');
            }
            if (! Schema::hasColumn('bookable_items', 'max_deposit_percent')) {
                $table->decimal('max_deposit_percent', 5, 2)->nullable()->after('deposit_value');
            }
            if (! Schema::hasColumn('bookable_items', 'min_deposit_amount')) {
                $table->decimal('min_deposit_amount', 12, 2)->nullable()->after('max_deposit_percent');
            }
            if (! Schema::hasColumn('bookable_items', 'max_deposit_amount')) {
                $table->decimal('max_deposit_amount', 12, 2)->nullable()->after('min_deposit_amount');
            }
            if (! Schema::hasColumn('bookable_items', 'external_verification_enabled')) {
                $table->boolean('external_verification_enabled')->default(false)->after('max_deposit_amount');
            }
            if (! Schema::hasColumn('bookable_items', 'wallet_hold_enabled')) {
                $table->boolean('wallet_hold_enabled')->default(false)->after('external_verification_enabled');
            }
            if (! Schema::hasColumn('bookable_items', 'business_counter_hold_enabled')) {
                $table->boolean('business_counter_hold_enabled')->default(false)->after('wallet_hold_enabled');
            }
            if (! Schema::hasColumn('bookable_items', 'business_counter_hold_percent')) {
                $table->decimal('business_counter_hold_percent', 5, 2)->nullable()->after('business_counter_hold_enabled');
            }
        });
    }
};
