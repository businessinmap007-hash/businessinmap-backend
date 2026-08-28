<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Prescription delivery used to take only a free-text address string — every
 * other delivery-needing flow (menu/retail checkout, see CheckoutAddressBookTest)
 * resolves against the saved address book instead. This proves the same
 * two-source pattern now works here too: a saved address wins when given, a
 * snapshot is written either way, and one patient cannot borrow another's address.
 */
class PrescriptionDeliveryAddressTest extends TestCase
{
    use DatabaseTransactions;

    private int $governorateId;
    private int $cityId;

    protected function setUp(): void
    {
        parent::setUp();

        $governorate = Governorate::query()->whereHas('cities')->first();

        if (! $governorate) {
            $this->markTestSkipped('Needs a governorate with cities.');
        }

        $this->governorateId = (int) $governorate->id;
        $this->cityId = (int) City::query()->where('governorate_id', $governorate->id)->value('id');
    }

    private function user(string $type, string $tag): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '0106' . random_int(1000000, 9999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);

        if ($tag === 'Clinic') {
            $u->category_child_id = 514;
        }

        $u->save();

        return $u;
    }

    private function addressFor(int $userId): Address
    {
        return Address::create([
            'user_id' => $userId,
            'governorate_id' => $this->governorateId,
            'city_id' => $this->cityId,
            'address_line' => 'شارع الجلاء، عمارة 9',
            'lat' => 30.05,
            'lng' => 31.24,
        ]);
    }

    private function issuedPrescriptionId(User $doctor, User $patient): int
    {
        Sanctum::actingAs($doctor);
        $medicine = Medicine::create(['name' => 'Amoxicillin ' . Str::random(4)]);

        return $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'diagnosis' => 'Infection',
            'items' => [['medicine_id' => $medicine->id, 'dosage' => '500mg', 'quantity' => '1 box']],
        ])->assertCreated()->json('data.prescription.id');
    }

    public function test_a_saved_address_is_snapshotted_and_pointed_at(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy');
        $address = $this->addressFor($patient->id);
        $id = $this->issuedPrescriptionId($doctor, $patient);

        Sanctum::actingAs($patient);

        $this->postJson("/api/v2/prescriptions/{$id}/send", [
            'pharmacy_id' => $pharmacy->id,
            'fulfillment_type' => 'delivery',
            'address_id' => $address->id,
        ])->assertOk()
            ->assertJsonPath('data.prescription.delivery_address_id', $address->id)
            ->assertJsonPath('data.prescription.delivery_address', $address->toDeliveryLine());

        $this->assertSame((int) $address->id, Prescription::find($id)->delivery_address_id);
    }

    public function test_a_free_text_address_still_works_without_an_address_id(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy');
        $id = $this->issuedPrescriptionId($doctor, $patient);

        Sanctum::actingAs($patient);

        $this->postJson("/api/v2/prescriptions/{$id}/send", [
            'pharmacy_id' => $pharmacy->id,
            'fulfillment_type' => 'delivery',
            'delivery_address' => '12 Nile St, Cairo',
        ])->assertOk()
            ->assertJsonPath('data.prescription.delivery_address', '12 Nile St, Cairo')
            ->assertJsonPath('data.prescription.delivery_address_id', null);
    }

    public function test_another_patients_address_is_refused(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $stranger = $this->user(User::TYPE_CLIENT, 'Stranger');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy');
        $foreignAddress = $this->addressFor($stranger->id);
        $id = $this->issuedPrescriptionId($doctor, $patient);

        Sanctum::actingAs($patient);

        $this->postJson("/api/v2/prescriptions/{$id}/send", [
            'pharmacy_id' => $pharmacy->id,
            'fulfillment_type' => 'delivery',
            'address_id' => $foreignAddress->id,
        ])->assertStatus(422)->assertJsonValidationErrors('address_id');
    }
}
