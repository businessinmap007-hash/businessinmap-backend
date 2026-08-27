<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A scan of the original paper prescription (or a doctor's supporting note) —
 * {@see \App\Models\Concerns\HasOwnedImages}. Only the doctor who wrote it or
 * the patient it is for may attach one; the pharmacy and a shared-in second
 * doctor may see it but never add or remove one.
 */
class PrescriptionImageTest extends TestCase
{
    use DatabaseTransactions;

    private const A_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    /** @var array<int,string> */
    private array $writtenPaths = [];

    protected function tearDown(): void
    {
        // The DB rolls back but the filesystem does not — clean up what we wrote.
        foreach ($this->writtenPaths as $path) {
            File::delete(public_path('files/uploads/' . basename($path)));
        }

        parent::tearDown();
    }

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

    private function aPng(string $name = 'scan.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::A_PNG));
    }

    private function issuedPrescription(User $doctor, User $patient): Prescription
    {
        Sanctum::actingAs($doctor);
        $id = (int) $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'items' => [['medicine_id' => $this->medicine('Paracetamol')->id]],
        ])->assertCreated()->json('data.prescription.id');

        return Prescription::query()->findOrFail($id);
    }

    public function test_the_patient_attaches_a_scan_and_it_appears_in_the_serialized_prescription(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $prescription = $this->issuedPrescription($doctor, $patient);

        Sanctum::actingAs($patient);
        $res = $this->post("/api/v2/prescriptions/{$prescription->id}/images", [
            'image' => $this->aPng(),
        ])->assertCreated();

        $this->writtenPaths[] = $res->json('data.image.image');
        $this->assertNotEmpty($res->json('data.image.image'));

        $shown = $this->getJson("/api/v2/prescriptions/{$prescription->id}")->assertOk();
        $this->assertCount(1, $shown->json('data.prescription.images'));
    }

    public function test_the_doctor_who_wrote_it_can_also_attach_one(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $prescription = $this->issuedPrescription($doctor, $patient);

        Sanctum::actingAs($doctor);
        $res = $this->post("/api/v2/prescriptions/{$prescription->id}/images", [
            'image' => $this->aPng(),
            'source' => Image::SOURCE_CAMERA,
        ])->assertCreated();

        $this->writtenPaths[] = $res->json('data.image.image');
    }

    public function test_the_pharmacy_can_see_it_but_cannot_attach_one(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy', 215);
        $prescription = $this->issuedPrescription($doctor, $patient);

        Sanctum::actingAs($patient);
        $res = $this->post("/api/v2/prescriptions/{$prescription->id}/images", ['image' => $this->aPng()])->assertCreated();
        $this->writtenPaths[] = $res->json('data.image.image');

        Sanctum::actingAs($patient);
        $this->postJson("/api/v2/prescriptions/{$prescription->id}/send", [
            'pharmacy_id' => $pharmacy->id,
            'fulfillment_type' => Prescription::FULFILLMENT_PICKUP,
        ])->assertOk();

        // The pharmacy sees the scan in its own queue view.
        Sanctum::actingAs($pharmacy);
        $this->getJson('/api/v2/pharmacy/prescriptions')
            ->assertOk()
            ->assertJsonCount(1, 'data.data.0.images');

        // But cannot attach one of its own.
        $this->post("/api/v2/prescriptions/{$prescription->id}/images", ['image' => $this->aPng()])->assertNotFound();
    }

    public function test_a_stranger_cannot_attach_or_read(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $prescription = $this->issuedPrescription($doctor, $patient);

        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'Stranger'));
        $this->post("/api/v2/prescriptions/{$prescription->id}/images", ['image' => $this->aPng()])->assertNotFound();
        $this->getJson("/api/v2/prescriptions/{$prescription->id}")->assertNotFound();
    }

    public function test_the_uploader_can_delete_their_own_photo(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $prescription = $this->issuedPrescription($doctor, $patient);

        Sanctum::actingAs($patient);
        $imageId = $this->post("/api/v2/prescriptions/{$prescription->id}/images", ['image' => $this->aPng()])
            ->assertCreated()->json('data.image.id');

        $this->deleteJson("/api/v2/prescriptions/{$prescription->id}/images/{$imageId}")->assertOk();

        $this->assertSame(0, $prescription->fresh()->images()->count());
    }

    public function test_the_gallery_is_capped(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $prescription = $this->issuedPrescription($doctor, $patient);

        Sanctum::actingAs($patient);
        for ($i = 0; $i < Prescription::MAX_IMAGES; $i++) {
            $res = $this->post("/api/v2/prescriptions/{$prescription->id}/images", ['image' => $this->aPng("s{$i}.png")])
                ->assertCreated();
            $this->writtenPaths[] = $res->json('data.image.image');
        }

        $this->post("/api/v2/prescriptions/{$prescription->id}/images", ['image' => $this->aPng('one_too_many.png')])
            ->assertStatus(422);
    }
}
