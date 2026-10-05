<?php

namespace Tests\Feature;

use App\Models\ClinicAppointment;
use App\Models\Image;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Media\ImageUploadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «نسخة الطبيب على الهاتف والكمبيوتر أيضًا» — المالك، 2026-10-05. The doctor's own copy is confirmed to the server
 * (phone or computer), the purge waits for BOTH the patient's and the doctor's copy, and the web panel writes and
 * lists prescriptions through the same doors as the app. Rolls back.
 */
class DoctorPrescriptionCopyTest extends TestCase
{
    use DatabaseTransactions;

    private User $doctor;
    private User $patient;
    private Medicine $plain;
    private Medicine $controlled;
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
        $this->plain = Medicine::create(['name' => 'Paracetamol ' . Str::random(4), 'scientific_name' => 'PARACETAMOL']);
        $this->controlled = Medicine::create(['name' => 'Tramal ' . Str::random(4), 'scientific_name' => 'TRAMADOL', 'is_controlled' => true]);

        Sanctum::actingAs($this->doctor);
        $this->id = (int) $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $this->patient->id, 'diagnosis' => 'Flu', 'notes' => 'Rest',
            'items' => [['medicine_id' => $this->plain->id, 'dosage' => '500mg', 'quantity' => '2 boxes']],
        ])->assertCreated()->json('data.prescription.id');
    }

    protected function tearDown(): void
    {
        foreach (Image::query()->where('purpose', Image::PURPOSE_HANDWRITTEN)->get() as $image) {
            app(ImageUploadService::class)->delete($image->image);
        }

        parent::tearDown();
    }

    private function copy(): array
    {
        Sanctum::actingAs($this->doctor);

        return $this->getJson("/api/v2/prescriptions/{$this->id}")->assertOk()->json('data.prescription.verifiable');
    }

    private function paper(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('paper.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    }

    public function test_the_doctors_device_confirms_its_copy_and_a_different_copy_is_refused(): void
    {
        $copy = $this->copy();

        $changed = $copy['content'];
        $changed['diagnosis'] = 'Something else';
        $this->postJson('/api/v2/prescriptions/issued/archived', ['id' => $this->id, 'content' => $changed])->assertUnprocessable();
        $this->assertNull(Prescription::query()->findOrFail($this->id)->archived_by_doctor_at);

        $this->postJson('/api/v2/prescriptions/issued/archived', ['id' => $this->id, 'content' => $copy['content']])->assertOk();
        $this->assertNotNull(Prescription::query()->findOrFail($this->id)->archived_by_doctor_at);
        $this->getJson("/api/v2/prescriptions/{$this->id}")->assertJsonPath('data.prescription.archived_by_doctor', true);
    }

    public function test_only_the_issuing_doctor_can_confirm_it(): void
    {
        $copy = $this->copy();

        Sanctum::actingAs($this->user(User::TYPE_BUSINESS, 'OtherClinic', 514));
        $this->postJson('/api/v2/prescriptions/issued/archived', ['id' => $this->id, 'content' => $copy['content']])->assertNotFound();

        Sanctum::actingAs($this->patient);
        $this->postJson('/api/v2/prescriptions/issued/archived', ['id' => $this->id, 'content' => $copy['content']])->assertForbidden();
    }

    public function test_the_purge_waits_for_both_copies(): void
    {
        $copy = $this->copy();
        DB::table('prescriptions')->where('id', $this->id)->update(['status' => 'dispensed', 'dispensed_at' => now()->subDays(120), 'updated_at' => now()->subDays(120)]);

        // the patient only
        Sanctum::actingAs($this->patient);
        $this->postJson('/api/v2/prescriptions/archived', ['id' => $this->id, 'content' => $copy['content']])->assertOk();
        Artisan::call('prescriptions:purge-sensitive');
        $this->assertSame('Flu', Prescription::query()->findOrFail($this->id)->diagnosis);

        // and the doctor too
        Sanctum::actingAs($this->doctor);
        $this->postJson('/api/v2/prescriptions/issued/archived', ['id' => $this->id, 'content' => $copy['content']])->assertOk();
        Artisan::call('prescriptions:purge-sensitive');

        $row = Prescription::query()->with('items')->findOrFail($this->id);
        $this->assertNull($row->diagnosis);
        $this->assertSame('500mg', $row->items->first()->dosage);
    }

    public function test_the_web_page_is_for_a_physicians_practice_only(): void
    {
        $this->actingAs($this->doctor)->get('/business/prescriptions')->assertOk()->assertSee('روشتاتي')->assertSee('medSearch', false);

        $shop = $this->user(User::TYPE_BUSINESS, 'Shop', 116);
        $this->actingAs($shop)->get('/business/prescriptions')->assertForbidden();
    }

    public function test_the_web_page_lists_issued_ones_with_their_verifiable_copy(): void
    {
        $rows = $this->actingAs($this->doctor)->getJson('/business/prescriptions/data/issued')->assertOk()->json('data.data');

        $this->assertSame($this->id, $rows[0]['id']);
        $this->assertNotEmpty($rows[0]['verifiable']['hash']);
        $this->assertFalse($rows[0]['archived_by_doctor']);
    }

    public function test_the_web_searches_the_dictionary_by_trade_name_or_active_ingredient(): void
    {
        $this->actingAs($this->doctor);

        $byName = $this->getJson('/business/prescriptions/data/medicines?q=' . urlencode($this->controlled->name))->assertOk()->json('data');
        $this->assertSame($this->controlled->id, $byName[0]['id']);
        $this->assertTrue($byName[0]['is_controlled']);

        $byIngredient = collect($this->getJson('/business/prescriptions/data/medicines?q=TRAMADOL&limit=50')->json('data'));
        $this->assertTrue($byIngredient->contains('id', $this->controlled->id), 'found by the active ingredient');
    }

    public function test_the_web_lists_the_clinics_visits_to_pick_the_patient_from(): void
    {
        ClinicAppointment::query()->create([
            'clinic_id' => $this->doctor->id, 'patient_id' => $this->patient->id,
            'scheduled_at' => now()->addDay(), 'status' => ClinicAppointment::STATUS_CONFIRMED, 'duration_minutes' => 20,
        ]);

        $rows = $this->actingAs($this->doctor)->getJson('/business/prescriptions/data/appointments')->assertOk()->json('data');

        $this->assertSame($this->patient->id, $rows[0]['patient_id']);
        $this->assertSame($this->patient->name, $rows[0]['patient_name']);
    }

    public function test_the_web_writes_a_prescription_with_the_same_rules_as_the_app(): void
    {
        $this->actingAs($this->doctor);
        $body = fn (array $items, array $extra = []) => $extra + ['patient_id' => $this->patient->id, 'items' => json_encode($items)];
        $item = [['medicine_id' => $this->controlled->id, 'dosage' => '1 tab']];

        $this->post('/business/prescriptions', $body($item), ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonPath('code', 'handwritten_required');

        $this->post('/business/prescriptions', $body($item, ['handwritten_image' => $this->paper(), 'handwritten_source' => 'upload']), ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.prescription.controlled', true);

        $this->post('/business/prescriptions', $body([['medicine_id' => $this->plain->id]]), ['Accept' => 'application/json'])->assertCreated();
    }

    public function test_the_web_confirms_its_copy(): void
    {
        $copy = $this->copy();

        $this->actingAs($this->doctor)->postJson('/business/prescriptions/archived', ['id' => $this->id, 'content' => $copy['content']])->assertOk();

        $this->assertNotNull(Prescription::query()->findOrFail($this->id)->archived_by_doctor_at);
    }
}
