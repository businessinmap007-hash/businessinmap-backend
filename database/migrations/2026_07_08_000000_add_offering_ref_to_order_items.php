<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generalize order_items from a menu-only line (`menu_id`) to a polymorphic
 * **offering** reference, so an order line can point at any offering — a menu
 * item now, a catalog-product listing or a bookable item type later (Phase 3).
 * `menu_id` stays for backward compatibility; both tables are currently empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'offering_type')) {
                $table->string('offering_type', 100)->nullable()->after('menu_id');
            }
            if (! Schema::hasColumn('order_items', 'offering_id')) {
                $table->unsignedBigInteger('offering_id')->nullable()->after('offering_type');
            }
        });

        if (! $this->hasIndex('order_items', 'order_items_offering_index')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->index(['offering_type', 'offering_id'], 'order_items_offering_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        if ($this->hasIndex('order_items', 'order_items_offering_index')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropIndex('order_items_offering_index');
            });
        }

        Schema::table('order_items', function (Blueprint $table) {
            foreach (['offering_type', 'offering_id'] as $col) {
                if (Schema::hasColumn('order_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn ($row) => $row->Key_name === $index);
    }
};
