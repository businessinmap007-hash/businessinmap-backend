<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\User;
use App\Services\DeliveryDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The same connected delivery loop `DeliveryJourneyTest` walks for menu
 * orders, mirrored onto prescriptions: a driver accepts a ready delivery
 * prescription, scans the pharmacy's pickup QR, then the patient scans the
 * driver's delivery QR — which is also the moment the prescription itself
 * becomes dispensed (one rule, {@see \App\Services\Prescriptions\
 * PrescriptionService::dispense()}, not reimplemented here).
 */
class PrescriptionDeliveryTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type, string $tag, ?int $childId = null): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '0105' . random_int(1000000, 9999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);
        if ($childId !== null) {
            $u->category_child_id = $childId;
        }
        $u->save();

        return $u;
    }

    private function medicine(string $name): Medicine
    {
        return Medicine::create(['name' => $name . ' ' . Str::random(4)]);
    }

    private function aDriver(string $name = 'Driver'): User
    {
        $driver = $this->user(User::TYPE_CLIENT, $name);

        Sanctum::actingAs($driver);
        $this->postJson('/api/v2/delivery/register', ['vehicle_label' => 'دراجة'])->assertCreated();

        return $driver;
    }

    /** A ready, priced, delivery prescription — the state every driver test needs. */
    private function readyForDelivery(User $doctor, User $patient, User $pharmacy): Prescription
    {
        Sanctum::actingAs($doctor);
        $id = (int) $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'items' => [['medicine_id' => $this->medicine('Paracetamol')->id]],
        ])->assertCreated()->json('data.prescription.id');

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/prescriptions/{$id}/send", [
            'pharmacy_id' => $pharmacy->id,
            'fulfillment_type' => Prescription::FULFILLMENT_DELIVERY,
            'delivery_address' => '12 شارع الجمهورية، وسط البلد',
        ])->assertOk();

        $itemId = Prescription::query()->findOrFail($id)->items()->value('id');

        Sanctum::actingAs($pharmacy);
        $this->postJson("/api/v2/pharmacy/prescriptions/{$id}/prepare")->assertOk();
        $this->postJson("/api/v2/pharmacy/prescriptions/{$id}/price", [
            'items' => [['prescription_item_id' => $itemId, 'unit_price' => 10, 'billed_quantity' => 1]],
        ])->assertOk();
        $this->postJson("/api/v2/pharmacy/prescriptions/{$id}/ready")->assertOk();

        return Prescription::query()->findOrFail($id);
    }

    public function test_a_prescription_travels_from_the_pharmacy_to_the_patient_through_both_scans(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy', 215);

        $prescription = $this->readyForDelivery($doctor, $patient, $pharmacy);
        $driver = $this->aDriver();

        // The driver learns the job from the board — never told the id directly.
        Sanctum::actingAs($driver);
        $available = $this->getJson('/api/v2/delivery/available-prescriptions')
            ->assertOk()
            ->json('data.prescriptions');

        $this->assertContains(
            $prescription->id,
            array_column($available, 'prescription_id'),
            'a ready delivery prescription must be offered to drivers'
        );

        $job = collect($available)->firstWhere('prescription_id', $prescription->id);
        $this->assertSame($pharmacy->name, $job['pharmacy']['name'] ?? null);

        $this->postJson("/api/v2/delivery/prescriptions/{$prescription->id}/accept")
            ->assertCreated()
            ->assertJsonPath('data.delivery_stage', DeliveryDispatchService::STAGE_ASSIGNED);

        // Taken — must vanish from every other driver's board.
        $secondDriver = $this->aDriver('OtherDriver');
        Sanctum::actingAs($secondDriver);
        $stillOffered = $this->getJson('/api/v2/delivery/available-prescriptions')->assertOk()->json('data.prescriptions');
        $this->assertNotContains($prescription->id, array_column($stillOffered, 'prescription_id'));

        // Stage 1: the pharmacy shows its pickup QR, the driver scans it.
        Sanctum::actingAs($pharmacy);
        $pickupToken = $this->postJson("/api/v2/delivery/prescriptions/{$prescription->id}/pickup-token")
            ->assertOk()->json('data.pickup_token');
        $this->assertNotEmpty($pickupToken);

        Sanctum::actingAs($driver);
        $this->postJson("/api/v2/delivery/prescriptions/pickup/{$pickupToken}/confirm")
            ->assertOk()
            ->assertJsonPath('data.delivery_stage', DeliveryDispatchService::STAGE_PICKED_UP);

        // Stage 2: the driver shows their delivery QR, the patient scans it.
        $deliveryToken = $this->postJson("/api/v2/delivery/prescriptions/{$prescription->id}/delivery-token")
            ->assertOk()->json('data.delivery_token');
        $this->assertNotEmpty($deliveryToken);

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/delivery/prescriptions/deliver/{$deliveryToken}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', Prescription::STATUS_DISPENSED)
            ->assertJsonPath('data.delivery_stage', DeliveryDispatchService::STAGE_DELIVERED);

        $prescription->refresh();
        $this->assertSame(Prescription::STATUS_DISPENSED, $prescription->status);
        $this->assertNotNull($prescription->dispensed_at);
    }

    public function test_a_scanned_token_cannot_be_scanned_again(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy', 215);
        $prescription = $this->readyForDelivery($doctor, $patient, $pharmacy);
        $driver = $this->aDriver();

        Sanctum::actingAs($driver);
        $this->postJson("/api/v2/delivery/prescriptions/{$prescription->id}/accept")->assertCreated();

        Sanctum::actingAs($pharmacy);
        $pickupToken = $this->postJson("/api/v2/delivery/prescriptions/{$prescription->id}/pickup-token")
            ->assertOk()->json('data.pickup_token');

        Sanctum::actingAs($driver);
        $this->postJson("/api/v2/delivery/prescriptions/pickup/{$pickupToken}/confirm")->assertOk();
        $this->postJson("/api/v2/delivery/prescriptions/pickup/{$pickupToken}/confirm")->assertNotFound();

        $deliveryToken = $this->postJson("/api/v2/delivery/prescriptions/{$prescription->id}/delivery-token")
            ->assertOk()->json('data.delivery_token');

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/delivery/prescriptions/deliver/{$deliveryToken}/confirm")->assertOk();
        $this->postJson("/api/v2/delivery/prescriptions/deliver/{$deliveryToken}/confirm")->assertNotFound();
    }

    public function test_a_token_is_worthless_in_the_wrong_hands(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy', 215);
        $prescription = $this->readyForDelivery($doctor, $patient, $pharmacy);
        $driver = $this->aDriver();

        Sanctum::actingAs($driver);
        $this->postJson("/api/v2/delivery/prescriptions/{$prescription->id}/accept")->assertCreated();

        Sanctum::actingAs($pharmacy);
        $pickupToken = $this->postJson("/api/v2/delivery/prescriptions/{$prescription->id}/pickup-token")
            ->assertOk()->json('data.pickup_token');

        $intruder = $this->aDriver('Intruder');
        Sanctum::actingAs($intruder);
        $this->postJson("/api/v2/delivery/prescriptions/pickup/{$pickupToken}/confirm")->assertForbidden();

        Sanctum::actingAs($driver);
        $this->postJson("/api/v2/delivery/prescriptions/pickup/{$pickupToken}/confirm")->assertOk();

        $deliveryToken = $this->postJson("/api/v2/delivery/prescriptions/{$prescription->id}/delivery-token")
            ->assertOk()->json('data.delivery_token');

        $stranger = $this->user(User::TYPE_CLIENT, 'Stranger');
        Sanctum::actingAs($stranger);
        $this->postJson("/api/v2/delivery/prescriptions/deliver/{$deliveryToken}/confirm")->assertForbidden();

        $this->assertSame(
            DeliveryDispatchService::STAGE_PICKED_UP,
            (string) Prescription::query()->find($prescription->id)->delivery_stage,
            'a refused scan must not have moved the prescription'
        );
    }

    public function test_the_stages_cannot_be_skipped(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy', 215);
        $prescription = $this->readyForDelivery($doctor, $patient, $pharmacy);
        $driver = $this->aDriver();

        // No driver assigned yet: nobody to hand the medicine to.
        Sanctum::actingAs($pharmacy);
        $this->postJson("/api/v2/delivery/prescriptions/{$prescription->id}/pickup-token")->assertStatus(422);

        Sanctum::actingAs($driver);
        $this->postJson("/api/v2/delivery/prescriptions/{$prescription->id}/accept")->assertCreated();

        // Assigned, but not yet picked up.
        $this->postJson("/api/v2/delivery/prescriptions/{$prescription->id}/delivery-token")->assertStatus(422);
    }

    public function test_only_a_registered_driver_can_see_the_job_board(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy', 215);
        $this->readyForDelivery($doctor, $patient, $pharmacy);

        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'Ordinary'));
        $this->getJson('/api/v2/delivery/available-prescriptions')->assertForbidden();
    }

    public function test_a_pickup_prescription_never_appears_on_the_delivery_board(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy', 215);

        Sanctum::actingAs($doctor);
        $id = (int) $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'items' => [['medicine_id' => $this->medicine('Vitamin C')->id]],
        ])->assertCreated()->json('data.prescription.id');

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/prescriptions/{$id}/send", [
            'pharmacy_id' => $pharmacy->id,
            'fulfillment_type' => Prescription::FULFILLMENT_PICKUP,
        ])->assertOk();

        $itemId = Prescription::query()->findOrFail($id)->items()->value('id');

        Sanctum::actingAs($pharmacy);
        $this->postJson("/api/v2/pharmacy/prescriptions/{$id}/price", [
            'items' => [['prescription_item_id' => $itemId, 'unit_price' => 5, 'billed_quantity' => 1]],
        ])->assertOk();
        $this->postJson("/api/v2/pharmacy/prescriptions/{$id}/ready")->assertOk();

        $driver = $this->aDriver();
        Sanctum::actingAs($driver);
        $available = $this->getJson('/api/v2/delivery/available-prescriptions')->assertOk()->json('data.prescriptions');

        $this->assertNotContains($id, array_column($available, 'prescription_id'), 'a pickup prescription is not a delivery job');
    }
}
