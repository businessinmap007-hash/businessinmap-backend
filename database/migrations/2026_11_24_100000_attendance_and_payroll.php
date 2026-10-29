<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «الحضور والمرتبات» — the desktop suite's phase 2 (owner, 2026-10-30, canvas approved). Built on the existing
 * `staff_attendances` (one row per person per day) and its rotating QR: shifts, the business's own lateness / absence /
 * overtime rules, leave and punch-correction requests, advances in installments, and a monthly payroll run that locks.
 *
 * The law's numbers (social insurance, wage tax brackets, overtime minimum, leave days) change by decree: they live in
 * `hr_law_tables`, one row per kind and effective date — a payroll month reads the row in force that month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('business_id')->unique();
            $t->unsignedSmallInteger('grace_minutes')->default(15);
            // [{upto_minutes:int|null, day_fraction:float}] — lateness / early leave tiers
            $t->json('late_tiers')->nullable();
            $t->decimal('absence_day_fraction', 4, 2)->default(1);
            $t->decimal('monthly_cap_days', 5, 2)->nullable();
            $t->decimal('overtime_day_pct', 5, 2)->default(35);
            $t->decimal('overtime_night_pct', 5, 2)->default(70);
            $t->string('night_from', 5)->default('22:00');
            $t->string('night_to', 5)->default('06:00');
            $t->unsignedTinyInteger('days_per_month')->default(30);
            $t->unsignedTinyInteger('hours_per_day')->default(8);
            // Carbon dayOfWeek (0 = Sunday … 6 = Saturday); Friday by default
            $t->json('rest_days')->nullable();
            $t->timestamps();
        });

        Schema::create('hr_shifts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('business_id')->index();
            $t->string('name', 80);
            $t->string('starts_at', 5);
            $t->string('ends_at', 5);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('hr_employees', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('business_id');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('shift_id')->nullable();
            $t->decimal('basic_salary', 12, 2)->default(0);
            // [{name, amount, insurable:bool}]
            $t->json('allowances')->nullable();
            $t->decimal('insurable_wage', 12, 2)->nullable();
            $t->boolean('insured')->default(true);
            $t->boolean('taxable')->default(true);
            $t->date('hire_date')->nullable();
            $t->date('birth_date')->nullable();
            $t->json('rest_days')->nullable();
            $t->string('pay_method', 20)->default('cash');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['business_id', 'user_id']);
        });

        Schema::create('hr_holidays', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('business_id')->index();
            $t->date('date');
            $t->string('name', 120);
            $t->timestamps();
            $t->unique(['business_id', 'date']);
        });

        // leave, punch correction and overtime — one queue the manager answers
        Schema::create('hr_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('business_id')->index();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('kind', 16);
            $t->date('date_from');
            $t->date('date_to');
            $t->json('data')->nullable();
            $t->string('reason', 300)->nullable();
            $t->string('status', 12)->default('pending');
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->string('decision_note', 300)->nullable();
            $t->timestamps();
        });

        Schema::create('hr_advances', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('business_id')->index();
            $t->unsignedBigInteger('user_id')->index();
            $t->decimal('amount', 12, 2);
            $t->unsignedTinyInteger('installments');
            $t->decimal('installment', 12, 2);
            $t->decimal('remaining', 12, 2);
            $t->string('first_month', 7);
            $t->date('given_on');
            $t->string('note', 300)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });

        // one-off for a month: a bonus or a penalty, always with its reason
        Schema::create('hr_adjustments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('business_id')->index();
            $t->unsignedBigInteger('user_id');
            $t->string('month', 7);
            $t->string('kind', 10);
            $t->decimal('amount', 12, 2);
            $t->string('reason', 300);
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->index(['business_id', 'month']);
        });

        Schema::create('hr_law_tables', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 30);
            $t->date('effective_from');
            $t->json('data');
            $t->string('source', 300)->nullable();
            $t->timestamps();
            $t->unique(['kind', 'effective_from']);
        });

        Schema::create('hr_payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('business_id');
            $t->string('month', 7);
            $t->string('status', 12)->default('draft');
            $t->json('totals')->nullable();
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'month']);
        });

        Schema::create('hr_payslips', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('run_id')->index();
            $t->unsignedBigInteger('business_id');
            $t->unsignedBigInteger('user_id');
            $t->json('lines');
            $t->json('attendance')->nullable();
            $t->decimal('gross', 12, 2);
            $t->decimal('deductions', 12, 2);
            $t->decimal('net', 12, 2);
            $t->decimal('employer_insurance', 12, 2)->default(0);
            $t->timestamp('paid_at')->nullable();
            $t->string('paid_method', 20)->nullable();
            $t->unsignedBigInteger('paid_by')->nullable();
            $t->timestamps();
            $t->unique(['run_id', 'user_id']);
            $t->foreign('run_id')->references('id')->on('hr_payroll_runs')->cascadeOnDelete();
        });

        Schema::table('staff_attendances', function (Blueprint $t) {
            $t->unsignedBigInteger('corrected_by')->nullable()->after('checked_out_at');
            $t->unsignedBigInteger('correction_request_id')->nullable()->after('corrected_by');
        });

        $now = now();
        $rows = [
            // Social Insurance Law 148/2019 — limits rise 15% each January (BDO; NOSI announcement Nov 2025)
            ['social_insurance', '2025-01-01', ['employee_pct' => 11, 'employer_pct' => 18.75, 'min_wage' => 2300, 'max_wage' => 14500], 'Law 148/2019; BDO / Payspace 2025 limits'],
            ['social_insurance', '2026-01-01', ['employee_pct' => 11, 'employer_pct' => 18.75, 'min_wage' => 2700, 'max_wage' => 16700], 'Law 148/2019; NOSI Jan 2026 limits (Business Today Egypt, Payspace)'],
            // Income Tax Law 91/2005 as amended by Law 7/2024 — annual brackets after the personal exemption
            ['wage_tax', '2024-03-01', [
                'exemption' => 20000,
                'brackets' => [[40000, 0], [55000, 10], [70000, 15], [200000, 20], [400000, 22.5], [1200000, 25], [null, 27.5]],
                // above this annual taxable income the law withdraws the lower brackets: such a payslip is flagged for review
                'review_above' => 600000,
            ], 'Law 7/2024 (Deloitte, Andersen)'],
            // Labour Law 14/2025 — overtime at least +35% by day and +70% by night
            ['overtime', '2025-09-01', ['day_min_pct' => 35, 'night_min_pct' => 70], 'Labour Law 14/2025 (Mondaq)'],
            ['annual_leave', '2025-09-01', ['first_year' => 15, 'after_first_year' => 21, 'after_ten_years_or_fifty' => 30], 'Labour Law 14/2025'],
        ];
        foreach ($rows as [$kind, $from, $data, $source]) {
            DB::table('hr_law_tables')->updateOrInsert(
                ['kind' => $kind, 'effective_from' => $from],
                ['data' => json_encode($data), 'source' => $source, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        Schema::table('staff_attendances', function (Blueprint $t) {
            $t->dropColumn(['corrected_by', 'correction_request_id']);
        });
        foreach (['hr_payslips', 'hr_payroll_runs', 'hr_law_tables', 'hr_adjustments', 'hr_advances', 'hr_requests', 'hr_holidays', 'hr_employees', 'hr_shifts', 'hr_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
