<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\Thread;
use App\Models\ThreadMessage;
use App\Models\ThreadParticipant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BookingDepositService;
use App\Services\DisputeService;
use App\Services\ThreadService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The arbitration room: a conversation on a dispute that can hold a third
 * party.
 *
 * The legacy `conversations` table is strictly 1-to-1 (user_one_id/user_two_id)
 * and cannot seat an arbitrator at all, which is why `threads` exists. These
 * tests press on the two things that make the room worth having: that the seat
 * list is what governs access, and that a ruling seals the record.
 */
class DisputeRoomTest extends TestCase
{
    use DatabaseTransactions;

    private Booking $booking;
    private User $client;
    private User $business;
    private DisputeService $disputes;
    private ThreadService $threads;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disputes = app(DisputeService::class);
        $this->threads = app(ThreadService::class);

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
        $this->client = $booking->user;
        $this->business = $booking->business;

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

    private function open(): Dispute
    {
        return app(BookingDepositService::class)
            ->openDisputeForBooking($this->booking, (int) $this->booking->user_id);
    }

    private function stranger(): User
    {
        return User::query()
            ->whereNotIn('id', [(int) $this->booking->user_id, (int) $this->booking->business_id])
            ->orderBy('id')
            ->firstOrFail();
    }

    // ─────────────────────── the room opens with the dispute ───────────────────────

    /** The settlement window asks two parties to agree — so it needs a room. */
    public function test_opening_a_dispute_opens_a_room_with_both_parties(): void
    {
        $dispute = $this->open();

        $thread = Thread::query()
            ->where('subject_type', Dispute::class)
            ->where('subject_id', $dispute->id)
            ->firstOrFail();

        $seats = $thread->participants()->pluck('role', 'user_id')->all();

        $this->assertSame(ThreadParticipant::ROLE_CLIENT, $seats[(int) $this->booking->user_id] ?? null);
        $this->assertSame(ThreadParticipant::ROLE_BUSINESS, $seats[(int) $this->booking->business_id] ?? null);
        $this->assertSame(Thread::STATUS_OPEN, $thread->status);
    }

    /** The room is not empty on arrival: it says why it exists. */
    public function test_the_room_opens_with_a_system_message(): void
    {
        $thread = $this->disputes->room($this->open());

        $first = $thread->messages()->orderBy('id')->first();

        $this->assertNotNull($first);
        $this->assertSame(ThreadMessage::KIND_SYSTEM, $first->kind);
        $this->assertNull($first->sender_id, 'a system message comes from nobody');
    }

    /** Asking for the room twice must not open a second one, or repeat itself. */
    public function test_the_room_is_created_once(): void
    {
        $dispute = $this->open();

        $first = $this->disputes->room($dispute);
        $second = $this->disputes->room($dispute);

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame(1, $second->messages()->where('kind', ThreadMessage::KIND_SYSTEM)->count());
    }

    // ─────────────────────────── the arbitrator ───────────────────────────

    /** The whole point of the participant list. */
    public function test_an_arbitrator_can_take_a_third_seat(): void
    {
        $dispute = $this->open();
        $arbitrator = $this->stranger();

        $thread = $this->disputes->joinAsArbitrator($dispute, (int) $arbitrator->id);

        $this->assertSame(3, $thread->participants()->count());
        $this->assertSame(
            ThreadParticipant::ROLE_ARBITRATOR,
            $thread->participants()->where('user_id', $arbitrator->id)->value('role')
        );
    }

    /** Nobody judges a case they are a party to. */
    public function test_a_party_cannot_become_the_arbitrator(): void
    {
        $dispute = $this->open();

        $this->expectException(ValidationException::class);
        $this->disputes->joinAsArbitrator($dispute, (int) $this->booking->user_id);
    }

    /** Joining twice is one seat and one announcement. */
    public function test_joining_twice_seats_the_arbitrator_once(): void
    {
        $dispute = $this->open();
        $arbitrator = $this->stranger();

        $this->disputes->joinAsArbitrator($dispute, (int) $arbitrator->id);
        $thread = $this->disputes->joinAsArbitrator($dispute, (int) $arbitrator->id);

        $this->assertSame(3, $thread->participants()->count());

        // Two system messages, not three: the room opening and one join.
        $this->assertSame(2, $thread->messages()->where('kind', ThreadMessage::KIND_SYSTEM)->count());
    }

