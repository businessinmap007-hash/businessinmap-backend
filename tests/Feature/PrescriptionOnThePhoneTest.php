<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «الروشتات تُحفظ على الفون وعند مشاركتها تُقرأ وتُعرض للصيدلي» — المالك، 2026-10-05. A copy kept on the patient's
 * phone is proved authentic against the doctor's fingerprint at the counter, then dispensed exactly once.
 * Rolls back.
 */
class PrescriptionOnThePhoneTest extends TestCase
{
    use DatabaseTransactions;

    private User $doctor;
    private User $patient;
    private User $pharmacy;
    private int $id;

    private function user(string $type, string $tag, ?int $child = null): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '0105' . random_int(1000000, 9999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);
        if ($child) {
            $u->category_child_id = $child;
        }
        $u->save();

        return $u;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $this->patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $this->pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy');

        Sanctum::actingAs($this->doctor);
        $a = Medicine::create(['name' => 'Paracetamol ' . Str::random(4)]);
        $this->id = (int) $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $this->patient->id, 'diagnosis' => 'Flu',
            'items' => [['medicine_id' => $a->id, 'dosage' => '500mg', 'quantity' => '2 boxes', 'instructions' => 'After meals']],
        ])->assertCreated()->json('data.prescription.id');
    }

    /** What the patient's phone keeps: the `verifiable.content` the server handed him. */
    private function copy(): array
    {
        Sanctum::actingAs($this->patient);

        return $this->getJson("/api/v2/prescriptions/{$this->id}")->assertOk()->json('data.prescription.verifiable');
    }

    public function test_the_patient_gets_the_doctors_content_and_its_fingerprint(): void
    {
        $copy = $this->copy();

        $this->assertNotEmpty($copy['hash']);
        $this->assertSame((string) $this->id, (string) $copy['content']['id']);
        $this->assertSame('500mg', $copy['content']['items'][0]['dosage']);
        $this->assertSame($copy['hash'], Prescription::query()->findOrFail($this->id)->content_hash);
    }

    public function test_the_pharmacy_sees_a_shown_copy_is_exactly_what_the_doctor_wrote(): void
    {
        $copy = $this->copy();
        Sanctum::actingAs($this->pharmacy);

        $check = $this->postJson('/api/v2/pharmacy/prescriptions/verify', ['id' => $this->id, 'content' => $copy['content']])->assertOk()->json('data');

        $this->assertTrue($check['authentic']);
        $this->assertTrue($check['can_dispense']);
        $this->assertSame($this->doctor->id, $check['doctor']['id']);
    }

    public function test_a_copy_the_patient_edited_is_not_authentic(): void
    {
        $copy = $this->copy();
        $copy['content']['items'][0]['quantity'] = '20 boxes';
        Sanctum::actingAs($this->pharmacy);

        $this->postJson('/api/v2/pharmacy/prescriptions/verify', ['id' => $this->id, 'content' => $copy['content']])->assertOk()->assertJsonPath('data.authentic', false);
        $this->postJson('/api/v2/pharmacy/prescriptions/dispense-in-person', ['id' => $this->id, 'content' => $copy['content']])->assertUnprocessable();
        $this->assertSame('issued', Prescription::query()->findOrFail($this->id)->status);
    }

    public function test_content_survives_a_json_round_trip_through_a_phone(): void
    {
        // Numbers become strings and strings stay strings on the way: the fingerprint must not care.
        $copy = $this->copy();
        $content = json_decode(json_encode($copy['content']), true);
        $content['doctor_id'] = (int) $content['doctor_id'];
        Sanctum::actingAs($this->pharmacy);

        $this->postJson('/api/v2/pharmacy/prescriptions/verify', ['id' => $this->id, 'content' => $content])->assertOk()->assertJsonPath('data.authentic', true);
    }

    public function test_it_is_dispensed_once_and_a_second_pharmacy_is_refused(): void
    {
        $copy = $this->copy();
        $other = $this->user(User::TYPE_BUSINESS, 'Pharmacy');

        Sanctum::actingAs($this->pharmacy);
        $this->postJson('/api/v2/pharmacy/prescriptions/dispense-in-person', ['id' => $this->id, 'content' => $copy['content']])
            ->assertOk()->assertJsonPath('data.status', 'dispensed');

        $row = Prescription::query()->findOrFail($this->id);
        $this->assertSame($this->pharmacy->id, (int) $row->pharmacy_id);
        $this->assertNotNull($row->dispensed_at);

        Sanctum::actingAs($other);
        $this->postJson('/api/v2/pharmacy/prescriptions/dispense-in-person', ['id' => $this->id, 'content' => $copy['content']])->assertUnprocessable();
        $this->postJson('/api/v2/pharmacy/prescriptions/verify', ['id' => $this->id, 'content' => $copy['content']])
            ->assertOk()->assertJsonPath('data.can_dispense', false)->assertJsonPath('data.status', 'dispensed');
    }

    public function test_an_amended_prescription_makes_the_old_copy_useless(): void
    {
        $old = $this->copy();
        Sanctum::actingAs($this->doctor);
        $b = Medicine::create(['name' => 'Ibuprofen ' . Str::random(4)]);
        $this->postJson("/api/v2/prescriptions/{$this->id}/revise", ['items' => [['medicine_id' => $b->id, 'dosage' => '400mg', 'quantity' => '1 box']]])->assertCreated();

        Sanctum::actingAs($this->pharmacy);
        $check = $this->postJson('/api/v2/pharmacy/prescriptions/verify', ['id' => $this->id, 'content' => $old['content']])->assertOk()->json('data');

        $this->assertTrue($check['authentic'], 'it is what the doctor wrote then…');
        $this->assertTrue($check['superseded'], '…but he replaced it');
        $this->assertFalse($check['can_dispense']);
        $this->postJson('/api/v2/pharmacy/prescriptions/dispense-in-person', ['id' => $this->id, 'content' => $old['content']])->assertUnprocessable();
    }

    public function test_one_sent_to_another_pharmacy_cannot_be_taken_at_the_counter(): void
    {
        $copy = $this->copy();
        $elsewhere = $this->user(User::TYPE_BUSINESS, 'Pharmacy');
        Sanctum::actingAs($this->patient);
        $this->postJson("/api/v2/prescriptions/{$this->id}/send", ['pharmacy_id' => $elsewhere->id, 'fulfillment_type' => 'pickup'])->assertOk();

        Sanctum::actingAs($this->pharmacy);
        $this->postJson('/api/v2/pharmacy/prescriptions/dispense-in-person', ['id' => $this->id, 'content' => $copy['content']])->assertUnprocessable();
    }

    public function test_a_patient_account_cannot_use_the_pharmacy_door(): void
    {
        $copy = $this->copy();

        $this->postJson('/api/v2/pharmacy/prescriptions/verify', ['id' => $this->id, 'content' => $copy['content']])->assertForbidden();
    }
}
