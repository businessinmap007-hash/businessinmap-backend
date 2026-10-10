<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Media\ImageUploadService;
use App\Support\ControlledMedicines;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «هناك بعض الأدوية المخدرة فلا بد من صورة روشتة بخط الطبيب منعًا للاحتيال» — المالك، 2026-10-05.
 * A prescription with a narcotic / psychotropic drug is not issued without a photo of the doctor's handwritten
 * paper, and a pharmacy does not dispense it without that photo. Rolls back (the uploaded files are removed).
 */
class ControlledPrescriptionTest extends TestCase
{
    use DatabaseTransactions;

    private User $doctor;
    private User $patient;
    private User $pharmacy;
    private Medicine $plain;
    private Medicine $controlled;

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
        $this->plain = Medicine::create(['name' => 'Paracetamol ' . Str::random(4), 'scientific_name' => 'PARACETAMOL']);
        $this->controlled = Medicine::create(['name' => 'Tramal ' . Str::random(4), 'scientific_name' => 'TRAMADOL', 'is_controlled' => true]);
        Sanctum::actingAs($this->doctor);
    }

    protected function tearDown(): void
    {
        foreach (Image::query()->where('purpose', Image::PURPOSE_HANDWRITTEN)->get() as $image) {
            app(ImageUploadService::class)->delete($image->image);
        }

        parent::tearDown();
    }

    /** A real one-pixel PNG (the local PHP has no GD to draw one). */
    private function paper(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('paper.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    }

    private function body(array $medicines, array $extra = []): array
    {
        return $extra + [
            'patient_id' => $this->patient->id,
            'items' => array_map(fn (Medicine $m) => ['medicine_id' => $m->id, 'dosage' => '1 tab', 'quantity' => '1 box'], $medicines),
        ];
    }

    /** What the app sends: multipart, the items as a JSON string, the photo of the paper. */
    private function issueWithPaper(array $medicines)
    {
        $body = $this->body($medicines);
        $body['items'] = json_encode($body['items']);
        $body['handwritten_image'] = $this->paper();
        $body['handwritten_source'] = 'camera';

        return $this->post('/api/v2/prescriptions', $body, ['Accept' => 'application/json']);
    }

    public function test_the_controlled_list_matches_whole_ingredient_words_only(): void
    {
        $this->assertTrue(ControlledMedicines::matches('TRAMADOL'));
        $this->assertTrue(ControlledMedicines::matches('PARACETAMOL(ACETAMINOPHEN)+TRAMADOL'));
        $this->assertTrue(ControlledMedicines::matches('DIHYDROCODEINE'));
        $this->assertTrue(ControlledMedicines::matches(null, 'Xanax ALPRAZOLAM 0.5'));
        $this->assertFalse(ControlledMedicines::matches('IPRATROPIUM BROMIDE'), 'ipratropium is not opium');
        $this->assertFalse(ControlledMedicines::matches('APOMORPHINE'), 'apomorphine is not morphine');
        $this->assertFalse(ControlledMedicines::matches('PARACETAMOL'));
        $this->assertFalse(ControlledMedicines::matches('PREGABALIN'), 'a starting list: pregabalin is left to the regulator');
    }

    public function test_the_dictionary_tells_the_doctor_which_drug_is_controlled(): void
    {
        $rows = $this->getJson('/api/v2/medicines?q=' . urlencode($this->controlled->name))->assertOk()->json('data');

        $this->assertTrue($rows[0]['is_controlled']);
        $this->assertFalse($this->getJson('/api/v2/medicines?q=' . urlencode($this->plain->name))->json('data.0.is_controlled'));
    }

    public function test_a_drug_a_doctor_adds_by_a_controlled_name_is_flagged(): void
    {
        $id = $this->postJson('/api/v2/medicines', ['name' => 'ALPRAZOLAM ' . Str::random(4)])->assertCreated()->json('data.id');

        $this->assertTrue(Medicine::query()->findOrFail($id)->is_controlled);
    }

    public function test_a_prescription_with_a_controlled_drug_is_refused_without_the_handwritten_photo(): void
    {
        $before = Prescription::query()->count();

        $this->postJson('/api/v2/prescriptions', $this->body([$this->plain, $this->controlled]))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'handwritten_required')
            ->assertJsonPath('controlled.0', $this->controlled->name);

        $this->assertSame($before, Prescription::query()->count(), 'nothing is stored');
    }

    public function test_an_ordinary_prescription_needs_no_photo(): void
    {
        $this->postJson('/api/v2/prescriptions', $this->body([$this->plain]))
            ->assertCreated()->assertJsonPath('data.prescription.controlled', false)->assertJsonPath('data.prescription.handwritten_image', null);
    }

    public function test_with_the_photo_it_is_issued_and_carries_the_photo(): void
    {
        $response = $this->issueWithPaper([$this->controlled])->assertCreated();

        $this->assertTrue($response->json('data.prescription.controlled'));
        $this->assertNotEmpty($response->json('data.prescription.handwritten_image'));

        $row = Prescription::query()->findOrFail($response->json('data.prescription.id'));
        $this->assertSame('camera', $row->handwrittenImage()->source);
        $this->assertCount(1, $row->items);
    }

    public function test_an_amendment_with_a_controlled_drug_needs_its_own_photo(): void
    {
        $id = $this->postJson('/api/v2/prescriptions', $this->body([$this->plain]))->assertCreated()->json('data.prescription.id');

        $this->postJson("/api/v2/prescriptions/{$id}/revise", ['items' => [['medicine_id' => $this->controlled->id, 'dosage' => '1']]])
            ->assertUnprocessable()->assertJsonPath('code', 'handwritten_required');
        $this->assertSame('issued', Prescription::query()->findOrFail($id)->status, 'the old one is untouched');

        $body = ['items' => json_encode([['medicine_id' => $this->controlled->id, 'dosage' => '1']]), 'handwritten_image' => $this->paper()];
        $this->post("/api/v2/prescriptions/{$id}/revise", $body, ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.prescription.controlled', true);
    }

    public function test_the_pharmacy_gets_the_handwritten_photo_to_compare_with_the_paper(): void
    {
        $id = $this->issueWithPaper([$this->controlled])->assertCreated()->json('data.prescription.id');
        Sanctum::actingAs($this->patient);
        $copy = $this->getJson("/api/v2/prescriptions/{$id}")->json('data.prescription.verifiable');

        Sanctum::actingAs($this->pharmacy);
        $check = $this->postJson('/api/v2/pharmacy/prescriptions/verify', ['id' => $id, 'content' => $copy['content']])->assertOk()->json('data');

        $this->assertTrue($check['authentic']);
        $this->assertTrue($check['controlled']);
        $this->assertNotEmpty($check['handwritten_image']);
        $this->assertTrue($check['can_dispense']);
        $this->postJson('/api/v2/pharmacy/prescriptions/dispense-in-person', ['id' => $id, 'content' => $copy['content']])->assertOk();
    }

    public function test_a_controlled_prescription_without_the_photo_cannot_be_dispensed(): void
    {
        // a prescription written before the rule: a controlled line and no handwritten paper
        $id = $this->postJson('/api/v2/prescriptions', $this->body([$this->plain]))->assertCreated()->json('data.prescription.id');
        $row = Prescription::query()->findOrFail($id);
        $row->items()->first()->update(['medicine_id' => $this->controlled->id, 'name' => $this->controlled->name]);
        app(\App\Services\Prescriptions\PrescriptionContent::class)->stamp($row->fresh());

        Sanctum::actingAs($this->patient);
        $copy = $this->getJson("/api/v2/prescriptions/{$id}")->json('data.prescription.verifiable');

        Sanctum::actingAs($this->pharmacy);
        $check = $this->postJson('/api/v2/pharmacy/prescriptions/verify', ['id' => $id, 'content' => $copy['content']])->assertOk()->json('data');
        $this->assertTrue($check['controlled']);
        $this->assertNull($check['handwritten_image']);
        $this->assertFalse($check['can_dispense']);

        $this->postJson('/api/v2/pharmacy/prescriptions/dispense-in-person', ['id' => $id, 'content' => $copy['content']])->assertUnprocessable();
        $this->assertSame('issued', $row->fresh()->status);
    }

    /** The doctor writes a controlled prescription to be filled $times times (multipart, like the app). */
    private function issueFilledTimes(int $times, array $medicines)
    {
        $body = $this->body($medicines);
        $body['items'] = json_encode($body['items']);
        $body['handwritten_image'] = $this->paper();
        $body['handwritten_source'] = 'camera';
        $body['dispense_limit'] = $times;

        return $this->post('/api/v2/prescriptions', $body, ['Accept' => 'application/json']);
    }

    public function test_a_controlled_prescription_is_stamped_dispensed_only_after_its_last_filling(): void
    {
        $id = $this->issueFilledTimes(3, [$this->controlled])->assertCreated()->assertJsonPath('data.prescription.dispense_limit', 3)->json('data.prescription.id');

        Sanctum::actingAs($this->patient);
        $copy = $this->getJson("/api/v2/prescriptions/{$id}")->json('data.prescription.verifiable');
        Sanctum::actingAs($this->pharmacy);

        $check = $this->postJson('/api/v2/pharmacy/prescriptions/verify', ['id' => $id, 'content' => $copy['content']])->assertOk()->json('data');
        $this->assertSame(3, $check['dispenses_left']);
        $this->assertFalse($check['final_dispense']);
        $this->assertNull($check['doctor'], 'the pharmacy never learns the doctor');

        // fillings 1 and 2: counted, the prescription stays open, no stamp yet
        foreach ([1, 2] as $n) {
            $done = $this->postJson('/api/v2/pharmacy/prescriptions/dispense-in-person', ['id' => $id, 'content' => $copy['content']])->assertOk()->json('data');
            $this->assertFalse($done['final']);
            $this->assertSame($n, $done['dispense_count']);
            $this->assertSame('issued', Prescription::query()->findOrFail($id)->status);
        }

        $check = $this->postJson('/api/v2/pharmacy/prescriptions/verify', ['id' => $id, 'content' => $copy['content']])->json('data');
        $this->assertTrue($check['can_dispense']);
        $this->assertTrue($check['final_dispense'], 'the next one is the last: the paper is stamped then');

        // the last filling makes it dispensed; nothing more can be taken
        $last = $this->postJson('/api/v2/pharmacy/prescriptions/dispense-in-person', ['id' => $id, 'content' => $copy['content']])->assertOk()->json('data');
        $this->assertTrue($last['final']);
        $this->assertSame('dispensed', $last['status']);
        $this->postJson('/api/v2/pharmacy/prescriptions/dispense-in-person', ['id' => $id, 'content' => $copy['content']])->assertUnprocessable();
    }

    public function test_an_ordinary_prescription_is_filled_once_whatever_the_number_asked(): void
    {
        Sanctum::actingAs($this->doctor);
        $body = $this->body([$this->plain]);
        $body['dispense_limit'] = 5;
        $id = $this->postJson('/api/v2/prescriptions', $body)->assertCreated()->assertJsonPath('data.prescription.dispense_limit', 1)->json('data.prescription.id');

        $this->assertSame(1, Prescription::query()->findOrFail($id)->dispense_limit);
    }
}
