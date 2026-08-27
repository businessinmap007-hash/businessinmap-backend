<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * Read-only AdminV2 oversight of prescriptions — an admin can browse and open
 * one to see the invoice, the pharmacy/delivery status, co-doctor shares and
 * attached scans, but nothing here can edit a prescription.
 */
class AdminPrescriptionOversightTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::query()->where('type', 'admin')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs an admin user.');
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

    private function aPrescription(): Prescription
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic', 514);
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');

        Sanctum::actingAs($doctor);
        $id = (int) $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'diagnosis' => 'Seasonal flu',
            'items' => [['medicine_id' => $this->medicine('Paracetamol')->id, 'dosage' => '500mg']],
        ])->assertCreated()->json('data.prescription.id');

        return Prescription::query()->findOrFail($id);
    }

    public function test_the_index_lists_prescriptions_across_every_clinic(): void
    {
        $prescription = $this->aPrescription();

        $this->actingAs($this->admin())
            ->get('/admin/prescriptions')
            ->assertOk()
            ->assertSee((string) $prescription->id, false)
            ->assertSee(optional($prescription->doctor)->name ?? $prescription->doctor->name, false);
    }

    public function test_the_show_screen_renders_diagnosis_and_items(): void
    {
        $prescription = $this->aPrescription();

        $this->actingAs($this->admin())
            ->get('/admin/prescriptions/' . $prescription->id)
            ->assertOk()
            ->assertSee('Seasonal flu', false)
            ->assertSee('Paracetamol', false);
    }

    public function test_a_status_filter_narrows_the_list(): void
    {
        $prescription = $this->aPrescription();

        $this->actingAs($this->admin())
            ->get('/admin/prescriptions?status=' . Prescription::STATUS_ISSUED)
            ->assertOk()
            ->assertSee((string) $prescription->id, false);

        $this->actingAs($this->admin())
            ->get('/admin/prescriptions?status=' . Prescription::STATUS_DISPENSED)
            ->assertOk()
            ->assertDontSee('Paracetamol', false);
    }

    /** Read-only oversight still sits behind an ability — nobody wanders in by URL alone. */
    public function test_the_screen_requires_the_operations_ability(): void
    {
        $admin = new User();
        $admin->name = 'Prescription Oversight Ability Test';
        $admin->email = 'prescr-admin-' . uniqid() . '@example.test';
        $admin->phone = '0155' . random_int(1000000, 9999999);
        $admin->password = 'secret-password';
        $admin->type = User::TYPE_ADMIN;
        $admin->api_token = Str::random(80);
        $admin->save();

        // Into the panel, but without the operations ability.
        Bouncer::allow($admin)->to(AdminAbility::ACCESS);
        Bouncer::refresh();

        $this->actingAs($admin)->get('/admin/prescriptions')->assertForbidden();
    }
}