    /** An arbitrator may speak in the room they were seated in. */
    public function test_the_arbitrator_can_post(): void
    {
        $dispute = $this->open();
        $arbitrator = $this->stranger();

        $thread = $this->disputes->joinAsArbitrator($dispute, (int) $arbitrator->id);
        $message = $this->threads->post($thread, (int) $arbitrator->id, 'Please send the booking confirmation.');

        $this->assertSame((int) $arbitrator->id, (int) $message->sender_id);
    }

    // ─────────────────────────── the lifecycle narrates itself ───────────────────────────

    public function test_escalation_is_recorded_in_the_room(): void
    {
        $dispute = $this->open();
        $dispute->update(['mutual_resolution_deadline_at' => now()->subDays(30)]);

        $before = $this->disputes->room($dispute)->messages()->count();

        $this->disputes->escalateExpired();

        $this->assertGreaterThan($before, $this->disputes->room($dispute)->messages()->count());
    }

    /** The ruling is announced, and only then is the room sealed. */
    public function test_a_ruling_announces_itself_and_locks_the_room(): void
    {
        $dispute = $this->open();

        $this->disputes->resolve($dispute, 'split', ['client_percent' => 60, 'business_percent' => 40]);

        $thread = $this->disputes->room($dispute->fresh());
        $last = $thread->messages()->orderByDesc('id')->first();

        $this->assertTrue($thread->isLocked());
        $this->assertSame(ThreadMessage::KIND_SYSTEM, $last->kind);
        $this->assertStringContainsString('60', $last->body, 'the ruling states how it was divided');
    }

    /** Once sealed, the record cannot be added to — it is evidence. */
    public function test_nobody_can_post_after_a_ruling(): void
    {
        $dispute = $this->open();
        $this->disputes->resolve($dispute, 'refund_client');

        $thread = $this->disputes->room($dispute->fresh());

        $this->expectException(ValidationException::class);
        $this->threads->post($thread, (int) $this->booking->user_id, 'one more thing');
    }

    // ─────────────────────────── the API ───────────────────────────

