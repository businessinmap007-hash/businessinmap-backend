<?php

namespace Tests\Feature;

use App\Models\BusinessDepositPolicy;
use App\Models\BusinessServicePrice;
use App\Models\ClinicAppointment;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\GuaranteeLevel;
use App\Models\UserGuarantee;
use App\Models\HospitalProcedure;
use App\Models\InvestigationOrder;
use App\Models\MedicalProcedure;
use App\Models\PlatformService;
use App\Models\ProcedureRequest;
use App\Models\User;
use App\Models\VisitDeposit;
use App\Models\Wallet;
use App\Services\DisputeService;
use App\Services\Hospitals\HospitalProcedureService;
use App\Services\Investigations\InvestigationOrderService;
use App\Services\VisitDepositService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «هل قسم الصحة بالكامل له خاصية طلب ديبوزت لجدية الحجز … خيار متاح لجميع الأنشطة يفعّله أي بزنس» — المالك, 2026-10-20.
 *
 * The same terms a business sets in «شروط الحجز» now stand behind a clinic appointment, a lab/radiology order and a
 * hospital procedure. Never confiscated; stops no account; care is never held for it. Rolls back.
 */
class VisitDepositTest extends TestCase
{
    use DatabaseTransactions;

    private const HEALTH = 20;
    private const HOSPITAL = 513;
    private const CLINIC = 514;
    private const LAB = 163;

