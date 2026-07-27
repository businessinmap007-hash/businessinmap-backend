<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The shared medicine dictionary: doctors build it (by adding drugs and by
 * writing them on prescriptions), and every doctor searches the same list.
 */
class MedicineDictionaryTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type, string $tag): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    public function test_a_doctor_adds_a_drug_and_any_doctor_finds_it(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $name = 'Amoxil' . Str::random(4);

        Sanctum::actingAs($doctor);
        $this->postJson('/api/v2/medicines', ['name' => $name, 'strength' => '500mg'])
            ->assertCreated()
            ->assertJsonPath('data.name', $name)
            ->assertJsonPath('data.strength', '500mg');

        // Another doctor sees it in the typeahead.
        Sanctum::actingAs($this->user(User::TYPE_BUSINESS, 'Other'));
        $this->getJson('/api/v2/medicines?q=' . substr($name, 0, 5))
            ->assertOk()
            ->assertJsonPath('data.0.name', $name);
    }

    public function test_writing_a_prescription_captures_its_drugs(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');
        $patient = $this->user(User::TYPE_CLIENT, 'Patient');
        $name = 'Brufen' . Str::random(4);

        Sanctum::actingAs($doctor);
        $this->postJson('/api/v2/prescriptions', [
            'patient_id' => $patient->id,
            'items' => [['name' => $name, 'dosage' => '400mg']],
        ])->assertCreated();

        // The written drug is now in the shared dictionary.
        $this->assertDatabaseHas('medicines', ['name' => $name, 'strength' => '400mg']);
    }

    public function test_the_same_drug_is_not_duplicated_and_counts_uses(): void
    {
        $doctor = $this->user(User::TYPE_BUSINESS, 'Clinic');

        Medicine::remember('Panadol', '500mg', (int) $doctor->id);
        Medicine::remember('Panadol', '500mg ', (int) $doctor->id); // trailing space normalises

        $rows = Medicine::query()->where('name', 'Panadol')->where('strength', '500mg')->get();
        $this->assertCount(1, $rows);
        $this->assertSame(2, (int) $rows->first()->uses_count);
    }

    public function test_a_client_cannot_add_a_drug(): void
    {
        Sanctum::actingAs($this->user(User::TYPE_CLIENT, 'Patient'));

        $this->postJson('/api/v2/medicines', ['name' => 'Aspirin', 'strength' => '75mg'])
            ->assertStatus(403);
    }
}
