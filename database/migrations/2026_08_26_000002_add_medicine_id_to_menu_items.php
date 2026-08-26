<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «الصيدلية لها قائمة بكل الادوية والاسعار اسمها قاموس الادوية قم بعمل المنيو
 *  الخاص بها» — المالك، 2026-08-26.
 *
 * A pharmacy's shelf is not a `line` option — it is one entry out of 25,065 in
 * the shared `medicines` dictionary, found by search rather than listed
 * whole. `nullOnDelete`: a dictionary row an admin later removes (only ever a
 * never-prescribed one, per `MedicineDictionaryController::destroy`) must not
 * take a pharmacy's priced row down with it — it just stops naming a drug.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_items') || Schema::hasColumn('menu_items', 'medicine_id')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreignId('medicine_id')->nullable()->after('menu_section_id')
                ->constrained('medicines')->nullOnDelete();
            $table->index(['business_id', 'medicine_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('menu_items', 'medicine_id')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('medicine_id');
        });
    }
};