    private function user(string $type, ?int $child = null, string $tag = 'U'): User
    {
        $u = new User();
        $u->forceFill([
            'name' => $tag . ' ' . Str::random(4), 'type' => $type, 'email' => strtolower($tag) . '-' . uniqid() . '@example.test',
            'phone' => '010' . random_int(10000000, 99999999), 'password' => 'secret-password', 'api_token' => Str::random(60),
            'category_id' => $child ? self::HEALTH : null, 'category_child_id' => $child,
        ])->save();

        foreach ([true] as $_) {
            DB::table('user_service_fee_consents')->updateOrInsert(
                ['user_id' => $u->id],
                ['fee_auto_charge_enabled' => 0, 'rating_enabled' => 0, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        return $u;
    }

    private function fund(User $user, float $balance): void
    {
        app(WalletService::class)->getOrCreateWallet((int) $user->id)
            ->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => $balance, 'locked_balance' => 0]);
    }

    private function guarantee(User $user, string $target, float $coverage): UserGuarantee
    {
        $levelId = (int) DB::table('guarantee_levels')->value('id');

        if ($levelId <= 0) {
            $this->markTestSkipped('Needs a guarantee level.');
        }

        return UserGuarantee::create([
            'user_id' => $user->id,
            'target_type' => $target,
            'purchased_level_id' => $levelId,
            'effective_level_id' => $levelId,
            'status' => UserGuarantee::STATUS_ACTIVE,
            'current_coverage_amount' => $coverage,
            'used_coverage_amount' => 0,
        ]);
    }

    private function wallet(User $user): Wallet
    {
        return Wallet::query()->where('user_id', $user->id)->firstOrFail();
    }

    /** The business's terms: a share of the visit, wallet-held (with a business counter hold) or outside the platform. */
    private function terms(User $business, string $mode = 'wallet', float $percent = 20, float $counter = 50): void
    {
        $external = $mode === 'external';

        DB::table('business_deposit_policies')->where('business_id', $business->id)->delete();
        DB::table('business_deposit_policies')->insert([
            'business_id' => $business->id,
            'platform_service_id' => (int) PlatformService::query()->where('key', 'booking')->value('id'),
            'category_child_id' => (int) $business->category_child_id,
            'scope_key' => BusinessDepositPolicy::SCOPE_BUSINESS_GLOBAL,
            'priority' => 0,
            'is_enabled' => 1,
            'deposit_mode' => $external ? BusinessDepositPolicy::MODE_EXTERNAL_VERIFICATION : BusinessDepositPolicy::MODE_WALLET_HOLD,
            'calculation_base' => BusinessDepositPolicy::BASE_TOTAL,
            'deposit_type' => BusinessDepositPolicy::TYPE_PERCENT,
            'deposit_value' => $percent,
            'max_deposit_percent' => 50,
            'wallet_hold_enabled' => $external ? 0 : 1,
            'external_verification_enabled' => $external ? 1 : 0,
            'business_counter_hold_enabled' => $external ? 0 : 1,
            'business_counter_hold_percent' => $external ? 0 : $counter,
            'currency' => 'EGP',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function visitPrice(User $clinic, float $price = 100): BusinessServicePrice
    {
        return BusinessServicePrice::create([
            'business_id' => $clinic->id, 'service_id' => (int) PlatformService::query()->where('key', 'booking')->value('id'),
            'child_id' => (int) $clinic->category_child_id, 'bookable_item_type' => BusinessServicePrice::DEFAULT_ITEM_TYPE,
            'price' => $price, 'currency' => 'EGP', 'is_active' => 1,
        ]);
    }

    private function when(): string
    {
        return Carbon::parse('+2 days 10:00')->format('Y-m-d H:i:s');
    }

    /** A requested appointment of a 100-pound visit (the patient funded with $balance). */
    private function requested(User $clinic, User $patient, float $balance = 500): ClinicAppointment
    {
        $this->fund($patient, $balance);
        $price = $this->visitPrice($clinic);

        Sanctum::actingAs($patient);
        $id = $this->postJson('/api/v2/clinic-appointments', [
            'clinic_id' => $clinic->id, 'scheduled_at' => $this->when(), 'service_price_id' => $price->id,
        ])->assertCreated()->json('data.appointment.id');

        return ClinicAppointment::query()->findOrFail($id);
    }

    public function test_a_clinic_that_asks_for_nothing_has_no_deposit_anywhere(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');

        $appointment = $this->requested($clinic, $patient);

        Sanctum::actingAs($patient);
        $this->getJson('/api/v2/clinic-appointments')->assertOk();

        $this->assertNull(app(VisitDepositService::class)->stateFor($appointment));

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/confirm")->assertOk();
        $this->assertNull(app(VisitDepositService::class)->rowFor($appointment));
    }

    public function test_the_deposit_is_quoted_then_frozen_in_both_wallets_when_the_clinic_confirms(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($clinic);
        $this->fund($clinic, 200);

        $appointment = $this->requested($clinic, $patient);

        // before the clinic answers, the patient already reads what it will ask
        $state = app(VisitDepositService::class)->stateFor($appointment);
        $this->assertSame('quoted', $state['status']);
        $this->assertEqualsWithDelta(20.0, $state['amount'], 0.001);
        $this->assertEqualsWithDelta(10.0, $state['counter_amount'], 0.001);
        $this->assertEqualsWithDelta(500.0, (float) $this->wallet($patient)->balance, 0.001, 'nothing frozen yet');

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/confirm")
            ->assertOk()->assertJsonPath('data.appointment.deposit.status', 'held');

        $this->assertEqualsWithDelta(480.0, (float) $this->wallet($patient)->balance, 0.001);
        $this->assertEqualsWithDelta(20.0, (float) $this->wallet($patient)->locked_balance, 0.001);
        $this->assertEqualsWithDelta(190.0, (float) $this->wallet($clinic)->balance, 0.001);
        $this->assertEqualsWithDelta(10.0, (float) $this->wallet($clinic)->locked_balance, 0.001);

        // the visit is done: both holds go back, nothing was kept
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/complete")->assertOk();

        $this->assertSame(VisitDeposit::STATUS_RELEASED, app(VisitDepositService::class)->rowFor($appointment)->status);
        $this->assertEqualsWithDelta(500.0, (float) $this->wallet($patient)->balance, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->wallet($patient)->locked_balance, 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $this->wallet($clinic)->balance, 0.001);
    }

    public function test_a_patient_whose_wallet_cannot_stand_the_deposit_cannot_request(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($clinic);
        $this->fund($patient, 5);
        $price = $this->visitPrice($clinic);

        Sanctum::actingAs($patient);
        $this->postJson('/api/v2/clinic-appointments', [
            'clinic_id' => $clinic->id, 'scheduled_at' => $this->when(), 'service_price_id' => $price->id,
        ])->assertStatus(422)->assertJsonValidationErrors('deposit');

        $this->assertSame(0, ClinicAppointment::query()->where('patient_id', $patient->id)->count());
    }

    public function test_a_clinic_short_of_its_own_share_cannot_confirm_and_the_appointment_stays_requested(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($clinic);
        $this->fund($clinic, 1);

        $appointment = $this->requested($clinic, $patient);

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/confirm")
            ->assertStatus(422)->assertJsonValidationErrors('deposit');

        $this->assertSame(ClinicAppointment::STATUS_REQUESTED, $appointment->fresh()->status);
        $this->assertNull(app(VisitDepositService::class)->rowFor($appointment));
        $this->assertEqualsWithDelta(500.0, (float) $this->wallet($patient)->balance, 0.001);
    }

    public function test_cancelling_or_rejecting_gives_every_hold_back(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($clinic);
        $this->fund($clinic, 200);

        $appointment = $this->requested($clinic, $patient);

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/confirm")->assertOk();
        $this->assertEqualsWithDelta(20.0, (float) $this->wallet($patient)->locked_balance, 0.001);

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/clinic-appointments/{$appointment->id}/cancel")->assertOk();

        $this->assertSame(VisitDeposit::STATUS_REFUNDED, app(VisitDepositService::class)->rowFor($appointment)->status);
        $this->assertEqualsWithDelta(500.0, (float) $this->wallet($patient)->balance, 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $this->wallet($clinic)->balance, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->wallet($clinic)->locked_balance, 0.001);
    }

    public function test_an_external_deposit_is_recorded_by_the_business_and_never_gates_care(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($clinic, 'external');

        $appointment = $this->requested($clinic, $patient, 0);

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/confirm")
            ->assertOk()->assertJsonPath('data.appointment.deposit.status', 'pending')
            ->assertJsonPath('data.appointment.deposit.mode', 'external');

        // no money moved, and nothing stops the patient's account meanwhile
        $this->assertSame(0, Deposit::query()->where('target_type', ClinicAppointment::class)->where('target_id', $appointment->id)->count());

        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/deposit/confirm-received", ['method' => 'transfer', 'reference' => 'T-9'])
            ->assertOk()->assertJsonPath('data.deposit.status', 'received');

        // a second answer on a settled deposit is refused
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/deposit/waive")->assertStatus(422);

        // the visit completes without any gate
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/complete")->assertOk();
    }

    public function test_the_business_may_accept_without_the_deposit_and_a_stranger_cannot_answer(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $other = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Other');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($clinic, 'external');

        $appointment = $this->requested($clinic, $patient, 0);

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/confirm")->assertOk();

        Sanctum::actingAs($other);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/deposit/waive")->assertNotFound();

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/deposit/waive")
            ->assertOk()->assertJsonPath('data.deposit.status', 'waived');
    }

    public function test_a_no_show_keeps_it_held_until_it_is_given_back_or_ruled_on(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($clinic);
        $this->fund($clinic, 200);

        $appointment = $this->requested($clinic, $patient);

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/confirm")->assertOk();
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/no-show")->assertOk();

        $svc = app(VisitDepositService::class);
        $this->assertSame(VisitDeposit::STATUS_HELD, $svc->rowFor($appointment)->status, 'still frozen after a no-show');

        // a stranger cannot open a dispute on it
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, null, 'Stranger'));
        $this->postJson("/api/v2/visit-deposits/clinic-appointment/{$appointment->id}/disputes", ['reason_code' => 'no_show'])->assertNotFound();

        // the clinic opens one; the deposit is now under dispute
        Sanctum::actingAs($clinic);
        $res = $this->postJson("/api/v2/visit-deposits/clinic-appointment/{$appointment->id}/disputes", [
            'reason_code' => 'no_show', 'reason_text' => 'لم يحضر',
        ])->assertOk();
        $disputeId = (int) $res->json('data.id');

        $this->assertSame(VisitDeposit::STATUS_DISPUTED, $svc->rowFor($appointment)->status);
        $this->assertSame(Dispute::class, get_class(Dispute::query()->findOrFail($disputeId)));

        // the arbitrator rules: the escrow unwinds to whoever posted it and the visit's record follows
        app(DisputeService::class)->resolve(Dispute::query()->findOrFail($disputeId), 'refund_client');

        $this->assertSame(VisitDeposit::STATUS_RESOLVED, $svc->rowFor($appointment)->status);
        $this->assertEqualsWithDelta(500.0, (float) $this->wallet($patient)->balance, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->wallet($patient)->locked_balance, 0.001);
    }

    public function test_the_business_may_give_a_held_deposit_back_after_a_no_show(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($clinic);
        $this->fund($clinic, 200);

        $appointment = $this->requested($clinic, $patient);

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/confirm")->assertOk();
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/no-show")->assertOk();
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/deposit/give-back")
            ->assertOk()->assertJsonPath('data.deposit.status', 'refunded');

        $this->assertEqualsWithDelta(500.0, (float) $this->wallet($patient)->balance, 0.001);
    }

    public function test_a_lab_secures_the_deposit_when_it_accepts_and_releases_it_with_the_results(): void
    {
        $lab = $this->user(User::TYPE_BUSINESS, self::LAB, 'Lab');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($lab);
        $this->fund($patient, 300);
        $this->fund($lab, 100);

        $optionId = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'التحاليل الطبية')->where('o.name_ar', 'صورة دم كاملة CBC')->value('o.id');
        BusinessServicePrice::create([
            'business_id' => $lab->id, 'service_id' => (int) PlatformService::query()->where('key', 'booking')->value('id'),
            'child_id' => self::LAB, 'bookable_item_type' => BusinessServicePrice::DEFAULT_ITEM_TYPE,
            'line_option_id' => $optionId, 'price' => 200, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        $svc = app(InvestigationOrderService::class);
        $order = $svc->requestFromCenter($patient, $lab, [$optionId], null, null);

        $this->assertSame('sent', $order->status);
        $this->assertEqualsWithDelta(300.0, (float) $this->wallet($patient)->balance, 0.001, 'nothing frozen at the request');

        $order = $svc->accept($order, null, null);
        $row = app(VisitDepositService::class)->rowFor($order);

        $this->assertSame(VisitDeposit::STATUS_HELD, $row->status);
        $this->assertEqualsWithDelta(40.0, (float) $row->amount, 0.001);
        $this->assertEqualsWithDelta(260.0, (float) $this->wallet($patient)->balance, 0.001);

        $svc->attachResults($order, [], 'تم', [(int) $order->items->first()->id => 'Hb 13.5']);

        $this->assertSame(VisitDeposit::STATUS_RELEASED, $row->fresh()->status);
        $this->assertEqualsWithDelta(300.0, (float) $this->wallet($patient)->balance, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $this->wallet($lab)->balance, 0.001);
    }

    public function test_a_hospital_secures_a_procedures_deposit_on_accepting_and_gives_it_back_when_declined(): void
    {
        $hospital = $this->user(User::TYPE_BUSINESS, self::HOSPITAL, 'Hospital');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($hospital, 'wallet', 10, 100);
        $this->fund($patient, 5000);
        $this->fund($hospital, 5000);

        $procedureId = (int) MedicalProcedure::query()->whereNull('owner_id')->where('name_ar', 'استئصال الزائدة الدودية')->value('id');
        $offer = HospitalProcedure::query()->create(['hospital_id' => $hospital->id, 'procedure_id' => $procedureId, 'price' => 10000]);

        $svc = app(HospitalProcedureService::class);

        // 10% of 10000 = 1000 — beyond this patient's 5000? no: within; the request stands
        $request = $svc->request($patient, (int) $offer->id, null, null);
        $this->assertSame(ProcedureRequest::STATUS_REQUESTED, $request->status);

        $request = $svc->accept($request, Carbon::parse('+5 days 09:00'), null, null);
        $row = app(VisitDepositService::class)->rowFor($request);
        $this->assertSame(VisitDeposit::STATUS_HELD, $row->status);
        $this->assertEqualsWithDelta(1000.0, (float) $row->amount, 0.001);
        $this->assertEqualsWithDelta(1000.0, (float) $row->counter_amount, 0.001);

        // the patient cancels the accepted procedure: both holds go back
        $svc->cancel($request);
        $this->assertSame(VisitDeposit::STATUS_REFUNDED, $row->fresh()->status);
        $this->assertEqualsWithDelta(5000.0, (float) $this->wallet($patient)->balance, 0.001);
        $this->assertEqualsWithDelta(5000.0, (float) $this->wallet($hospital)->balance, 0.001);
    }

    public function test_every_health_business_may_open_the_terms_screen_and_set_them(): void
    {
        foreach ([self::CLINIC, self::HOSPITAL, self::LAB] as $child) {
            $business = $this->user(User::TYPE_BUSINESS, $child, 'Biz');
            $this->guarantee($business, GuaranteeLevel::TARGET_BUSINESS, 500);

            Sanctum::actingAs($business);
            $this->putJson('/api/v2/business/booking-terms', [
                'enabled' => true, 'security_mode' => 'deposit_freeze', 'deposit_percent' => 20,
            ])->assertOk()->assertJsonPath('data.enabled', true);

            $this->getJson('/api/v2/business/booking-terms')->assertOk()->assertJsonPath('data.deposit_percent', 20);
        }
    }

    public function test_an_account_owing_nothing_is_never_stopped_by_a_held_deposit(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($clinic);
        $this->fund($clinic, 200);

        $appointment = $this->requested($clinic, $patient);

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/confirm")->assertOk();
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/no-show")->assertOk();

        // the deposit is held, no dispute is open: the patient's account still books elsewhere
        Sanctum::actingAs($patient);
        $other = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Other');
        $this->postJson('/api/v2/clinic-appointments', ['clinic_id' => $other->id, 'scheduled_at' => $this->when()])
            ->assertCreated();
    }

    public function test_each_side_stands_on_its_guarantee_first_and_the_wallet_holds_only_the_rest(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($clinic);
        $this->fund($clinic, 200);
        $patientGuarantee = $this->guarantee($patient, GuaranteeLevel::TARGET_CLIENT, 8);
        $clinicGuarantee = $this->guarantee($clinic, GuaranteeLevel::TARGET_BUSINESS, 10);

        $appointment = $this->requested($clinic, $patient);

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/confirm")
            ->assertOk()->assertJsonPath('data.appointment.deposit.status', 'held')
            ->assertJsonPath('data.appointment.deposit.guarantee_amount', 8)
            ->assertJsonPath('data.appointment.deposit.business_guarantee_amount', 10);

        // the deposit is 20 from the patient: 8 from his guarantee, 12 from his wallet; the clinic's 10 is all guarantee
        $this->assertEqualsWithDelta(488.0, (float) $this->wallet($patient)->balance, 0.001);
        $this->assertEqualsWithDelta(12.0, (float) $this->wallet($patient)->locked_balance, 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $this->wallet($clinic)->balance, 0.001, 'the clinic\'s wallet is untouched');
        $this->assertEqualsWithDelta(8.0, (float) $patientGuarantee->fresh()->used_coverage_amount, 0.001);
        $this->assertEqualsWithDelta(10.0, (float) $clinicGuarantee->fresh()->used_coverage_amount, 0.001);

        // the visit is done: wallets and coverage all go back
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/complete")->assertOk();

        $this->assertEqualsWithDelta(500.0, (float) $this->wallet($patient)->balance, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->wallet($patient)->locked_balance, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $patientGuarantee->fresh()->used_coverage_amount, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $clinicGuarantee->fresh()->used_coverage_amount, 0.001);
    }

    public function test_a_deposit_that_stands_wholly_on_guarantees_moves_no_wallet_money(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($clinic);
        $this->fund($clinic, 0);
        $patientGuarantee = $this->guarantee($patient, GuaranteeLevel::TARGET_CLIENT, 100);
        $clinicGuarantee = $this->guarantee($clinic, GuaranteeLevel::TARGET_BUSINESS, 100);

        // a patient with an empty wallet may still book: his guarantee stands for the deposit
        $appointment = $this->requested($clinic, $patient, 0);

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/confirm")->assertOk();

        $row = app(VisitDepositService::class)->rowFor($appointment);
        $this->assertSame(VisitDeposit::STATUS_HELD, $row->status);
        $this->assertNull($row->deposit_id, 'no escrow row: nothing was held from a wallet');
        $this->assertEqualsWithDelta(20.0, (float) $patientGuarantee->fresh()->used_coverage_amount, 0.001);
        $this->assertEqualsWithDelta(10.0, (float) $clinicGuarantee->fresh()->used_coverage_amount, 0.001);

        // the patient cancels: the coverage is returned
        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/clinic-appointments/{$appointment->id}/cancel")->assertOk();

        $this->assertSame(VisitDeposit::STATUS_REFUNDED, $row->fresh()->status);
        $this->assertEqualsWithDelta(0.0, (float) $patientGuarantee->fresh()->used_coverage_amount, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $clinicGuarantee->fresh()->used_coverage_amount, 0.001);
    }

    public function test_a_ruling_returns_the_coverage_frozen_for_the_visit(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($clinic);
        $this->fund($clinic, 200);
        $patientGuarantee = $this->guarantee($patient, GuaranteeLevel::TARGET_CLIENT, 20);
        $this->guarantee($clinic, GuaranteeLevel::TARGET_BUSINESS, 10);

        $appointment = $this->requested($clinic, $patient);

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/confirm")->assertOk();
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/no-show")->assertOk();
        $disputeId = (int) $this->postJson("/api/v2/visit-deposits/clinic-appointment/{$appointment->id}/disputes", ['reason_code' => 'no_show'])
            ->assertOk()->json('data.id');

        $this->assertEqualsWithDelta(20.0, (float) $patientGuarantee->fresh()->used_coverage_amount, 0.001);

        app(DisputeService::class)->resolve(Dispute::query()->findOrFail($disputeId), 'no_action');

        $this->assertSame(VisitDeposit::STATUS_RESOLVED, app(VisitDepositService::class)->rowFor($appointment)->status);
        $this->assertEqualsWithDelta(0.0, (float) $patientGuarantee->fresh()->used_coverage_amount, 0.001, 'coverage is never charged');
    }

    public function test_a_patient_short_of_both_guarantee_and_wallet_is_told_how_to_close_the_gap(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($clinic);
        $this->fund($patient, 5);
        $this->guarantee($patient, GuaranteeLevel::TARGET_CLIENT, 8);
        $price = $this->visitPrice($clinic);

        Sanctum::actingAs($patient);
        $this->postJson('/api/v2/clinic-appointments', [
            'clinic_id' => $clinic->id, 'scheduled_at' => $this->when(), 'service_price_id' => $price->id,
        ])->assertStatus(422)
            ->assertJsonPath('options.add_balance.amount', 7)
            ->assertJsonPath('options.upgrade_guarantee', true);
    }

    public function test_a_business_that_requires_the_guarantee_freezes_it_for_the_visit(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($clinic);
        DB::table('business_deposit_policies')->where('business_id', $clinic->id)->update([
            'client_guarantee_strategy' => BusinessDepositPolicy::GUARANTEE_GENERAL,
        ]);
        $this->fund($clinic, 200);
        $patientGuarantee = $this->guarantee($patient, GuaranteeLevel::TARGET_CLIENT, 500);
        $this->guarantee($clinic, GuaranteeLevel::TARGET_BUSINESS, 100);

        $appointment = $this->requested($clinic, $patient);

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/confirm")
            ->assertOk()->assertJsonPath('data.appointment.deposit.status', 'held')
            ->assertJsonPath('data.appointment.deposit.guarantee_amount', 20);

        $this->assertEqualsWithDelta(20.0, (float) $patientGuarantee->fresh()->used_coverage_amount, 0.001);
        $this->assertEqualsWithDelta(500.0, (float) $this->wallet($patient)->balance, 0.001, 'his wallet is untouched');

        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/complete")->assertOk();
        $this->assertEqualsWithDelta(0.0, (float) $patientGuarantee->fresh()->used_coverage_amount, 0.001);
    }

    // ── a friend stands for the deposit ───────────────────────────────────────────────────────────────────────

    public function test_a_friend_may_stand_for_the_deposit_and_is_released_with_the_visit(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $friend = $this->user(User::TYPE_CLIENT, null, 'Friend');
        $this->terms($clinic);
        $this->fund($clinic, 200);
        $this->fund($patient, 0);
        $friendGuarantee = $this->guarantee($friend, GuaranteeLevel::TARGET_CLIENT, 100);
        $price = $this->visitPrice($clinic);

        // short of the deposit: refused…
        Sanctum::actingAs($patient);
        $payload = ['clinic_id' => $clinic->id, 'scheduled_at' => $this->when(), 'service_price_id' => $price->id];
        $this->postJson('/api/v2/clinic-appointments', $payload)->assertStatus(422);

        // …unless a friend is to stand for him
        $id = $this->postJson('/api/v2/clinic-appointments', $payload + ['friend_guarantee' => true])
            ->assertCreated()->json('data.appointment.id');

        // he invites the friend by phone and is told what is still missing
        $this->postJson("/api/v2/visit-deposits/clinic-appointment/{$id}/guarantors", ['phone' => $friend->phone])
            ->assertCreated()->assertJsonPath('data.guarantor.status', 'invited');
        $guarantorId = (int) \App\Models\OperationGuarantor::query()->where('guarantor_user_id', $friend->id)->value('id');

        $this->getJson("/api/v2/visit-deposits/clinic-appointment/{$id}/guarantors")->assertOk()
            ->assertJsonPath('data.need.deposit', 20);

        // the friend reads the request and freezes 20
        Sanctum::actingAs($friend);
        $this->getJson("/api/v2/guarantors/{$guarantorId}")->assertOk()->assertJsonPath('data.guarantor.available_coverage', 100);
        $this->postJson("/api/v2/guarantors/{$guarantorId}/accept", ['amount' => 20])->assertOk();
        $this->assertEqualsWithDelta(20.0, (float) $friendGuarantee->fresh()->used_coverage_amount, 0.001);

        // the clinic confirms: the deposit stands on the friend's coverage, the patient's wallet untouched
        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$id}/confirm")->assertOk()
            ->assertJsonPath('data.appointment.deposit.status', 'held');
        $this->assertEqualsWithDelta(0.0, (float) $this->wallet($patient)->locked_balance, 0.001);

        // the patient cancels: the friend's coverage is free again
        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/clinic-appointments/{$id}/cancel")->assertOk();
        $this->assertEqualsWithDelta(0.0, (float) $friendGuarantee->fresh()->used_coverage_amount, 0.001);
    }

    public function test_a_request_cancelled_before_confirmation_returns_what_the_friend_froze(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $friend = $this->user(User::TYPE_CLIENT, null, 'Friend');
        $this->terms($clinic);
        $this->fund($patient, 0);
        $friendGuarantee = $this->guarantee($friend, GuaranteeLevel::TARGET_CLIENT, 100);
        $price = $this->visitPrice($clinic);

        Sanctum::actingAs($patient);
        $id = $this->postJson('/api/v2/clinic-appointments', [
            'clinic_id' => $clinic->id, 'scheduled_at' => $this->when(), 'service_price_id' => $price->id, 'friend_guarantee' => true,
        ])->assertCreated()->json('data.appointment.id');
        $this->postJson("/api/v2/visit-deposits/clinic-appointment/{$id}/guarantors", ['guarantor_user_id' => $friend->id])->assertCreated();
        $guarantorId = (int) \App\Models\OperationGuarantor::query()->where('guarantor_user_id', $friend->id)->value('id');

        Sanctum::actingAs($friend);
        $this->postJson("/api/v2/guarantors/{$guarantorId}/accept", ['amount' => 20])->assertOk();
        $this->assertEqualsWithDelta(20.0, (float) $friendGuarantee->fresh()->used_coverage_amount, 0.001);

        // the clinic rejects before ever confirming: no deposit row exists, yet the friend is freed
        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$id}/reject")->assertOk();
        $this->assertEqualsWithDelta(0.0, (float) $friendGuarantee->fresh()->used_coverage_amount, 0.001);
    }

    public function test_only_the_customer_invites_and_a_visit_with_no_deposit_takes_no_friends(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $stranger = $this->user(User::TYPE_CLIENT, null, 'Stranger');
        $friend = $this->user(User::TYPE_CLIENT, null, 'Friend');
        $this->guarantee($friend, GuaranteeLevel::TARGET_CLIENT, 100);

        // no terms → no deposit
        $appointment = $this->requested($clinic, $patient);
        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/visit-deposits/clinic-appointment/{$appointment->id}/guarantors", ['guarantor_user_id' => $friend->id])
            ->assertStatus(422);

        $this->terms($clinic);
        Sanctum::actingAs($stranger);
        $this->postJson("/api/v2/visit-deposits/clinic-appointment/{$appointment->id}/guarantors", ['guarantor_user_id' => $friend->id])
            ->assertNotFound();
    }

    // ---- «اكتمل العدد اليوم»: the business's own full day returns what it still holds for no-shows -------------------

    public function test_closing_the_day_returns_the_deposit_held_for_a_no_show(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($clinic);
        $this->fund($clinic, 200);

        $appointment = $this->requested($clinic, $patient);

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/confirm")->assertOk();
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/no-show")->assertOk();

        $svc = app(VisitDepositService::class);
        $this->assertSame(VisitDeposit::STATUS_HELD, $svc->rowFor($appointment)->status, 'held until the day is full');

        // the day is full by the clinic's own decision: it has no chance to keep what it holds
        $this->putJson('/api/v2/business/booking-day-closure', ['closed' => true, 'date' => $appointment->scheduled_at->toDateString()])->assertOk();

        $this->assertSame(VisitDeposit::STATUS_REFUNDED, $svc->rowFor($appointment)->status);
        $this->assertEqualsWithDelta(500.0, (float) $this->wallet($patient)->balance, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->wallet($patient)->locked_balance, 0.001);
    }

    public function test_a_no_show_marked_on_a_day_the_clinic_already_closed_is_returned_at_once(): void
    {
        $clinic = $this->user(User::TYPE_BUSINESS, self::CLINIC, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        $this->terms($clinic);
        $this->fund($clinic, 200);

        $appointment = $this->requested($clinic, $patient);

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/confirm")->assertOk();
        $this->putJson('/api/v2/business/booking-day-closure', ['closed' => true, 'date' => $appointment->scheduled_at->toDateString()])->assertOk();

        $this->postJson("/api/v2/business/clinic-appointments/{$appointment->id}/no-show")->assertOk();

        $this->assertSame(VisitDeposit::STATUS_REFUNDED, app(VisitDepositService::class)->rowFor($appointment)->status);
        $this->assertEqualsWithDelta(500.0, (float) $this->wallet($patient)->balance, 0.001);
    }
}
