<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «الدواء المخدر … روشتة بخط اليد ويُختم عليها «تم الصرف» بعد المرات التي سيصرف فيها» — المالك، 2026-10-11.
 *
 * A prescription holding a controlled drug is dispensed against the doctor\'s handwritten paper, which may be filled
 * more than once: the doctor says how many times (`dispense_limit`, 1 = once, the old behaviour), each dispensing is
 * counted, and only the LAST one makes the prescription `dispensed` — the moment the pharmacist stamps the paper
 * «تم الصرف».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('dispense_limit')->default(1)->after('status');
            $table->unsignedTinyInteger('dispense_count')->default(0)->after('dispense_limit');
            $table->timestamp('last_dispensed_at')->nullable()->after('dispense_count');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn(['dispense_limit', 'dispense_count', 'last_dispensed_at']);
        });
    }
};
