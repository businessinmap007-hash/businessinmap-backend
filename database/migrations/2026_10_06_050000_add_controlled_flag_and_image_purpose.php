<?php

use App\Support\ControlledMedicines;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «الأدوية المخدرة لا بد لها من صورة روشتة بخط الطبيب» — المالك، 2026-10-05.
 *   medicines.is_controlled — the dictionary drug is narcotic / psychotropic (see ControlledMedicines);
 *   images.purpose          — what a photo is FOR: `handwritten_prescription` is the doctor's paper prescription.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->boolean('is_controlled')->default(false)->index()->after('route');
        });

        Schema::table('images', function (Blueprint $table) {
            $table->string('purpose', 40)->nullable()->after('source');
        });

        ControlledMedicines::flagAll();
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });

        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn('is_controlled');
        });
    }
};
