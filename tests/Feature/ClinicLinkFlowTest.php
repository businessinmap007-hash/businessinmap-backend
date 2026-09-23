<?php

namespace Tests\Feature;

use App\Models\BusinessWorkingHour;
use App\Models\ClinicLink;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A doctor with more than one clinic account links them together (mutual
 * consent), so a patient who opens either one's public page sees a single
 * unified schedule instead of two disconnected businesses.
 */
class ClinicLinkFlowTest extends TestCase
{
    use DatabaseTransactions;

    private function clinic(string $tag): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_BUSINESS;
        $u->category_child_id = User::DOCTOR_OWN_CLINIC_CHILD_ID;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    private function hours(User $clinic, int $day, string $open, string $close): void
    {
        BusinessWorkingHour::create([
            'business_id' => $clinic->id, 'day_of_week' => $day,
            'is_closed' => false, 'open_time' => $open, 'close_time' => $close,
        ]);
    }

    public function test_a_link_request_and_accept_produces_a_unified_public_schedule(): void
    {
        $damietta = $this->clinic('Damietta');
        $cairo = $this->clinic('Cairo');

        $this->hours($damietta, 6, '09:00', '14:00'); // Saturday
        $this->hours($cairo, 0, '16:00', '21:00');    // Sunday

        Sanctum::actingAs($damietta);
        $linkId = $this->postJson('/api/v2/business/clinic-links', ['target_id' => $cairo->id])
            ->assertCreated()->assertJsonPath('data.link.status', 'pending')
            ->json('data.link.id');

        // Not yet unified — still pending.
        $this->getJson("/api/v2/businesses/{$damietta->id}")->assertOk()
            ->assertJsonPath('data.linked_schedule', null);

        Sanctum::actingAs($cairo);
        $this->postJson("/api/v2/business/clinic-links/{$linkId}/accept")->assertOk()
            ->assertJsonPath('data.link.status', 'accepted');

        // Either clinic's own public page now shows the whole group.
        $res = $this->getJson("/api/v2/businesses/{$damietta->id}")->assertOk();
        $group = $res->json('data.linked_schedule');
        $this->assertCount(2, $group);

        $damiettaEntry = collect($group)->firstWhere('id', $damietta->id);
        $this->assertSame(6, $damiettaEntry['days'][0]['day_of_week']);
        $this->assertSame('09:00', $damiettaEntry['days'][0]['open_time']);

        $cairoEntry = collect($group)->firstWhere('id', $cairo->id);
        $this->assertSame(0, $cairoEntry['days'][0]['day_of_week']);

        $this->getJson("/api/v2/businesses/{$cairo->id}")->assertOk()
            ->assertJsonCount(2, 'data.linked_schedule');
    }

    /** A already requesting B while B already requested A accepts on the spot, no duplicate pending row. */
    public function test_a_mutual_request_auto_accepts_instead_of_duplicating(): void
    {
        $a = $this->clinic('A');
        $b = $this->clinic('B');

        Sanctum::actingAs($a);
        $this->postJson('/api/v2/business/clinic-links', ['target_id' => $b->id])->assertCreated();

        Sanctum::actingAs($b);
        $res = $this->postJson('/api/v2/business/clinic-links', ['target_id' => $a->id])->assertCreated();

        $this->assertSame('accepted', $res->json('data.link.status'));
        $this->assertSame(1, ClinicLink::query()->count());
    }

    public function test_only_a_clinic_child_account_may_link(): void
    {
        $clinic = $this->clinic('Clinic');
        $shop = new User();
        $shop->name = 'Shop';
        $shop->email = 'shop-' . uniqid() . '@example.test';
        $shop->phone = '01' . random_int(100000000, 999999999);
        $shop->password = 'secret-password';
        $shop->type = User::TYPE_BUSINESS;
        $shop->category_child_id = 116;
        $shop->api_token = Str::random(80);
        $shop->save();

        Sanctum::actingAs($clinic);
        $this->postJson('/api/v2/business/clinic-links', ['target_id' => $shop->id])->assertStatus(422);
    }

    public function test_cannot_link_to_self(): void
    {
        $clinic = $this->clinic('Clinic');

        Sanctum::actingAs($clinic);
        $this->postJson('/api/v2/business/clinic-links', ['target_id' => $clinic->id])
            ->assertStatus(422)->assertJsonValidationErrors('target_id');
    }

    public function test_only_the_target_may_accept(): void
    {
        $a = $this->clinic('A');
        $b = $this->clinic('B');
        $stranger = $this->clinic('Stranger');

        Sanctum::actingAs($a);
        $linkId = $this->postJson('/api/v2/business/clinic-links', ['target_id' => $b->id])
            ->json('data.link.id');

        Sanctum::actingAs($stranger);
        $this->postJson("/api/v2/business/clinic-links/{$linkId}/accept")->assertStatus(403);
    }

    public function test_either_party_can_unlink_an_accepted_link(): void
    {
        $a = $this->clinic('A');
        $b = $this->clinic('B');

        Sanctum::actingAs($a);
        $linkId = $this->postJson('/api/v2/business/clinic-links', ['target_id' => $b->id])
            ->json('data.link.id');

        Sanctum::actingAs($b);
        $this->postJson("/api/v2/business/clinic-links/{$linkId}/accept")->assertOk();

        // The REQUESTER (not just the target) can also break the link.
        Sanctum::actingAs($a);
        $this->deleteJson("/api/v2/business/clinic-links/{$linkId}")->assertOk();

        $this->assertDatabaseMissing('clinic_links', ['id' => $linkId]);
        $this->getJson("/api/v2/businesses/{$a->id}")->assertOk()
            ->assertJsonPath('data.linked_schedule', null);
    }
}
