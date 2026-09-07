<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fixed component list of a {@see \App\Models\MenuBundle} — "برجر +
 * بطاطس + مشروب", not a customer-facing choice. Cascades with its bundle;
 * cascades off its menu item too (a deleted item can't linger as a ghost
 * component), which is the one gap in "fully fixed": deleting a component
 * item silently shrinks the bundle rather than blocking the delete. Left
 * that way deliberately for now — the owner who deletes the item is the same
 * person who can re-add it to the bundle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_bundle_id')->constrained('menu_bundles')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->unsignedInteger('qty')->default(1);

            $table->timestamps();

            $table->index(['menu_bundle_id'], 'menu_bundle_items_bundle_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_bundle_items');
    }
};
