<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «التاريخ المرضى يحفظ على الفون وعند مشاركته يقرأ ويعرض للطبيب — بأمان ومشفر» — المالك، 2026-10-05.
 * The server relays ciphertext for a few minutes and nothing else. Rolls back.
 */
class MedicalShareTest extends TestCase
{
    use DatabaseTransactions;

    private User $patient;
    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->patient = User::query()->where('type', '!=', 'business')->orderBy('id')->firstOrFail();
        $this->doctor = User::query()->where('type', 'business')->orderBy('id')->firstOrFail();
    }

    public function test_the_patient_leaves_only_ciphertext_and_the_doctor_reads_it_back(): void
    {
        Sanctum::actingAs($this->patient);
        $cipher = base64_encode(random_bytes(200));
        $share = $this->postJson('/api/v2/medical-shares', ['ciphertext' => $cipher])->assertCreated()->json('data');

        $stored = DB::table('medical_shares')->where('uuid', $share['id'])->first();
        $this->assertSame($cipher, $stored->ciphertext, 'stored as sent: the server has no key to open it');
        $this->assertSame(['id', 'uuid', 'user_id', 'ciphertext', 'expires_at', 'reads', 'created_at', 'updated_at'], array_keys((array) $stored), 'no plaintext column');

        Sanctum::actingAs($this->doctor);
        $read = $this->getJson('/api/v2/medical-shares/' . $share['id'])->assertOk()->json('data');
        $this->assertSame($cipher, $read['ciphertext']);
        $this->assertSame($this->patient->name, $read['shared_by']);
    }

    public function test_a_share_ends_on_time_or_when_the_patient_ends_it(): void
    {
        Sanctum::actingAs($this->patient);
        $id = $this->postJson('/api/v2/medical-shares', ['ciphertext' => 'QUJD', 'minutes' => 5])->assertCreated()->json('data.id');

        $this->travel(6)->minutes();
        Sanctum::actingAs($this->doctor);
        $this->getJson('/api/v2/medical-shares/' . $id)->assertNotFound();
        $this->travelBack();

        Sanctum::actingAs($this->patient);
        $live = $this->postJson('/api/v2/medical-shares', ['ciphertext' => 'QUJD'])->assertCreated()->json('data.id');
        Sanctum::actingAs($this->doctor);
        $this->deleteJson('/api/v2/medical-shares/' . $live)->assertOk();
        // Only the patient can end his share.
        $this->getJson('/api/v2/medical-shares/' . $live)->assertOk();
        Sanctum::actingAs($this->patient);
        $this->deleteJson('/api/v2/medical-shares/' . $live)->assertOk();
        Sanctum::actingAs($this->doctor);
        $this->getJson('/api/v2/medical-shares/' . $live)->assertNotFound();
    }

    public function test_it_is_not_a_place_to_store_anything_else(): void
    {
        Sanctum::actingAs($this->patient);
        $this->postJson('/api/v2/medical-shares', ['ciphertext' => 'not base64 <script>'])->assertUnprocessable();
        $this->postJson('/api/v2/medical-shares', ['ciphertext' => 'QUJD', 'minutes' => 600])->assertUnprocessable();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v2/medical-shares/' . \Illuminate\Support\Str::uuid())->assertUnauthorized();
    }
}
