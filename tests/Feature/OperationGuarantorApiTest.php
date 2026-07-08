<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\GuaranteeLevel;
use App\Models\OperationGuarantor;
use App\Models\User;
use App\Models\UserGuarantee;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Customer co-guarantor API: only the booking's client may invite, and only the
 * invited friend may accept/decline. Accepting freezes the friend's coverage.
 */
class OperationGuarantorApiTest extends TestCase
{
    use DatabaseTransactions;

    private Booking $booking;

    private User $client;

    private User $friend;

    protected function setUp(): void
    {
        parent::setUp();

        $booking = Booking::query()
            ->whereNotNull('user_id')->whereNotNull('business_id')
            ->whereColumn('user_id', '!=', 'business_id')->first();

        $levelId = (int) DB::table('guarantee_levels')->value('id');
        $client = $booking?->user;
        $friend = $client
            ? User::query()->whereNotIn('id', [(int) $booking->user_id, (int) $booking->business_id])->first()
            : null;

        if (! $booking || ! $client || ! $friend || $levelId <= 0) {
            $this->markTestSkipped('Needs a booking, client, distinct friend, and a guarantee level.');
        }

        $this->booking = $booking;
        $this->client = $client;
        $this->friend = $friend;

        UserGuarantee::query()->where('user_id', $friend->id)->where('target_type', 'client')->delete();
        UserGuarantee::create([
            'user_id' => $friend->id, 'target_type' => GuaranteeLevel::TARGET_CLIENT,
            'purchased_level_id' => $levelId, 'effective_level_id' => $levelId,
            'status' => UserGuarantee::STATUS_ACTIVE, 'current_coverage_amount' => 500, 'used_coverage_amount' => 0,
        ]);
        OperationGuarantor::query()->forOperation('booking', (int) $booking->id)->delete();
    }

    public function test_client_can_invite_a_friend(): void
    {
        Sanctum::actingAs($this->client);

        $this->postJson("/api/v2/bookings/{$this->booking->id}/guarantors", [
            'guarantor_user_id' => $this->friend->id,
        ])->assertCreated()->assertJsonPath('data.guarantor.status', OperationGuarantor::STATUS_INVITED);
    }

    public function test_non_client_cannot_invite(): void
    {
        Sanctum::actingAs($this->friend);

        $this->postJson("/api/v2/bookings/{$this->booking->id}/guarantors", [
            'guarantor_user_id' => $this->friend->id,
        ])->assertForbidden();
    }

    public function test_invited_friend_can_accept_and_freeze_coverage(): void
    {
        Sanctum::actingAs($this->client);
        $invite = $this->postJson("/api/v2/bookings/{$this->booking->id}/guarantors", [
            'guarantor_user_id' => $this->friend->id,
        ])->assertCreated();
        $guarantorId = (int) $invite->json('data.guarantor.id');

        Sanctum::actingAs($this->friend);
        $this->postJson("/api/v2/guarantors/{$guarantorId}/accept", ['amount' => 200])
            ->assertOk()
            ->assertJsonPath('data.guarantor.status', OperationGuarantor::STATUS_ACCEPTED);

        $g = UserGuarantee::query()->where('user_id', $this->friend->id)->where('target_type', 'client')->first();
        $this->assertEqualsWithDelta(200.0, (float) $g->used_coverage_amount, 0.001);
    }

    public function test_only_the_invited_friend_can_accept(): void
    {
        Sanctum::actingAs($this->client);
        $invite = $this->postJson("/api/v2/bookings/{$this->booking->id}/guarantors", [
            'guarantor_user_id' => $this->friend->id,
        ])->assertCreated();
        $guarantorId = (int) $invite->json('data.guarantor.id');

        // The client (not the invited friend) must not be able to accept.
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/guarantors/{$guarantorId}/accept", ['amount' => 200])->assertForbidden();
    }
}
