<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «تأكيد الموظف على قبض الراتب من حسابه» (owner, 2026-10-30): the employee himself confirms, from his own account, that
 * he received the month's salary — beside the manager's record that it was paid. The two sides of a cash handover.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payslips', function (Blueprint $t) {
            $t->timestamp('received_confirmed_at')->nullable()->after('paid_by');
        });
    }

    public function down(): void
    {
        Schema::table('hr_payslips', function (Blueprint $t) {
            $t->dropColumn('received_confirmed_at');
        });
    }
};
