<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The prescription journey: a doctor issues a روشتة for a patient, the patient
 * sends it to a pharmacy, and the pharmacy prepares → readies → dispenses it —
 * with the right party notified at each hand-off, and no one else able to read
 * or drive it.
 */
class PrescriptionFlowTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type, string $tag): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '0105' . random_int(1000000, 9999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    private function medicine(string $name): Medicine
    {
        return Medicine::create(['name' => $name . ' ' . Str::random(4)]);
    }

    private function issue(User $doctor, User $patient): int
    {
        Sanctum::actingAs($doctor);

        $paracetamol = $this->medicine('Paracetamol');
        $vitaminC = $this->medicine('Vitamin C');

        return $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'diagnosis' => 'Seasonal flu',
            'items' => [
                ['medicine_id' => $paracetamol->id, 'dosage' => '500mg', 'quantity' => '2 boxes', 'instructions' => 'After meals, 3x daily'],
                ['medicine_id' => $vitaminC->id, 'dosage' => '1000mg', 'quantity' => '1 box'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.prescription.status', 'issued')
            ->json('data.prescription.id');
    }

    public function test_full_flow_from_issue_to_dispense_with_notifications(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy');

        $id = $this->issue($doctor, $patient);

        // The patient was notified of the new prescription.
        $this->assertTrue(
            AppNotification::query()
                ->where('user_id', $patient->id)
                ->where('notifiable_type', Prescription::class)
                ->where('notifiable_id', $id)
                ->exists()
        );

        // The patient sees it and sends it to the pharmacy for delivery.
        Sanctum::actingAs($patient);
        $this->getJson('/api/v2/prescriptions')->assertOk()->assertJsonPath('data.data.0.id', $id);

        $this->postJson("/api/v2/prescriptions/{$id}/send", [
            'pharmacy_id' => $pharmacy->id,
            'fulfillment_type' => 'delivery',
            'delivery_address' => '12 Nile St, Cairo',
        ])->assertOk()->assertJsonPath('data.prescription.status', 'sent_to_pharmacy');

        // The pharmacy was notified and sees it in its queue.
        $this->assertTrue(
            AppNotification::query()->where('user_id', $pharmacy->id)
                ->where('notifiable_id', $id)->where('notifiable_type', Prescription::class)->exists()
        );

        Sanctum::actingAs($pharmacy);
        $this->getJson('/api/v2/pharmacy/prescriptions')->assertOk()->assertJsonPath('data.data.0.id', $id);

        $this->postJson("/api/v2/pharmacy/prescriptions/{$id}/prepare")->assertOk()
            ->assertJsonPath('data.prescription.status', 'preparing');
        $this->postJson("/api/v2/pharmacy/prescriptions/{$id}/ready")->assertOk()
            ->assertJsonPath('data.prescription.status', 'ready');
        $this->postJson("/api/v2/pharmacy/prescriptions/{$id}/dispense")->assertOk()
            ->assertJsonPath('data.prescription.status', 'dispensed');

        // Ready-notice reached the patient.
        $this->assertTrue(
            AppNotification::query()->where('user_id', $patient->id)
                ->where('title_ar', 'دواؤك جاهز')->exists()
        );
    }

    public function test_only_the_patient_may_send_and_only_a_party_may_read(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy');
        $stranger = $this->user(User::TYPE_CLIENT, 'Stranger');

        $id = $this->issue($doctor, $patient);

        // A stranger can neither read nor send it.
        Sanctum::actingAs($stranger);
        $this->getJson("/api/v2/prescriptions/{$id}")->assertNotFound();
        $this->postJson("/api/v2/prescriptions/{$id}/send", [
            'pharmacy_id' => $pharmacy->id,
            'fulfillment_type' => 'pickup',
        ])->assertNotFound();

        // The doctor (a party) can read it.
        Sanctum::actingAs($doctor);
        $this->getJson("/api/v2/prescriptions/{$id}")->assertOk()->assertJsonPath('data.prescription.id', $id);
    }

    public function test_a_wrong_pharmacy_cannot_act_on_it(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy');
        $other = $this->user(User::TYPE_BUSINESS, 'OtherPharmacy');

        $id = $this->issue($doctor, $patient);
        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/prescriptions/{$id}/send", [
            'pharmacy_id' => $pharmacy->id,
            'fulfillment_type' => 'pickup',
        ])->assertOk();

        // A different pharmacy the script was not sent to cannot touch it.
        Sanctum::actingAs($other);
        $this->postJson("/api/v2/pharmacy/prescriptions/{$id}/prepare")->assertNotFound();
    }

    /** «يجب ان يختار من الاصناف حتى تكون نسبة الخطأ صفر» — المالك، 2026-08-27. */
    public function test_a_prescription_line_must_name_a_real_dictionary_drug(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');

        Sanctum::actingAs($doctor);

        // Free-typed text is no longer an accepted line at all.
        $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'items' => [['name' => 'Some Drug I Typed']],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.medicine_id');

        // Neither is a medicine_id that does not exist in the dictionary.
        $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'items' => [['medicine_id' => 999999999]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.medicine_id');
    }

    /** The name printed on the prescription comes from the dictionary row, never client text. */
    public function test_the_line_name_is_the_dictionary_records_own_name(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $medicine = $this->medicine('AUGMENTIN 1GM');

        Sanctum::actingAs($doctor);
        $res = $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'items' => [['medicine_id' => $medicine->id, 'dosage' => '1g']],
        ])->assertCreated();

        $this->assertSame($medicine->id, $res->json('data.prescription.items.0.medicine_id'));
        $this->assertSame($medicine->name, $res->json('data.prescription.items.0.name'));

        $this->assertDatabaseHas('prescription_items', [
            'medicine_id' => $medicine->id,
            'name' => $medicine->name,
        ]);
    }

    /** «حالة المريض» و«مدة العلاج كام يوم او اسبوع او شهر» — المالك، 2026-08-27. */
    public function test_patient_condition_and_a_week_long_duration_are_recorded(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $medicine = $this->medicine('Amoxicillin');

        Sanctum::actingAs($doctor);
        $res = $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'diagnosis' => 'Sinusitis',
            'patient_condition' => 'حرارة مرتفعة وإجهاد عام',
            'items' => [[
                'medicine_id' => $medicine->id,
                'duration_value' => 2,
                'duration_unit' => 'weeks',
            ]],
        ])->assertCreated();

        $this->assertSame('حرارة مرتفعة وإجهاد عام', $res->json('data.prescription.patient_condition'));
        $this->assertSame(2, $res->json('data.prescription.items.0.duration_value'));
        $this->assertSame('weeks', $res->json('data.prescription.items.0.duration_unit'));
        // The scheduler still only ever reads a plain day count.
        $this->assertSame(14, $res->json('data.prescription.items.0.duration_days'));
    }

    public function test_duration_value_and_unit_must_be_given_together(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $medicine = $this->medicine('Amoxicillin');

        Sanctum::actingAs($doctor);
        $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'items' => [['medicine_id' => $medicine->id, 'duration_value' => 2]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.duration_unit');
    }

    /** «مشاركة الروشتة مع طبيب اخر» — «الاثنين معا» يقرران: المريض أو الطبيب الأصلي. */
    public function test_the_patient_can_share_with_a_second_doctor_who_can_then_read_it(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $secondDoctor = $this->user(User::TYPE_BUSINESS, 'SecondOpinion');
        $stranger = $this->user(User::TYPE_BUSINESS, 'Stranger');

        $id = $this->issue($doctor, $patient);

        Sanctum::actingAs($stranger);
        $this->getJson("/api/v2/prescriptions/{$id}")->assertNotFound();

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/prescriptions/{$id}/share", ['doctor_id' => $secondDoctor->id])
            ->assertOk()
            ->assertJsonPath('data.prescription.shared_with.0.id', $secondDoctor->id);

        Sanctum::actingAs($secondDoctor);
        $this->getJson("/api/v2/prescriptions/{$id}")->assertOk()->assertJsonPath('data.prescription.id', $id);
    }

    public function test_the_original_doctor_can_also_share_it(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $secondDoctor = $this->user(User::TYPE_BUSINESS, 'SecondOpinion');

        $id = $this->issue($doctor, $patient);

        Sanctum::actingAs($doctor);
        $this->postJson("/api/v2/prescriptions/{$id}/share", ['doctor_id' => $secondDoctor->id])->assertOk();

        Sanctum::actingAs($secondDoctor);
        $this->getJson("/api/v2/prescriptions/{$id}")->assertOk();
    }

    public function test_a_shared_in_doctor_cannot_revise_it(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $secondDoctor = $this->user(User::TYPE_BUSINESS, 'SecondOpinion');

        $id = $this->issue($doctor, $patient);

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/prescriptions/{$id}/share", ['doctor_id' => $secondDoctor->id])->assertOk();

        Sanctum::actingAs($secondDoctor);
        $this->postJson("/api/v2/prescriptions/{$id}/revise", [
            'items' => [['medicine_id' => $this->medicine('Something')->id]],
        ])->assertNotFound();
    }

    /** «تعدل من الطبيب الاصلى وتحفظ نسخة جديدة وتختم القديمة ملغاة ولا تحذف». */
    public function test_only_the_original_doctor_can_revise_and_it_versions_not_overwrites(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $newMedicine = $this->medicine('Stronger Dose');

        $id = $this->issue($doctor, $patient);

        Sanctum::actingAs($doctor);
        $res = $this->postJson("/api/v2/prescriptions/{$id}/revise", [
            'diagnosis' => 'Updated diagnosis',
            'items' => [['medicine_id' => $newMedicine->id, 'dosage' => '750mg']],
        ])->assertCreated();

        $newId = $res->json('data.prescription.id');
        $this->assertNotSame($id, $newId, 'a revision must be a new row, not the same one rewritten');
        $this->assertSame($id, $res->json('data.prescription.revises_prescription_id'));
        $this->assertSame('Updated diagnosis', $res->json('data.prescription.diagnosis'));

        // The old one is cancelled, not deleted, and reads as superseded.
        $this->assertDatabaseHas('prescriptions', ['id' => $id, 'status' => Prescription::STATUS_CANCELLED]);
        $old = $this->getJson("/api/v2/prescriptions/{$id}")->assertOk();
        $this->assertTrue($old->json('data.prescription.superseded'));

        // The patient still reads BOTH — nothing vanished.
        $this->getJson("/api/v2/prescriptions/{$id}")->assertOk();
        $this->getJson("/api/v2/prescriptions/{$newId}")->assertOk();
    }

    public function test_a_different_doctor_cannot_revise_someone_elses_prescription(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $otherDoctor = $this->user(User::TYPE_BUSINESS, 'Other');

        $id = $this->issue($doctor, $patient);

        Sanctum::actingAs($otherDoctor);
        $this->postJson("/api/v2/prescriptions/{$id}/revise", [
            'items' => [['medicine_id' => $this->medicine('X')->id]],
        ])->assertNotFound();
    }

    public function test_a_client_cannot_issue_a_prescription(): void
    {
        $notADoctor = $this->user(User::TYPE_CLIENT, 'Client');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');

        Sanctum::actingAs($notADoctor);
        $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'items' => [['name' => 'Aspirin']],
        ])->assertForbidden();
    }
}
