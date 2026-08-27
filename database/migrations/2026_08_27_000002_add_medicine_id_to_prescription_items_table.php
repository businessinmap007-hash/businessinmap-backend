<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «يجب ان يختار من الاصناف حتى تكون نسبة الخطأ صفر» — المالك، 2026-08-27. A
 * prescription line used to be free-text `name`, and `PrescriptionService`
 * silently grew the shared dictionary from whatever a doctor typed — a typo
 * became a real, selectable drug for the next doctor. Nullable so existing
 * free-text prescriptions keep working unchanged; new ones are required to
 * name a real dictionary row (enforced in PrescriptionController, not here).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prescription_items') || Schema::hasColumn('prescription_items', 'medicine_id')) {
            return;
        }

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->foreignId('medicine_id')->nullable()->after('prescription_id')
                ->constrained('medicines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('prescription_items') || ! Schema::hasColumn('prescription_items', 'medicine_id')) {
            return;
        }

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('medicine_id');
        });
    }
};
