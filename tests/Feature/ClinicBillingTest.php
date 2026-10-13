<?php

namespace Tests\Feature;

use App\Models\BusinessServicePrice;
use App\Models\ClinicAppointment;
use App\Models\ClinicAppointmentSlot;
use App\Models\SpecialtyService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «عند الوصول رسم الزيارة وبعد الانتهاء تظهر تكلفة الخدمة» — المالك، 2026-10-15: the visit fee is the base and is paid on
 * arrival; a named service is charged once the visit is done, at a price the clinic may raise or lower; what is paid is
 * recorded as payments (instalments), and both the patient and the doctor read them.
 *
 * Rolls back.
 */
class ClinicBillingTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type, ?int $child = null, string $tag = 'U'): User
    {
        $u = new User();
        $u->forceFill([
            'name' => $tag . ' ' . Str::random(4), 'type' => $type, 'email' => strtolower($tag) . '-' . uniqid() . '@example.test',
            'phone' => '010' . random_int(10000000, 99999999), 'password' => 'secret-password', 'api_token' => Str::random(60),
            'category_id' => $child ? 20 : null, 'category_child_id' => $child,
        ])->save();

        return $u;
    }

    private function dentist(): User
    {
        $u = $this->user(User::TYPE_BUSINESS, 514, 'Dentist');
        $option = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'تخصصات طبية')->where('o.name_ar', 'أسنان')->value('o.id');
        DB::table('option_user')->insert(['user_id' => $u->id, 'option_id' => $option]);

        return $u;
    }

    /** A visit booked at a 300 fee, naming a 400 cleaning and a 2500 whitening. @return array{0:User,1:User,2:int} */
    private function booked(): array
    {
        $clinic = $this->dentist();
        $price = BusinessServicePrice::create([
            'business_id' => $clinic->id, 'service_id' => (int) DB::table('platform_services')->where('key', 'booking')->value('id'),
            'child_id' => 514, 'bookable_item_type' => 'booking_examination', 'line_option_id' => 0, 'price' => 300,
            'currency' => 'EGP', 'is_active' => 1, 'duration_minutes' => 30,
        ]);

        $ids = [];
        Sanctum::actingAs($clinic);
        foreach ([['تنظيف الأسنان وإزالة الجير', 400], ['تبييض الأسنان بالعيادة', 2500]] as [$name, $p]) {
            $id = (int) SpecialtyService::query()->whereNull('owner_id')->where('name_ar', $name)->value('id');
            $this->putJson('/api/v2/business/specialty-services', ['items' => [['service_id' => $id, 'offered' => true, 'price' => $p]]])->assertOk();
            $ids[] = $id;
        }

        $slot = ClinicAppointmentSlot::create([
            'clinic_id' => $clinic->id, 'starts_at' => now()->addDays(2)->setHour(11)->setMinute(0)->setSecond(0),
            'duration_minutes' => 30, 'service_price_id' => $price->id,
        ]);

        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');
        Sanctum::actingAs($patient);
        $id = (int) $this->postJson("/api/v2/clinic-slots/{$slot->id}/book", ['specialty_service_ids' => $ids])->assertCreated()->json('data.appointment.id');

        return [$clinic, $patient, $id];
    }

    private function billingAs(User $who, int $id): array
    {
        Sanctum::actingAs($who);
        $path = $who->type === User::TYPE_BUSINESS ? '/api/v2/business/clinic-appointments' : '/api/v2/clinic-appointments';
        $rows = collect($this->getJson($path)->assertOk()->json('data.data'));

        return $rows->firstWhere('id', $id)['billing'];
    }

    public function test_the_visit_fee_is_kept_at_booking_and_the_services_are_not_charged_yet(): void
    {
        [$clinic, $patient, $id] = $this->booked();

        $this->assertEquals(300, ClinicAppointment::query()->find($id)->visit_fee);

        foreach ([$patient, $clinic] as $who) {
            $b = $this->billingAs($who, $id);

            $this->assertEquals(300, $b['visit_fee']);
            $this->assertEquals(300, $b['total'], 'on arrival the account is the visit fee alone');
            $this->assertEquals(300, $b['remaining']);
            $this->assertTrue($b['services_pending']);
            $this->assertNull($b['services'][0]['amount'], 'the service cost is not shown before the visit is done');
            $this->assertEquals(400, $b['services'][0]['price'], 'the listed price the patient picked it by');
        }
    }

    public function test_the_clinic_records_the_fee_on_arrival_and_both_sides_read_what_was_paid(): void
    {
        [$clinic, $patient, $id] = $this->booked();

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$id}/payments", ['amount' => 300, 'kind' => 'visit_fee'])
            ->assertCreated()->assertJsonPath('data.billing.paid', 300)->assertJsonPath('data.billing.remaining', 0);

        foreach ([$patient, $clinic] as $who) {
            $b = $this->billingAs($who, $id);
            $this->assertEquals(300, $b['paid']);
            $this->assertSame('visit_fee', $b['payments'][0]['kind']);
        }
    }

    public function test_after_the_visit_the_services_are_charged_and_the_clinic_may_raise_or_lower_them(): void
    {
        [$clinic, $patient, $id] = $this->booked();

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$id}/complete")->assertOk();

        $b = $this->billingAs($patient, $id);
        $this->assertEquals(300 + 400 + 2500, $b['total'], 'the listed prices count once it is done');
        $this->assertFalse($b['services_pending']);

        // the cleaning took more than listed (400 → 500); the whitening is given a little less (2500 → 2300)
        Sanctum::actingAs($clinic);
        $items = collect($b['services'])->pluck('item_id', 'name');
        $cleaning = collect($b['services'])->firstWhere('price', 400)['item_id'];
        $whitening = collect($b['services'])->firstWhere('price', 2500)['item_id'];

        $this->putJson("/api/v2/business/clinic-appointments/{$id}/items/{$cleaning}", ['final_price' => 500])->assertOk();
        $this->putJson("/api/v2/business/clinic-appointments/{$id}/items/{$whitening}", ['final_price' => 2300])->assertOk();

        $b = $this->billingAs($patient, $id);
        $this->assertEquals(300 + 500 + 2300, $b['total']);

        // back to the listed price
        Sanctum::actingAs($clinic);
        $this->putJson("/api/v2/business/clinic-appointments/{$id}/items/{$cleaning}", ['final_price' => null])->assertOk();
        $this->assertEquals(300 + 400 + 2300, $this->billingAs($patient, $id)['total']);
    }

    public function test_the_treatment_may_be_paid_in_instalments_and_a_mistake_is_taken_back(): void
    {
        [$clinic, $patient, $id] = $this->booked();

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$id}/complete")->assertOk();

        $this->postJson("/api/v2/business/clinic-appointments/{$id}/payments", ['amount' => 300, 'kind' => 'visit_fee'])->assertCreated();
        $this->postJson("/api/v2/business/clinic-appointments/{$id}/payments", ['amount' => 1000, 'kind' => 'service', 'note' => 'الدفعة الأولى'])->assertCreated();
        $wrong = $this->postJson("/api/v2/business/clinic-appointments/{$id}/payments", ['amount' => 9999, 'kind' => 'service'])
            ->assertCreated()->json('data.billing.payments.2.id');

        $this->deleteJson("/api/v2/business/clinic-appointments/{$id}/payments/{$wrong}")->assertOk();

        $b = $this->billingAs($patient, $id);
        $this->assertEquals(1300, $b['paid']);
        $this->assertEquals(3200 - 1300, $b['remaining']);
        $this->assertCount(2, $b['payments']);
        $this->assertSame('الدفعة الأولى', $b['payments'][1]['note']);
    }

    public function test_only_the_clinic_of_the_visit_takes_payments_and_only_for_a_live_visit(): void
    {
        [$clinic, $patient, $id] = $this->booked();
        $other = $this->dentist();

        Sanctum::actingAs($other);
        $this->postJson("/api/v2/business/clinic-appointments/{$id}/payments", ['amount' => 100, 'kind' => 'service'])->assertNotFound();

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/business/clinic-appointments/{$id}/payments", ['amount' => 100, 'kind' => 'service'])->assertForbidden();

        Sanctum::actingAs($clinic);
        $this->postJson("/api/v2/business/clinic-appointments/{$id}/payments", ['amount' => 0, 'kind' => 'service'])->assertStatus(422);
        $this->postJson("/api/v2/business/clinic-appointments/{$id}/payments", ['amount' => 50, 'kind' => 'gift'])->assertStatus(422);

        ClinicAppointment::query()->whereKey($id)->update(['status' => ClinicAppointment::STATUS_CANCELLED]);
        $this->postJson("/api/v2/business/clinic-appointments/{$id}/payments", ['amount' => 100, 'kind' => 'service'])->assertStatus(422);
    }

    public function test_a_slots_visit_kind_reads_as_its_name_not_its_key(): void
    {
        [$clinic] = $this->booked();

        ClinicAppointmentSlot::create([
            'clinic_id' => $clinic->id, 'starts_at' => now()->addDays(3)->setHour(12)->setMinute(0)->setSecond(0), 'duration_minutes' => 30,
            'service_price_id' => (int) BusinessServicePrice::query()->where('business_id', $clinic->id)->value('id'),
        ]);

        Sanctum::actingAs($this->user(User::TYPE_CLIENT, null, 'Patient'));
        $rows = $this->getJson("/api/v2/clinics/{$clinic->id}/slots")->assertOk()->json('data.data');

        $this->assertNotEmpty($rows);
        $this->assertStringNotContainsString('booking_', (string) $rows[0]['visit_kind']);
    }
}
