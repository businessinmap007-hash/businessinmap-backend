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
 * A customer asks a pharmacy for medicine directly — no doctor, no dictionary
 * drug on their side (a photo/note only). The pharmacy replies with real,
 * priced items; nothing is prepared until the customer accepts the quote.
 */
class MedicineRequestFlowTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type, string $tag, ?int $categoryChildId = null): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '0105' . random_int(1000000, 9999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->category_child_id = $categoryChildId;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    public function test_the_full_request_quote_confirm_dispense_journey(): void
    {
        $customer = $this->user(User::TYPE_CLIENT, 'Customer');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy', Prescription::PHARMACY_CHILD_ID);
        $panadol = Medicine::create(['name' => 'Panadol Extra ' . Str::random(4)]);

        Sanctum::actingAs($customer);
        $id = (int) $this->postJson('/api/v2/prescriptions/request', [
            'pharmacy_id' => $pharmacy->id, 'note' => 'محتاج حاجة للصداع وكحة',
        ])->assertCreated()->assertJsonPath('data.prescription.status', 'requested')
            ->assertJsonPath('data.prescription.origin', 'customer')
            ->assertJsonPath('data.prescription.doctor', null)
            ->json('data.prescription.id');

        // Only a real pharmacy account may be targeted.
        $notAPharmacy = $this->user(User::TYPE_BUSINESS, 'JustAShop');
        $this->postJson('/api/v2/prescriptions/request', ['pharmacy_id' => $notAPharmacy->id, 'note' => 'x'])
            ->assertStatus(422);

        // A stranger cannot read it.
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'Stranger'));
        $this->getJson("/api/v2/prescriptions/{$id}")->assertStatus(404);

        // The pharmacy sees it in its incoming queue and quotes it.
        Sanctum::actingAs($pharmacy);
        $listed = collect($this->getJson('/api/v2/pharmacy/prescriptions')->assertOk()->json('data.data'))->pluck('id');
        $this->assertContains($id, $listed);

        $this->postJson("/api/v2/pharmacy/prescriptions/{$id}/quote", [
            'items' => [['medicine_id' => $panadol->id, 'quantity' => 2, 'unit_price' => 15, 'note' => 'بعد الأكل']],
        ])->assertOk()
            ->assertJsonPath('data.prescription.status', 'quoted')
            ->assertJsonPath('data.prescription.medicine_total', 30);

        // The pharmacy cannot jump straight to preparing — the customer hasn't confirmed yet.
        $this->postJson("/api/v2/pharmacy/prescriptions/{$id}/prepare")->assertStatus(422);

        // The customer confirms the quote.
        Sanctum::actingAs($customer);
        $this->postJson("/api/v2/prescriptions/{$id}/confirm-quote")
            ->assertOk()->assertJsonPath('data.prescription.status', 'sent_to_pharmacy');

        // Only the customer who owns it may confirm.
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'Stranger2'));
        $this->postJson("/api/v2/prescriptions/{$id}/confirm-quote")->assertStatus(404);

        // Now the ordinary pharmacy pipeline takes over unchanged.
        Sanctum::actingAs($pharmacy);
        $this->postJson("/api/v2/pharmacy/prescriptions/{$id}/prepare")->assertOk();
        $this->postJson("/api/v2/pharmacy/prescriptions/{$id}/ready")->assertOk();
        $this->postJson("/api/v2/pharmacy/prescriptions/{$id}/dispense")
            ->assertOk()->assertJsonPath('data.prescription.status', 'dispensed');
    }

    public function test_the_pharmacy_can_decline_a_request_it_cannot_fulfil(): void
    {
        $customer = $this->user(User::TYPE_CLIENT, 'Customer');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy', Prescription::PHARMACY_CHILD_ID);

        Sanctum::actingAs($customer);
        $id = (int) $this->postJson('/api/v2/prescriptions/request', ['pharmacy_id' => $pharmacy->id, 'has_photo' => true])
            ->assertCreated()->json('data.prescription.id');

        Sanctum::actingAs($pharmacy);
        $this->postJson("/api/v2/pharmacy/prescriptions/{$id}/decline", ['note' => 'الصورة غير واضحة'])
            ->assertOk()->assertJsonPath('data.prescription.status', 'cancelled');

        // Another pharmacy cannot decline someone else's request.
        $other = $this->user(User::TYPE_BUSINESS, 'OtherPharmacy', Prescription::PHARMACY_CHILD_ID);
        $second = $this->user(User::TYPE_CLIENT, 'Customer2');
        Sanctum::actingAs($second);
        $secondId = (int) $this->postJson('/api/v2/prescriptions/request', ['pharmacy_id' => $pharmacy->id, 'note' => 'y'])
            ->assertCreated()->json('data.prescription.id');
        Sanctum::actingAs($other);
        $this->postJson("/api/v2/pharmacy/prescriptions/{$secondId}/decline")->assertStatus(404);
    }

    public function test_a_request_needs_a_note_or_a_photo(): void
    {
        $customer = $this->user(User::TYPE_CLIENT, 'Customer');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy', Prescription::PHARMACY_CHILD_ID);

        Sanctum::actingAs($customer);
        $this->postJson('/api/v2/prescriptions/request', ['pharmacy_id' => $pharmacy->id])
            ->assertStatus(422)->assertJsonValidationErrors('note');
    }
}
