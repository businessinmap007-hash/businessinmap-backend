<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ConductViolation;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\DisputeFee;
use App\Models\Thread;
use App\Models\ThreadMessage;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ArbitrationService;
use App\Services\BookingDepositService;
use App\Services\DisputeService;
use App\Services\ThreadService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Both parties agreeing to delete a finished dispute's conversation.
 *
 * The standing rule is that dispute records are never deleted. This is the one
 * consented exception, so the tests press on the two things that keep it safe:
 * that it needs BOTH parties and a closed case, and that only the conversation
 * goes — the dispute number, the parties and the ruling stay on record.
 */
class DisputeRoomPurgeTest extends TestCase
{
    use DatabaseTransactions;

    private ArbitrationService $arbitration;
    private DisputeService $disputes;
    private ThreadService $threads;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->arbitration = app(ArbitrationService::class);
        $this->disputes = app(DisputeService::class);
        $this->threads = app(ThreadService::class);

        DisputeFee::query()->updateOrCreate(['platform_service_id' => null], ['amount' => 0, 'is_active' => true]);

        $booking = Booking::withTrashed()
            ->whereNotNull('user_id')->whereNotNull('business_id')
            ->whereColumn('user_id', '!=', 'business_id')
            ->first();

        if ($booking && $booking->trashed()) {
            $booking->restore();
        }

        if (! $booking || ! $booking->user || ! $booking->business) {
            $this->markTestSkipped('Needs a booking with a client and a business.');
        }

        $this->booking = $booking;

        Deposit::query()->where('target_type', Booking::class)->where('target_id', $booking->id)->delete();
        Dispute::query()->where('disputeable_type', Booking::class)->where('disputeable_id', $booking->id)->delete();

        foreach ([(int) $booking->user_id, (int) $booking->business_id] as $userId) {
            app(WalletService::class)->getOrCreateWallet($userId)->update([
                'status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0,
            ]);
        }

