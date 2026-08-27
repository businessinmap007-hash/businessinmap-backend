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
 * The pharmacy's own price + invoice on a prescription — its price, never
 * inferred from the doctor, the drug dictionary, or the pharmacy's own
 * «قاموس الأدوية» catalog. All-or-nothing per prescription, and dispensing
 * is refused until it has been priced at least once.
 */
class PrescriptionPricingTest extends TestCase
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

    /** @return array{0:Prescription,1:array<int,int>} the sent-to-pharmacy row + its item ids */
    private function sentPrescription(User $doctor, User $patient, User $pharmacy): array
    {
        Sanctum::actingAs($doctor);
        $id = (int) $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'items' => [
                ['medicine_id' => $this->medicine('Paracetamol')->id, 'dosage' => '500mg'],
                ['medicine_id' => $this->medicine('Vitamin C')->id, 'dosage' => '1000mg'],
            ],
        ])->assertCreated()->json('data.prescription.id');

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/prescriptions/{$id}/send", [
            'pharmacy_id' => $pharmacy->id,
            'fulfillment_type' => Prescription::FULFILLMENT_PICKUP,
        ])->assertOk();

        $row = Prescription::query()->with('items')->findOrFail($id);

        return [$row, $row->items->pluck('id')->all()];
    }

    public function test_the_pharmacy_prices_every_line_and_the_total_is_computed(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy', 215);

        [$row, $itemIds] = $this->sentPrescription($doctor, $patient, $pharmacy);

        Sanctum::actingAs($pharmacy);
        $res = $this->postJson("/api/v2/pharmacy/prescriptions/{$row->id}/price", [
            'items' => [
                ['prescription_item_id' => $itemIds[0], 'unit_price' => 25.5, 'billed_quantity' => 2],
                ['prescription_item_id' => $itemIds[1], 'unit_price' => 10, 'billed_quantity' => 1],
            ],
        ])->assertOk();

        $this->assertEquals(61.0, $res->json('data.prescription.medicine_total'));
        $this->assertNotNull($res->json('data.prescription.priced_at'));

        $row->refresh();
        $this->assertSame(61.0, (float) $row->medicine_total);
        $this->assertNotNull($row->priced_at);

        // The doctor and patient both see the invoice too.
        Sanctum::actingAs($patient);
        $this->assertEquals(
            61.0,
            $this->getJson("/api/v2/prescriptions/{$row->id}")->assertOk()->json('data.prescription.medicine_total'),
        );
    }

    public function test_pricing_must_cover_every_item_or_it_is_refused(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy', 215);

        [$row, $itemIds] = $this->sentPrescription($doctor, $patient, $pharmacy);

        Sanctum::actingAs($pharmacy);
        $this->postJson("/api/v2/pharmacy/prescriptions/{$row->id}/price", [
            'items' => [
                ['prescription_item_id' => $itemIds[0], 'unit_price' => 25.5, 'billed_quantity' => 2],
            ],
        ])->assertStatus(422);

        $this->assertNull($row->fresh()->medicine_total);
    }

    public function test_an_item_id_belonging_to_another_prescription_is_refused(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy', 215);

        [$rowA, $itemIdsA] = $this->sentPrescription($doctor, $patient, $pharmacy);
        [$rowB, $itemIdsB] = $this->sentPrescription($doctor, $patient, $pharmacy);

        Sanctum::actingAs($pharmacy);
        $this->postJson("/api/v2/pharmacy/prescriptions/{$rowA->id}/price", [
            'items' => [
                ['prescription_item_id' => $itemIdsB[0], 'unit_price' => 10, 'billed_quantity' => 1],
                ['prescription_item_id' => $itemIdsA[1], 'unit_price' => 10, 'billed_quantity' => 1],
            ],
        ])->assertStatus(422);
    }

    public function test_dispensing_is_refused_until_priced_then_succeeds(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy', 215);

        [$row, $itemIds] = $this->sentPrescription($doctor, $patient, $pharmacy);

        Sanctum::actingAs($pharmacy);
        $this->postJson("/api/v2/pharmacy/prescriptions/{$row->id}/prepare")->assertOk();
        $this->postJson("/api/v2/pharmacy/prescriptions/{$row->id}/ready")->assertOk();

        // Not priced yet — dispense is refused.
        $this->postJson("/api/v2/pharmacy/prescriptions/{$row->id}/dispense")->assertStatus(422);

        $this->postJson("/api/v2/pharmacy/prescriptions/{$row->id}/price", [
            'items' => [
                ['prescription_item_id' => $itemIds[0], 'unit_price' => 5, 'billed_quantity' => 1],
                ['prescription_item_id' => $itemIds[1], 'unit_price' => 5, 'billed_quantity' => 1],
            ],
        ])->assertOk();

        $this->postJson("/api/v2/pharmacy/prescriptions/{$row->id}/dispense")->assertOk();
        $this->assertSame(Prescription::STATUS_DISPENSED, $row->fresh()->status);
    }

    public function test_pricing_is_repeatable_and_overwrites_the_previous_total(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy', 215);

        [$row, $itemIds] = $this->sentPrescription($doctor, $patient, $pharmacy);

        Sanctum::actingAs($pharmacy);
        $this->postJson("/api/v2/pharmacy/prescriptions/{$row->id}/price", [
            'items' => [
                ['prescription_item_id' => $itemIds[0], 'unit_price' => 10, 'billed_quantity' => 1],
                ['prescription_item_id' => $itemIds[1], 'unit_price' => 10, 'billed_quantity' => 1],
            ],
        ])->assertOk();
        $this->assertSame(20.0, (float) $row->fresh()->medicine_total);

        // Stock ran out for one line — the pharmacy re-prices.
        $this->postJson("/api/v2/pharmacy/prescriptions/{$row->id}/price", [
            'items' => [
                ['prescription_item_id' => $itemIds[0], 'unit_price' => 12, 'billed_quantity' => 3],
                ['prescription_item_id' => $itemIds[1], 'unit_price' => 10, 'billed_quantity' => 1],
            ],
        ])->assertOk();
        $this->assertSame(46.0, (float) $row->fresh()->medicine_total);
    }

    public function test_a_different_pharmacy_cannot_price_it(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy', 215);
        $otherPharmacy = $this->user(User::TYPE_BUSINESS, 'OtherPharmacy', 215);

        [$row, $itemIds] = $this->sentPrescription($doctor, $patient, $pharmacy);

        Sanctum::actingAs($otherPharmacy);
        $this->postJson("/api/v2/pharmacy/prescriptions/{$row->id}/price", [
            'items' => [
                ['prescription_item_id' => $itemIds[0], 'unit_price' => 10, 'billed_quantity' => 1],
                ['prescription_item_id' => $itemIds[1], 'unit_price' => 10, 'billed_quantity' => 1],
            ],
        ])->assertNotFound();
    }
}
