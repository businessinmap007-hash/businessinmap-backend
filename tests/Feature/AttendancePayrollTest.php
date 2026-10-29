<?php

namespace Tests\Feature;

use App\Models\BusinessStaff;
use App\Models\Hr\Advance;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Services\Hr\PayrollCalculator;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «الحضور والمرتبات» (desktop suite phase 2, canvas approved 2026-10-30): the day is read against the shift and the
 * business's own rules, requests go through the manager, and the payroll goes gross → net by the law's dated tables —
 * the canvas's worked example (أحمد, September 2026) comes out to the piastre.
 */
class AttendancePayrollTest extends TestCase
{
    use DatabaseTransactions;

    private const MONTH = '2026-09';

    private User $owner;
    private User $ahmed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->user(User::TYPE_BUSINESS, ['category_id' => 16, 'category_child_id' => 245]);
        $this->ahmed = $this->user(User::TYPE_CLIENT);
        BusinessStaff::create(['business_id' => $this->owner->id, 'user_id' => $this->ahmed->id, 'title' => 'مسؤول المخزن', 'capabilities' => ['inventory'], 'is_active' => 1]);
    }

    private function user(string $type, array $extra = []): User
    {
        return User::create([
            'name' => 'Zz Hr ' . uniqid(), 'email' => 'zz-hr-' . uniqid() . '@test.local', 'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'), 'type' => $type, 'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ] + $extra);
    }

    private function punch(string $date, ?string $in, ?string $out): void
    {
        StaffAttendance::create([
            'business_id' => $this->owner->id, 'user_id' => $this->ahmed->id, 'date' => $date,
            'checked_in_at' => $in ? Carbon::parse("$date $in") : null, 'checked_out_at' => $out ? Carbon::parse("$date $out") : null,
        ]);
    }

    /** A morning shift 9–5, Friday off, the canvas's salary; every work day of September punched on time but the 1st. */
    private function ahmedsMonth(): void
    {
        Sanctum::actingAs($this->owner);
        $shift = $this->postJson('/api/v2/business/hr/shifts', ['name' => 'صباحي', 'starts_at' => '09:00', 'ends_at' => '17:00'])->assertCreated()->json('data.shift.id');
        $this->patchJson("/api/v2/business/hr/employees/{$this->ahmed->id}", [
            'shift_id' => $shift, 'hire_date' => '2024-01-01', 'basic_salary' => 9000,
            'allowances' => [['name' => 'بدل مواصلات', 'amount' => 1500, 'insurable' => true]],
        ])->assertOk();

        for ($d = Carbon::parse(self::MONTH . '-01'); $d->month === 9; $d->addDay()) {
            if ($d->isFriday()) {
                continue;
            }
            $d->day === 1 ? $this->punch($d->toDateString(), '09:40', '17:00') : $this->punch($d->toDateString(), '09:00', '17:00');
        }
    }

    public function test_the_day_is_read_against_the_shift_and_the_rules(): void
    {
        Sanctum::actingAs($this->owner);
        $shift = $this->postJson('/api/v2/business/hr/shifts', ['name' => 'صباحي', 'starts_at' => '09:00', 'ends_at' => '17:00'])->json('data.shift.id');
        $this->patchJson("/api/v2/business/hr/employees/{$this->ahmed->id}", ['shift_id' => $shift, 'hire_date' => '2024-01-01'])->assertOk();
        $this->postJson('/api/v2/business/hr/holidays', ['date' => '2026-09-03', 'name' => 'إجازة رسمية'])->assertCreated();

        $this->punch('2026-09-01', '09:40', '19:00');   // late 40 → a quarter day; 2 hours after the shift
        $this->punch('2026-09-02', '08:55', '16:20');   // left 40 minutes early → a quarter day
        // the 5th (Saturday) no punch at all → absent

        $board = collect($this->getJson('/api/v2/business/hr/attendance?month=' . self::MONTH)->assertOk()->json('data.employees'))->firstWhere('user_id', $this->ahmed->id);
        $days = collect($board['days'])->keyBy('date');

        $this->assertSame('late', $days['2026-09-01']['status']);
        $this->assertSame(40, $days['2026-09-01']['late_minutes']);
        $this->assertSame(120, $days['2026-09-01']['overtime_minutes']);
        $this->assertEquals(0.25, $days['2026-09-01']['deduction']);
        $this->assertSame(40, $days['2026-09-02']['early_minutes']);
        $this->assertSame('holiday', $days['2026-09-03']['status']);
        $this->assertSame('rest', $days['2026-09-04']['status']);
        $this->assertSame('absent', $days['2026-09-05']['status']);
        // the suggested overtime is not paid until approved
        $this->assertSame(0, $board['totals']['approved_overtime_minutes']);
    }

    public function test_the_canvas_payslip_comes_out_to_the_piastre(): void
    {
        $this->ahmedsMonth();
        $this->postJson('/api/v2/business/hr/requests', ['user_id' => $this->ahmed->id, 'kind' => 'overtime', 'date_from' => '2026-09-10', 'minutes' => 360])->assertCreated();
        $this->postJson('/api/v2/business/hr/advances', ['user_id' => $this->ahmed->id, 'amount' => 3000, 'installments' => 6, 'first_month' => self::MONTH])->assertCreated();

        $slip = collect($this->postJson('/api/v2/business/hr/payroll/' . self::MONTH . '/generate')->assertOk()->json('data.payslips'))->firstWhere('user_id', $this->ahmed->id);
        $line = fn (string $key) => collect($slip['lines'])->firstWhere('key', $key)['amount'] ?? null;

        $this->assertEquals(303.75, $line('overtime'));     // 6 h × 37.5 × 135%
        $this->assertEquals(75, $line('attendance'));       // a quarter of a 300 day
        $this->assertEquals(500, $line('advance'));
        $this->assertEquals(1155, $line('insurance'));      // 11% of 10,500
        $this->assertEquals(727.25, $line('tax'));          // 94,885 a year → 8,727 ÷ 12
        $this->assertEquals(10803.75, $slip['gross']);
        $this->assertEquals(8346.5, $slip['net']);
        $this->assertEquals(1968.75, $slip['employer_insurance']);
    }

    public function test_approving_locks_the_month_takes_the_installment_and_reaches_the_employee(): void
    {
        $this->ahmedsMonth();
        $this->postJson('/api/v2/business/hr/advances', ['user_id' => $this->ahmed->id, 'amount' => 3000, 'installments' => 6, 'first_month' => self::MONTH])->assertCreated();
        $this->postJson('/api/v2/business/hr/payroll/' . self::MONTH . '/generate')->assertOk();

        Sanctum::actingAs($this->ahmed);
        $this->getJson('/api/v2/staff/hr/me?month=' . self::MONTH)->assertOk()->assertJsonCount(0, 'data.payslips');

        Sanctum::actingAs($this->owner);
        $run = $this->postJson('/api/v2/business/hr/payroll/' . self::MONTH . '/approve')->assertOk()->assertJsonPath('data.run.status', 'approved');
        $this->postJson('/api/v2/business/hr/payroll/' . self::MONTH . '/generate')->assertStatus(422);
        $this->postJson('/api/v2/business/hr/adjustments', ['user_id' => $this->ahmed->id, 'month' => self::MONTH, 'kind' => 'bonus', 'amount' => 100, 'reason' => 'x'])->assertStatus(422);
        $this->assertEquals(2500, (float) Advance::query()->where('user_id', $this->ahmed->id)->value('remaining'));

        $slipId = $run->json('data.payslips.0.id');
        $this->postJson("/api/v2/business/hr/payslips/{$slipId}/paid", ['method' => 'cash'])->assertOk()->assertJsonPath('data.payslip.paid_method', 'cash');

        Sanctum::actingAs($this->ahmed);
        $this->getJson('/api/v2/staff/hr/me?month=' . self::MONTH)->assertOk()->assertJsonPath('data.payslips.0.id', $slipId)->assertJsonPath('data.payslips.0.month', self::MONTH);
        $this->assertTrue(DB::table('app_notifications')->where('user_id', $this->ahmed->id)->where('source_type', 'hr_payslip')->exists());
    }

    public function test_leave_is_held_to_the_balance_and_a_correction_writes_the_punch(): void
    {
        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v2/business/hr/employees/{$this->ahmed->id}", ['hire_date' => '2024-01-01'])->assertOk();

        Sanctum::actingAs($this->ahmed);
        $this->getJson('/api/v2/staff/hr/me')->assertOk()->assertJsonPath('data.leave.entitlement', 21);
        $this->postJson('/api/v2/staff/hr/requests', ['kind' => 'leave', 'leave_type' => 'annual', 'date_from' => '2026-11-01', 'date_to' => '2026-12-15'])->assertStatus(422);
        $leave = $this->postJson('/api/v2/staff/hr/requests', ['kind' => 'leave', 'leave_type' => 'annual', 'date_from' => '2026-11-02', 'date_to' => '2026-11-04'])
            ->assertCreated()->json('data.request');
        $this->assertSame(3, $leave['data']['days']);
        $fix = $this->postJson('/api/v2/staff/hr/requests', ['kind' => 'correction', 'date_from' => '2026-10-20', 'check_out' => '17:10', 'reason' => 'نسيت البصمة'])
            ->assertCreated()->json('data.request.id');

        Sanctum::actingAs($this->owner);
        $this->assertTrue(DB::table('app_notifications')->where('user_id', $this->owner->id)->where('source_type', 'hr_request')->exists());
        $this->postJson("/api/v2/business/hr/requests/{$leave['id']}/decide", ['approve' => true])->assertOk();
        $this->postJson("/api/v2/business/hr/requests/{$fix}/decide", ['approve' => true])->assertOk();
        $this->postJson("/api/v2/business/hr/requests/{$fix}/decide", ['approve' => false])->assertStatus(422);

        $punch = StaffAttendance::query()->where('user_id', $this->ahmed->id)->whereDate('date', '2026-10-20')->first();
        $this->assertSame('17:10', $punch->checked_out_at->format('H:i'));
        $this->assertSame($this->owner->id, (int) $punch->corrected_by);

        Sanctum::actingAs($this->ahmed);
        $this->getJson('/api/v2/staff/hr/me')->assertOk()->assertJsonPath('data.leave.remaining', 18);
    }

    public function test_who_may_see_and_change_pay(): void
    {
        $hr = $this->user(User::TYPE_CLIENT);
        BusinessStaff::create(['business_id' => $this->owner->id, 'user_id' => $hr->id, 'capabilities' => [BusinessCapability::ATTENDANCE], 'is_active' => 1]);

        Sanctum::actingAs($hr);
        $row = collect($this->getJson('/api/v2/business/hr/employees')->assertOk()->json('data.employees'))->firstWhere('user_id', $this->ahmed->id);
        $this->assertArrayNotHasKey('basic_salary', $row);
        $this->patchJson("/api/v2/business/hr/employees/{$this->ahmed->id}", ['basic_salary' => 99999])->assertStatus(403);
        $this->patchJson("/api/v2/business/hr/employees/{$this->ahmed->id}", ['hire_date' => '2025-01-01'])->assertOk();
        $this->postJson('/api/v2/business/hr/payroll/' . self::MONTH . '/generate')->assertStatus(403);

        // the business pays more than the law, never less
        $this->patchJson('/api/v2/business/hr/settings', ['overtime_day_pct' => 20])->assertStatus(422);
        $this->patchJson('/api/v2/business/hr/settings', ['overtime_day_pct' => 50])->assertOk();

        Sanctum::actingAs($this->ahmed);
        $this->getJson('/api/v2/business/hr/employees')->assertStatus(403);
    }

    public function test_insurance_is_held_inside_the_law_s_limits_and_the_brackets_add_up(): void
    {
        $calc = app(PayrollCalculator::class);
        $brackets = [[40000, 0], [55000, 10], [70000, 15], [200000, 20], [400000, 22.5], [1200000, 25], [null, 27.5]];

        $this->assertEquals(0, $calc->bracketTax(40000, $brackets));
        $this->assertEquals(8727, $calc->bracketTax(94885, $brackets));
        $this->assertEquals(1500 + 2250 + 26000 + 45000 + 50000, $calc->bracketTax(600000, $brackets));

        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v2/business/hr/employees/{$this->ahmed->id}", ['hire_date' => '2026-09-30', 'basic_salary' => 30000])->assertOk();
        $slip = collect($this->postJson('/api/v2/business/hr/payroll/' . self::MONTH . '/generate')->assertOk()->json('data.payslips'))->firstWhere('user_id', $this->ahmed->id);

        // 11% of the 2026 maximum, 16,700 — not of 30,000
        $this->assertEquals(1837, collect($slip['lines'])->firstWhere('key', 'insurance')['amount']);
    }
}
