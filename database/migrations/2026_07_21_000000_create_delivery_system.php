<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connected delivery loop (v2). A delivery driver accepts a ready order, scans
 * the restaurant's pickup QR (stage 1 → picked_up), then the customer scans the
 * driver's delivery QR (stage 2 → completed). Each successful delivery writes a
 * `delivery_completions` ledger row — the recorded success for BOTH the
 * restaurant (business_id) and the driver (driver_user_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_drivers')) {
            Schema::create('delivery_drivers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->boolean('is_active')->default(true);
                $table->string('phone', 40)->nullable();
                $table->string('vehicle_label', 120)->nullable();
                $table->unsignedInteger('assigned_count')->default(0);
                $table->unsignedInteger('picked_up_count')->default(0);
                $table->unsignedInteger('delivered_count')->default(0);
                $table->timestamps();

                $table->foreign('user_id', 'delivery_drivers_user_fk')
                    ->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('delivery_completions')) {
            Schema::create('delivery_completions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->unique();
                $table->unsignedBigInteger('business_id');
                $table->unsignedBigInteger('delivery_driver_id');
                $table->unsignedBigInteger('driver_user_id');
                $table->timestamp('completed_at');
                $table->timestamps();

                $table->index('business_id');
                $table->index('driver_user_id');
                $table->index('delivery_driver_id');
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'delivery_driver_id')) {
                    $table->unsignedBigInteger('delivery_driver_id')->nullable()->after('business_table_id');
                    $table->index(['delivery_driver_id']);
                }
                if (! Schema::hasColumn('orders', 'delivery_stage')) {
                    // null (unassigned) → assigned → picked_up → delivered
                    $table->string('delivery_stage', 20)->nullable()->after('delivery_driver_id');
                }
                if (! Schema::hasColumn('orders', 'pickup_token')) {
                    $table->string('pickup_token', 64)->nullable()->unique()->after('handover_confirmed_at');
                }
                if (! Schema::hasColumn('orders', 'delivery_token')) {
                    $table->string('delivery_token', 64)->nullable()->unique()->after('pickup_token');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['pickup_token', 'delivery_token'] as $col) {
                    if (Schema::hasColumn('orders', $col)) {
                        $table->dropUnique(['orders_' . $col . '_unique']);
                        $table->dropColumn($col);
                    }
                }
                foreach (['delivery_stage', 'delivery_driver_id'] as $col) {
                    if (Schema::hasColumn('orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('delivery_completions');
        Schema::dropIfExists('delivery_drivers');
    }
};