        app(BookingDepositService::class)->freezeForBooking($booking, 100.0, [
            'wallet_hold_amount' => 100.0,
            'business_counter_hold_amount' => 0.0,
            'amount' => 100.0,
        ]);
    }

    private function makeAdmin(): User
    {
        $admin = new User();
        $admin->name = 'Purge Test Admin';
        $admin->email = 'purge-' . uniqid() . '@example.test';
        $admin->phone = '0155' . random_int(1000000, 9999999);
        $admin->password = 'secret-password';
        $admin->type = User::TYPE_ADMIN;
        $admin->api_token = Str::random(80);
        $admin->save();

        return $admin;
    }

    /** A dispute closed as complied, ready to have its conversation deleted. */
    private function closedDispute(): Dispute
    {
        $admin = $this->makeAdmin();
        $dispute = app(BookingDepositService::class)
            ->openDisputeForBooking($this->booking, (int) $this->booking->user_id);

        $this->arbitration->acceptSession($dispute, (int) $admin->id);
        $this->disputes->resolve($dispute->fresh(), 'release_business', [], (int) $admin->id);
        $this->arbitration->chargeArbitrationFee($dispute->fresh());

        return $this->disputes->closeWithCompliance($dispute->fresh(), (int) $admin->id);
    }

    private function threadFor(Dispute $dispute): ?Thread
    {
        return Thread::query()
            ->where('subject_type', Dispute::class)
            ->where('subject_id', $dispute->id)
            ->first();
    }

    // ─────────────────────────── the gate ───────────────────────────

    /** One confirmation is a request, not a deletion. */
    public function test_one_party_confirming_does_not_delete_the_room(): void
    {
        $dispute = $this->closedDispute();

        $this->disputes->confirmClosurePurge($dispute, (int) $this->booking->user_id);

        $fresh = $dispute->fresh();
        $this->assertNotNull($fresh->client_purge_confirmed_at);
        $this->assertNull($fresh->room_purged_at, 'it takes both hands');
        $this->assertNotNull($this->threadFor($dispute), 'the conversation is still there');
    }

    /** Both confirming deletes the conversation. */
    public function test_both_parties_confirming_deletes_the_conversation(): void
    {
        $dispute = $this->closedDispute();

        $this->disputes->confirmClosurePurge($dispute, (int) $this->booking->user_id);
        $dispute = $this->disputes->confirmClosurePurge($dispute->fresh(), (int) $this->booking->business_id);

        $this->assertNotNull($dispute->room_purged_at);
        $this->assertTrue($dispute->isRoomPurged());
        $this->assertNull($this->threadFor($dispute), 'the thread is gone');
    }

    /** The conversation must not be deletable while the case is still live. */
    public function test_a_dispute_that_is_not_closed_cannot_be_purged(): void
    {
        $dispute = app(BookingDepositService::class)
            ->openDisputeForBooking($this->booking, (int) $this->booking->user_id);

        $this->expectException(ValidationException::class);
        $this->disputes->confirmClosurePurge($dispute, (int) $this->booking->user_id);
    }

    public function test_a_stranger_cannot_confirm(): void
    {
        $dispute = $this->closedDispute();

        $stranger = User::query()
            ->whereNotIn('id', [(int) $this->booking->user_id, (int) $this->booking->business_id])
            ->orderBy('id')->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->disputes->confirmClosurePurge($dispute, (int) $stranger->id);
    }

    // ─────────────────────── what stays, what goes ───────────────────────

    /** The record survives: number, parties, and the ruling. */
    public function test_the_record_is_kept_when_the_conversation_goes(): void
    {
        $dispute = $this->closedDispute();
        $disputeId = $dispute->id;

        $this->disputes->confirmClosurePurge($dispute, (int) $this->booking->user_id);
        $this->disputes->confirmClosurePurge($dispute->fresh(), (int) $this->booking->business_id);

        $kept = Dispute::find($disputeId);

        $this->assertNotNull($kept, 'the dispute row itself is not deleted');
        $this->assertSame((int) $this->booking->user_id, (int) $kept->opened_by_user_id, 'the parties are kept');
        $this->assertSame((int) $this->booking->business_id, (int) $kept->against_user_id);
        $this->assertSame('release_business', $kept->resolution_type, 'the ruling is kept');

        $this->assertDatabaseHas('arbitration_sessions', [
            'dispute_id' => $disputeId,
            'outcome' => 'release_business',
        ]);
    }

    /** The whole conversation — messages, seats, violations — goes with the thread. */
    public function test_the_conversation_and_its_contents_are_deleted(): void
    {
        $dispute = $this->closedDispute();

        // Something was said, and a violation was recorded, before deletion.
        $thread = $this->threadFor($dispute);
        $threadId = $thread->id;
        $this->threads->recordViolation(
            thread: $thread->fresh('participants'),
            againstUserId: (int) $this->booking->business_id,
            recordedByUserId: (int) $this->makeAdmin()->id,
            reason: 'test'
        );

        $this->assertGreaterThan(0, ThreadMessage::query()->where('thread_id', $threadId)->count());
        $this->assertGreaterThan(0, ConductViolation::query()->where('thread_id', $threadId)->count());

        $this->disputes->confirmClosurePurge($dispute, (int) $this->booking->user_id);
        $this->disputes->confirmClosurePurge($dispute->fresh(), (int) $this->booking->business_id);

        $this->assertSame(0, ThreadMessage::query()->where('thread_id', $threadId)->count());
        $this->assertSame(0, ConductViolation::query()->where('thread_id', $threadId)->count());
    }

    /** A purged room must never be silently recreated. */
    public function test_a_purged_room_is_not_rebuilt_on_the_next_request(): void
    {
        $dispute = $this->closedDispute();

        $this->disputes->confirmClosurePurge($dispute, (int) $this->booking->user_id);
        $this->disputes->confirmClosurePurge($dispute->fresh(), (int) $this->booking->business_id);

        // Asking for the room again must not make a fresh empty one.
        $this->expectException(ValidationException::class);
        $this->disputes->room($dispute->fresh());
    }

    /** Confirming after it is already gone is harmless. */
    public function test_confirming_after_purge_is_a_noop(): void
    {
        $dispute = $this->closedDispute();

        $this->disputes->confirmClosurePurge($dispute, (int) $this->booking->user_id);
        $this->disputes->confirmClosurePurge($dispute->fresh(), (int) $this->booking->business_id);

        $purgedAt = $dispute->fresh()->room_purged_at;

        $again = $this->disputes->confirmClosurePurge($dispute->fresh(), (int) $this->booking->user_id);

        $this->assertEquals($purgedAt, $again->room_purged_at, 'the deletion time does not move');
    }

    // ─────────────────────────── the API ───────────────────────────

    public function test_the_flow_works_over_the_api(): void
    {
        $dispute = $this->closedDispute();

        Sanctum::actingAs($this->booking->user);
        $this->postJson("/api/v2/disputes/{$dispute->id}/closure-confirmation")
            ->assertOk()
            ->assertJsonPath('data.purge.purged_at', null);

        Sanctum::actingAs($this->booking->business);
        $this->postJson("/api/v2/disputes/{$dispute->id}/closure-confirmation")
            ->assertOk();

        $this->assertNotNull($dispute->fresh()->room_purged_at);

        // The room endpoint reports it as purged rather than rebuilding it.
        $this->getJson("/api/v2/disputes/{$dispute->id}/room")
            ->assertOk()
            ->assertJsonPath('meta.thread.purged', true);
    }

    public function test_a_stranger_cannot_confirm_over_the_api(): void
    {
        $dispute = $this->closedDispute();

        $stranger = User::query()
            ->whereNotIn('id', [(int) $this->booking->user_id, (int) $this->booking->business_id])
            ->orderBy('id')->firstOrFail();

        Sanctum::actingAs($stranger);
        $this->postJson("/api/v2/disputes/{$dispute->id}/closure-confirmation")->assertNotFound();
    }
}
