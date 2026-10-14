<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «الطبيب المعالج يصدر قرارًا بإجراء جراحي» — المالك، 2026-10-19. A procedure request may now start as the treating
 * physician's DECISION (status `advised`): who decided it, from which visit, and whether the patient asked on his own
 * (`source` patient) or the physician decided (`physician`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_requests', function (Blueprint $t) {
            if (! Schema::hasColumn('procedure_requests', 'physician_id')) {
                $t->unsignedBigInteger('physician_id')->nullable()->after('hospital_id')->index();
            }
            if (! Schema::hasColumn('procedure_requests', 'appointment_id')) {
                $t->unsignedBigInteger('appointment_id')->nullable()->after('physician_id');
            }
            if (! Schema::hasColumn('procedure_requests', 'source')) {
                $t->string('source', 12)->default('patient')->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procedure_requests', function (Blueprint $t) {
            foreach (['physician_id', 'appointment_id', 'source'] as $column) {
                if (Schema::hasColumn('procedure_requests', $column)) {
                    $t->dropColumn($column);
                }
            }
        });
    }
};
