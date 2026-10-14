<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «القسم» — المالك، 2026-10-19: a physician who works for a hospital is placed in ONE of the departments the hospital
 * ticked («جراحة عيون»). The department is an option of «تخصصات طبية», the same ids the «الأقسام والأطباء» tab lists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('business_staff', 'department_option_id')) {
            Schema::table('business_staff', function (Blueprint $t) {
                $t->unsignedBigInteger('department_option_id')->nullable()->after('is_provider')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('business_staff', 'department_option_id')) {
            Schema::table('business_staff', fn (Blueprint $t) => $t->dropColumn('department_option_id'));
        }
    }
};