    public function test_a_party_can_read_and_post_through_the_api(): void
    {
        Sanctum::actingAs($this->client);

        $id = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'quality'])
            ->json('data.id');

        $this->postJson("/api/v2/disputes/{$id}/room/conduct")->assertOk();

        $this->postJson("/api/v2/disputes/{$id}/room/messages", ['body' => 'The room was not as advertised.'])
            ->assertCreated()
            ->assertJsonPath('data.is_mine', true);

        Sanctum::actingAs($this->business);

        $response = $this->getJson("/api/v2/disputes/{$id}/room")->assertOk();

        $this->assertSame(false, $response->json('data.0.is_mine'), 'the other party did not write it');
        $this->assertFalse($response->json('meta.thread.locked'));
        $this->assertCount(2, $response->json('meta.thread.participants'));
    }

    public function test_a_stranger_can_neither_read_nor_post(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'quality'])
            ->json('data.id');

        Sanctum::actingAs($this->stranger());

        // 404, not 403: a stranger must not learn the dispute exists.
        $this->getJson("/api/v2/disputes/{$id}/room")->assertNotFound();
        $this->postJson("/api/v2/disputes/{$id}/room/messages", ['body' => 'hello'])->assertNotFound();
    }

    public function test_posting_to_a_sealed_room_is_refused_through_the_api(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'quality'])
            ->json('data.id');

        $this->disputes->resolve(Dispute::findOrFail($id), 'refund_client');

        $this->postJson("/api/v2/disputes/{$id}/room/messages", ['body' => 'one more thing'])
            ->assertStatus(422);
    }

    public function test_an_empty_message_is_refused(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'quality'])
            ->json('data.id');

        $this->postJson("/api/v2/disputes/{$id}/room/messages", ['body' => '   '])
            ->assertStatus(422);
    }

    // ─────────────────────── the arbitrator's actual way in ───────────────────────

    private function admin(): User
    {
        $admin = new User();
        $admin->name = 'Dispute Room Arbitrator';
        $admin->email = 'arbitrator-' . uniqid() . '@example.test';
        $admin->phone = '0155' . random_int(1000000, 9999999);
        $admin->password = 'secret-password';
        $admin->type = User::TYPE_ADMIN;
        $admin->api_token = \Illuminate\Support\Str::random(80);
        $admin->save();

        \Bouncer::allow($admin)->to(\App\Support\AdminAbility::ACCESS);
        \Bouncer::allow($admin)->to(\App\Support\AdminAbility::DISPUTES);
        \Bouncer::refresh();

        return $admin;
    }

    /** Looking at a case must not seat you in it — joining announces itself. */
    public function test_the_admin_screen_shows_the_room_without_joining_it(): void
    {
        $dispute = $this->open();
        $admin = $this->admin();

        $this->actingAs($admin)->get("/admin/disputes/{$dispute->id}")->assertOk();

        $this->assertSame(
            2,
            $this->disputes->room($dispute)->participants()->count(),
            'reading the case must not take the arbitrator seat'
        );
    }

    /** Posting from the admin screen is what takes the seat. */
    public function test_posting_from_the_admin_screen_seats_the_arbitrator(): void
    {
        $dispute = $this->open();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post("/admin/disputes/{$dispute->id}/room", ['body' => 'Send me the confirmation, please.'])
            ->assertRedirect();

        $thread = $this->disputes->room($dispute);

        $this->assertSame(
            ThreadParticipant::ROLE_ARBITRATOR,
            $thread->participants()->where('user_id', $admin->id)->value('role')
        );
        $this->assertSame(
            1,
            $thread->messages()->where('sender_id', $admin->id)->count()
        );
    }

    // ─────────────────────────── notifications ───────────────────────────

    /** A message nobody is told about is a message nobody answers. */
    public function test_a_new_message_notifies_the_other_party_only(): void
    {
        $dispute = $this->open();
        $thread = $this->disputes->room($dispute);

        $this->threads->acceptConduct($thread, (int) $this->booking->user_id);
        $this->threads->post($thread->fresh('participants'), (int) $this->booking->user_id, 'Any update on this?');

        $notified = \App\Models\AppNotification::query()
            ->where('type', \App\Models\AppNotification::TYPE_MESSAGE)
            ->where('source_type', Thread::class)
            ->where('source_id', $thread->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame([(int) $this->booking->business_id], $notified, 'the sender must not be told about their own message');
    }

    /** The arbitrator is told too, once seated — that is the point of the seat. */
    public function test_a_message_reaches_the_arbitrator(): void
    {
        $dispute = $this->open();
        $arbitrator = $this->stranger();
        $thread = $this->disputes->joinAsArbitrator($dispute, (int) $arbitrator->id);

        $this->threads->acceptConduct($thread, (int) $this->booking->user_id);
        $this->threads->post($thread->fresh('participants'), (int) $this->booking->user_id, 'Here is my evidence.');

        $this->assertSame(
            1,
            \App\Models\AppNotification::query()
                ->where('type', \App\Models\AppNotification::TYPE_MESSAGE)
                ->where('source_id', $thread->id)
                ->where('user_id', $arbitrator->id)
                ->count()
        );
    }

    /** The notification points at the dispute — the app has no thread screen. */
    public function test_the_notification_points_at_the_dispute(): void
    {
        $dispute = $this->open();
        $thread = $this->disputes->room($dispute);

        $this->threads->acceptConduct($thread, (int) $this->booking->user_id);
        $this->threads->post($thread->fresh('participants'), (int) $this->booking->user_id, 'hello');

        $notification = \App\Models\AppNotification::query()
            ->where('source_id', $thread->id)
            ->where('type', \App\Models\AppNotification::TYPE_MESSAGE)
            ->firstOrFail();

        $this->assertSame(Dispute::class, $notification->notifiable_type);
        $this->assertSame((int) $dispute->id, (int) $notification->notifiable_id);
    }

    /** System narration must not spam: escalation and rulings notify on their own. */
    public function test_a_system_message_notifies_nobody(): void
    {
        $dispute = $this->open();
        $thread = $this->disputes->room($dispute);

        $this->threads->system($thread, 'something happened');

        $this->assertSame(
            0,
            \App\Models\AppNotification::query()
                ->where('type', \App\Models\AppNotification::TYPE_MESSAGE)
                ->where('source_id', $thread->id)
                ->count()
        );
    }

    /** Reading the room clears the badge; your own words were never unread. */
    public function test_unread_counts_ignore_your_own_messages(): void
    {
        $dispute = $this->open();
        $thread = $this->disputes->room($dispute);

        $this->threads->acceptConduct($thread, (int) $this->booking->user_id);
        $this->threads->markRead($thread, (int) $this->booking->user_id);
        $this->threads->post($thread->fresh('participants'), (int) $this->booking->user_id, 'from the client');

        $counts = $this->threads->unreadCounts((int) $this->booking->user_id, [(int) $thread->id]);

        $this->assertSame(0, $counts[(int) $thread->id]);

        $businessCounts = $this->threads->unreadCounts((int) $this->booking->business_id, [(int) $thread->id]);

        $this->assertGreaterThan(0, $businessCounts[(int) $thread->id], 'the other party has something to read');
    }
}
