<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «احذف الحقول الحساسة فقط وابقِ الأصناف» — المالك، 2026-10-05. The diagnosis, condition and notes of a finished
 * prescription leave the server — but only after the patient's phone proved it holds the exact copy, and never the
 * medicine lines, the fingerprint, the dates or the money. Rolls back.
 */
class PrescriptionPurgeTest extends TestCase
{
    use DatabaseTransactions;

    private User $doctor;
    private User $patient;
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

        Sanctum::actingAs($this->doctor);
        $a = Medicine::create(['name' => 'Paracetamol ' . Str::random(4)]);
        $this->id = (int) $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $this->patient->id, 'diagnosis' => 'Flu', 'notes' => 'Rest',
            'items' => [['medicine_id' => $a->id, 'dosage' => '500mg', 'quantity' => '2 boxes']],
        ])->assertCreated()->json('data.prescription.id');
    }

    private function copy(): array
    {
        Sanctum::actingAs($this->patient);

        return $this->getJson("/api/v2/prescriptions/{$this->id}")->assertOk()->json('data.prescription.verifiable');
    }

    /** Finish it (dispensed) a given number of days ago. */
    private function finish(int $daysAgo, string $status = Prescription::STATUS_DISPENSED, bool $doctorHolds = true): void
    {
        $when = now()->subDays($daysAgo);
        DB::table('prescriptions')->where('id', $this->id)->update(['status' => $status, 'dispensed_at' => $when, 'updated_at' => $when, 'archived_by_doctor_at' => $doctorHolds ? now() : null]);
    }

    private function row(): Prescription
    {
        return Prescription::query()->with('items')->findOrFail($this->id);
    }

    public function test_the_phone_confirms_its_copy_and_a_different_copy_is_refused(): void
    {
        $copy = $this->copy();

        $changed = $copy['content'];
        $changed['diagnosis'] = 'Something else';
        $this->postJson('/api/v2/prescriptions/archived', ['id' => $this->id, 'content' => $changed])->assertUnprocessable();
        $this->assertNull($this->row()->archived_by_patient_at);

        $this->postJson('/api/v2/prescriptions/archived', ['id' => $this->id, 'content' => $copy['content']])->assertOk()->assertJsonPath('data.archived', true);
        $this->assertNotNull($this->row()->archived_by_patient_at);
        $this->getJson("/api/v2/prescriptions/{$this->id}")->assertJsonPath('data.prescription.archived_by_patient', true);
    }

    public function test_only_the_patient_can_confirm_it(): void
    {
        $copy = $this->copy();

        Sanctum::actingAs($this->doctor);
        $this->postJson('/api/v2/prescriptions/archived', ['id' => $this->id, 'content' => $copy['content']])->assertNotFound();

        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'Stranger'));
        $this->postJson('/api/v2/prescriptions/archived', ['id' => $this->id, 'content' => $copy['content']])->assertNotFound();
    }

    public function test_it_purges_only_the_sensitive_fields_and_keeps_the_items_and_the_fingerprint(): void
    {
        $copy = $this->copy();
        $this->postJson('/api/v2/prescriptions/archived', ['id' => $this->id, 'content' => $copy['content']])->assertOk();
        $this->finish(120);
        $hash = $this->row()->content_hash;

        Artisan::call('prescriptions:purge-sensitive');

        $row = $this->row();
        $this->assertNull($row->diagnosis);
        $this->assertNull($row->patient_condition);
        $this->assertNull($row->notes);
        $this->assertNotNull($row->content_purged_at);
        $this->assertSame($hash, $row->content_hash);
        $this->assertSame('500mg', $row->items->first()->dosage, 'the items stay');
        $this->assertSame('dispensed', $row->status);
        $this->assertSame(now()->subDays(120)->toDateString(), $row->dispensed_at->toDateString(), 'the dates stay');
    }

    public function test_the_phones_copy_still_proves_authentic_after_the_purge(): void
    {
        $copy = $this->copy();
        $this->postJson('/api/v2/prescriptions/archived', ['id' => $this->id, 'content' => $copy['content']])->assertOk();
        $this->finish(120);
        Artisan::call('prescriptions:purge-sensitive');

        $pharmacy = $this->user(User::TYPE_BUSINESS, 'Pharmacy');
        Sanctum::actingAs($pharmacy);
        $this->postJson('/api/v2/pharmacy/prescriptions/verify', ['id' => $this->id, 'content' => $copy['content']])
            ->assertOk()->assertJsonPath('data.authentic', true)->assertJsonPath('data.can_dispense', false);
    }

    public function test_the_server_stops_handing_back_content_it_no_longer_holds(): void
    {
        $copy = $this->copy();
        $this->postJson('/api/v2/prescriptions/archived', ['id' => $this->id, 'content' => $copy['content']])->assertOk();
        $this->finish(120);
        Artisan::call('prescriptions:purge-sensitive');

        $this->getJson("/api/v2/prescriptions/{$this->id}")->assertOk()
            ->assertJsonPath('data.prescription.content_purged', true)
            ->assertJsonPath('data.prescription.verifiable', null)
            ->assertJsonPath('data.prescription.diagnosis', null)
            ->assertJsonPath('data.prescription.items.0.dosage', '500mg');
    }

    public function test_nothing_is_purged_without_the_phones_confirmation(): void
    {
        $this->finish(400);

        Artisan::call('prescriptions:purge-sensitive');

        $row = $this->row();
        $this->assertSame('Flu', $row->diagnosis);
        $this->assertNull($row->content_purged_at);
    }

    public function test_nothing_is_purged_until_the_doctors_phone_holds_a_copy_too(): void
    {
        $copy = $this->copy();
        $this->postJson('/api/v2/prescriptions/archived', ['id' => $this->id, 'content' => $copy['content']])->assertOk();
        $this->finish(400, Prescription::STATUS_DISPENSED, doctorHolds: false);

        Artisan::call('prescriptions:purge-sensitive');

        $row = $this->row();
        $this->assertSame('Flu', $row->diagnosis);
        $this->assertNull($row->content_purged_at);
    }

    public function test_nothing_is_purged_before_the_window_or_while_it_is_still_open(): void
    {
        $copy = $this->copy();
        $this->postJson('/api/v2/prescriptions/archived', ['id' => $this->id, 'content' => $copy['content']])->assertOk();

        // still issued (not finished), even though the phone holds it
        Artisan::call('prescriptions:purge-sensitive', ['--days' => 1]);
        $this->assertSame('Flu', $this->row()->diagnosis);

        // finished, but only 10 days ago
        $this->finish(10);
        Artisan::call('prescriptions:purge-sensitive');
        $this->assertSame('Flu', $this->row()->diagnosis);

        // a shorter window reaches it
        Artisan::call('prescriptions:purge-sensitive', ['--days' => 5]);
        $this->assertNull($this->row()->diagnosis);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $copy = $this->copy();
        $this->postJson('/api/v2/prescriptions/archived', ['id' => $this->id, 'content' => $copy['content']])->assertOk();
        $this->finish(120);

        Artisan::call('prescriptions:purge-sensitive', ['--dry-run' => true]);

        $this->assertStringContainsString('Would purge', Artisan::output());
        $row = $this->row();
        $this->assertSame('Flu', $row->diagnosis);
        $this->assertNull($row->content_purged_at);
    }

    public function test_a_cancelled_one_the_phone_holds_is_purged_too(): void
    {
        $copy = $this->copy();
        $this->postJson('/api/v2/prescriptions/archived', ['id' => $this->id, 'content' => $copy['content']])->assertOk();
        DB::table('prescriptions')->where('id', $this->id)->update(['status' => 'cancelled', 'updated_at' => now()->subDays(100), 'archived_by_doctor_at' => now()]);

        Artisan::call('prescriptions:purge-sensitive');

        $this->assertNull($this->row()->diagnosis);
    }
}
