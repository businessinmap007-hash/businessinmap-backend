<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ConductViolation;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ArbitrationService;
use App\Services\BookingDepositService;
use App\Services\DisputeService;
use App\Services\ThreadService;
use App\Services\WalletService;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

/**
 * The conduct charter: what a party agrees to before they may write in the
 * room, and what recording a breach does — and deliberately does not do.
 *
 * The consent is to the ARBITRATOR'S JUDGEMENT, not to automatic detection: a
 * word list would fire on dialect and sarcasm and be trivially worked around,
 * so nothing here decides on its own what counts as an insult.
 */
class DisputeConductTest extends TestCase
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

    private function makeArbitrator(): User
    {
        $admin = new User();
        $admin->name = 'Conduct Test Arbitrator';
        $admin->email = 'conduct-' . uniqid() . '@example.test';
        $admin->phone = '0155' . random_int(1000000, 9999999);
        $admin->password = 'secret-password';
        $admin->type = User::TYPE_ADMIN;
        $admin->api_token = Str::random(80);
        $admin->save();

        app(ArbitrationService::class)->promote($admin);
        Bouncer::refresh();

        return $admin;
    }

    // ─────────────────────────── the charter ───────────────────────────

    /** The warning must actually say what it costs, or consent means nothing. */
    public function test_the_charter_states_the_two_consequences(): void
    {
        $charter = $this->threads->conductCharter();

        $text = implode(' ', $charter['clauses']);

        // Compared through __(), not against the Arabic source: the charter is
        // translatable, so a literal here would pass only in Arabic.
        $this->assertStringContainsString(
            __('قد تخسر الجلسة بسبب المخالفة حتى لو كان الحق معك في أصل النزاع.'),
            $text,
            'losing the case must be stated'
        );
        $this->assertStringContainsString(
            __('قد تُفرض عليك غرامة منصة بسبب المخالفة، مستقلة عن نتيجة النزاع.'),
            $text,
            'the fine must be stated'
        );
        $this->assertSame(ThreadService::CONDUCT_VERSION, $charter['version']);
    }

    /** Nobody writes in the room before agreeing. */
    public function test_a_party_cannot_post_before_accepting_the_charter(): void
    {
        $thread = $this->disputes->room($this->open());

        $this->expectException(ValidationException::class);
        $this->threads->post($thread, (int) $this->booking->user_id, 'hello');
    }

    public function test_accepting_the_charter_opens_the_room(): void
    {
        $thread = $this->disputes->room($this->open());

        $this->threads->acceptConduct($thread, (int) $this->booking->user_id);

        $message = $this->threads->post($thread->fresh('participants'), (int) $this->booking->user_id, 'hello');

        $this->assertSame((int) $this->booking->user_id, (int) $message->sender_id);
    }

    /**
     * A rewritten charter is a different promise, so bumping the version
     * invalidates what people agreed to before.
     */
    public function test_an_acceptance_of_an_older_charter_does_not_count(): void
    {
        $thread = $this->disputes->room($this->open());

        $this->threads->acceptConduct($thread, (int) $this->booking->user_id);

        // Simulate the charter having been rewritten since.
        $thread->participants()
            ->where('user_id', $this->booking->user_id)
            ->update(['conduct_version' => ThreadService::CONDUCT_VERSION - 1]);

        $this->assertFalse(
            $this->threads->hasAcceptedConduct($thread->fresh('participants'), (int) $this->booking->user_id)
        );
    }

    /**
     * The charter's clauses are about losing the case and being fined — neither
     * can happen to the person deciding it.
     */
    public function test_the_arbitrator_is_not_gated_on_the_party_charter(): void
    {
        $dispute = $this->open();
        $arbitrator = $this->makeArbitrator();

        $thread = $this->disputes->joinAsArbitrator($dispute, (int) $arbitrator->id);

        $message = $this->threads->post($thread->fresh('participants'), (int) $arbitrator->id, 'Send the receipt.');

        $this->assertSame((int) $arbitrator->id, (int) $message->sender_id);
    }

    // ─────────────────────────── violations ───────────────────────────

    /** A row that points at the message, so the party can see what is held against them. */
    public function test_a_violation_points_at_the_offending_message(): void
    {
        $dispute = $this->open();
        $thread = $this->disputes->room($dispute);

        $this->threads->acceptConduct($thread, (int) $this->booking->user_id);
        $message = $this->threads->post($thread->fresh('participants'), (int) $this->booking->user_id, 'rude thing');

        $arbitrator = $this->makeArbitrator();

        $violation = $this->threads->recordViolation(
            thread: $thread->fresh('participants'),
            againstUserId: (int) $this->booking->user_id,
            recordedByUserId: (int) $arbitrator->id,
            reason: 'إهانة الطرف الآخر',
            messageId: (int) $message->id
        );

        $this->assertSame((int) $message->id, (int) $violation->thread_message_id);
        $this->assertSame((int) $this->booking->user_id, (int) $violation->against_user_id);
    }

    /** A mark recorded in silence is one the party first learns of from the ruling. */
    public function test_a_violation_is_announced_in_the_room(): void
    {
        $dispute = $this->open();
        $thread = $this->disputes->room($dispute);
        $arbitrator = $this->makeArbitrator();

        $before = $thread->messages()->count();

        $this->threads->recordViolation(
            thread: $thread->fresh('participants'),
            againstUserId: (int) $this->booking->business_id,
            recordedByUserId: (int) $arbitrator->id,
            reason: 'تهديد'
        );

        $this->assertGreaterThan($before, $thread->fresh()->messages()->count());
    }

    /**
     * THE point of the design the user chose: recording is evidence, not a
     * verdict. Nothing is deducted and nothing is decided by the act itself.
     */
    public function test_recording_a_violation_costs_nothing_and_decides_nothing(): void
    {
        $dispute = $this->open();
        $thread = $this->disputes->room($dispute);
        $arbitrator = $this->makeArbitrator();

        $wallet = Wallet::query()->where('user_id', $this->booking->user_id)->firstOrFail();
        $before = (float) $wallet->balance;

        $this->threads->recordViolation(
            thread: $thread->fresh('participants'),
            againstUserId: (int) $this->booking->user_id,
            recordedByUserId: (int) $arbitrator->id,
            reason: 'إساءة لفظية'
        );

        $this->assertEqualsWithDelta($before, (float) $wallet->fresh()->balance, 0.01, 'no automatic fine');
        $this->assertSame(
            Dispute::STATUS_MUTUAL_RESOLUTION,
            $dispute->fresh()->status,
            'no automatic loss — the arbitrator still decides'
        );
        $this->assertNull($dispute->fresh()->resolution_type);
    }

    public function test_a_violation_cannot_point_at_a_message_from_another_room(): void
    {
        $dispute = $this->open();
        $thread = $this->disputes->room($dispute);
        $arbitrator = $this->makeArbitrator();

        $this->expectException(ValidationException::class);
        $this->threads->recordViolation(
            thread: $thread->fresh('participants'),
            againstUserId: (int) $this->booking->user_id,
            recordedByUserId: (int) $arbitrator->id,
            reason: 'x',
            messageId: 999999999
        );
    }

    public function test_a_violation_cannot_be_recorded_against_a_non_participant(): void
    {
        $dispute = $this->open();
        $thread = $this->disputes->room($dispute);
        $arbitrator = $this->makeArbitrator();

        $stranger = User::query()
            ->whereNotIn('id', [(int) $this->booking->user_id, (int) $this->booking->business_id, (int) $arbitrator->id])
            ->orderBy('id')->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->threads->recordViolation(
            thread: $thread->fresh('participants'),
            againstUserId: (int) $stranger->id,
            recordedByUserId: (int) $arbitrator->id,
            reason: 'x'
        );
    }

    // ─────────────────────────── the API ───────────────────────────

    public function test_the_charter_and_acceptance_are_served_over_the_api(): void
    {
        Sanctum::actingAs($this->client);

        $id = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'quality'])
            ->json('data.id');

        $this->getJson("/api/v2/disputes/{$id}/room/conduct")
            ->assertOk()
            ->assertJsonPath('data.accepted', false)
            ->assertJsonPath('data.version', ThreadService::CONDUCT_VERSION);

        // The composer must be shut until it is accepted.
        $this->getJson("/api/v2/disputes/{$id}/room")
            ->assertOk()
            ->assertJsonPath('meta.thread.conduct_accepted', false);

        $this->postJson("/api/v2/disputes/{$id}/room/messages", ['body' => 'hello'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('conduct');

        $this->postJson("/api/v2/disputes/{$id}/room/conduct")
            ->assertOk()
            ->assertJsonPath('data.accepted', true);

        $this->postJson("/api/v2/disputes/{$id}/room/messages", ['body' => 'hello'])
            ->assertCreated();
    }

    public function test_a_stranger_cannot_read_or_accept_the_charter(): void
    {
        Sanctum::actingAs($this->client);
        $id = $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'quality'])
            ->json('data.id');

        $stranger = User::query()
            ->whereNotIn('id', [(int) $this->booking->user_id, (int) $this->booking->business_id])
            ->orderBy('id')->firstOrFail();

        Sanctum::actingAs($stranger);

        $this->getJson("/api/v2/disputes/{$id}/room/conduct")->assertNotFound();
        $this->postJson("/api/v2/disputes/{$id}/room/conduct")->assertNotFound();
    }

    // ─────────────────────────── the admin screen ───────────────────────────

    public function test_the_arbitrator_can_record_a_violation_from_the_panel(): void
    {
        $dispute = $this->open();
        $arbitrator = $this->makeArbitrator();

        $this->actingAs($arbitrator)
            ->post("/admin/disputes/{$dispute->id}/conduct-violation", [
                'against_user_id' => (int) $this->booking->business_id,
                'reason' => 'ألفاظ غير لائقة',
            ])
            ->assertRedirect();

        $this->assertSame(
            1,
            ConductViolation::query()
                ->where('against_user_id', $this->booking->business_id)
                ->where('recorded_by_user_id', $arbitrator->id)
                ->count()
        );
    }

    public function test_recording_a_violation_needs_the_disputes_ability(): void
    {
        $dispute = $this->open();

        $admin = new User();
        $admin->name = 'No Disputes Ability';
        $admin->email = 'noability-' . uniqid() . '@example.test';
        $admin->phone = '0155' . random_int(1000000, 9999999);
        $admin->password = 'secret-password';
        $admin->type = User::TYPE_ADMIN;
        $admin->api_token = Str::random(80);
        $admin->save();

        Bouncer::allow($admin)->to(AdminAbility::ACCESS);
        Bouncer::refresh();

        $this->actingAs($admin)
            ->post("/admin/disputes/{$dispute->id}/conduct-violation", [
                'against_user_id' => (int) $this->booking->business_id,
                'reason' => 'x',
            ])
            ->assertForbidden();
    }
}
