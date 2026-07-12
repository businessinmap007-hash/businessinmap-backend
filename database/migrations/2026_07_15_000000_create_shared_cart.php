<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared / group cart. A shared cart is an ordinary draft Order owned by the
 * host (orders.user_id); friends join it via a share token and each adds their
 * own lines. order_items.added_by_user_id attributes each line to whoever added
 * it (null on a personal cart — the owner). order_participants lists the host +
 * members.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'share_token')) {
                    $table->string('share_token', 40)->nullable()->unique()->after('status');
                }
                if (! Schema::hasColumn('orders', 'is_shared')) {
                    $table->boolean('is_shared')->default(false)->after('share_token');
                }
            });
        }

        if (Schema::hasTable('order_items') && ! Schema::hasColumn('order_items', 'added_by_user_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->unsignedBigInteger('added_by_user_id')->nullable()->after('order_id');
                $table->index('added_by_user_id', 'order_items_added_by_idx');
                $table->foreign('added_by_user_id', 'order_items_added_by_fk')
                    ->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('order_participants')) {
            Schema::create('order_participants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('user_id');
                $table->enum('role', ['host', 'member'])->default('member');
                $table->timestamps();
                $table->unique(['order_id', 'user_id'], 'order_participants_order_user_unique');
                $table->index('user_id', 'order_participants_user_idx');
                $table->foreign('order_id', 'order_participants_order_fk')
                    ->references('id')->on('orders')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('user_id', 'order_participants_user_fk')
                    ->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_participants');

        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'added_by_user_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign('order_items_added_by_fk');
                $table->dropIndex('order_items_added_by_idx');
                $table->dropColumn('added_by_user_id');
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['is_shared', 'share_token'] as $col) {
                    if (Schema::hasColumn('orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
