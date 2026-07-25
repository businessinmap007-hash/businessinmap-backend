<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Thread;
use App\Models\ThreadMessageAttachment;
use App\Models\User;
use App\Services\Media\ThreadAttachmentStorage;
use App\Services\OperationChatService;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The native customer↔business chat on an operation — the trusted, in-app
 * alternative to attaching forgeable screenshots. It reuses the generic thread
 * machinery (so evidence attachments come for free), asks for NO conduct
 * charter, and is kept for 7 days after the operation completes before it locks
 * and becomes deletable in the panel.
 */
class OperationChatTest extends TestCase
{
    use DatabaseTransactions;

    private Booking $booking;
    private User $client;
    private User $business;
    private OperationChatService $chats;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chats = app(OperationChatService::class);

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

        // Start each test from a clean chat + a non-completed, undisputed
        // operation (a leftover dispute would make hasDispute() true and skip
        // the cleanup paths under test).
        Thread::query()
            ->where('subject_type', $booking->getMorphClass())
            ->where('subject_id', $booking->id)
            ->get()->each->delete();

        \App\Models\Dispute::query()
            ->where('disputeable_type', $booking->getMorphClass())
            ->where('disputeable_id', $booking->id)
            ->delete();

        $booking->update(['status' => Booking::STATUS_IN_PROGRESS]);
    }

    protected function tearDown(): void
    {
        $uploads = app(ThreadAttachmentStorage::class);
        foreach (ThreadMessageAttachment::query()->pluck('path') as $path) {
            $uploads->delete($path);
        }
        parent::tearDown();
    }

    private function chatUrl(): string
    {
        return "/api/v2/operation-chats/booking/{$this->booking->id}";
    }

    private function file(): UploadedFile
    {
        return UploadedFile::fake()->create('evidence.jpg', 64, 'image/jpeg');
    }

    private function admin(): User
    {
        $admin = User::query()->where('type', User::TYPE_ADMIN)->firstOrFail();

        foreach ([AdminAbility::ACCESS, AdminAbility::OPERATIONS] as $ability) {
            \Bouncer::allow($admin)->to($ability);
        }
        \Bouncer::refresh();

        return $admin;
    }

    // ─────────────────────────── the chat ───────────────────────────

    public function test_a_party_can_open_the_chat_and_post_without_any_charter(): void
    {
        Sanctum::actingAs($this->client);

        // No conduct acceptance step at all — unlike the dispute room.
        $this->postJson($this->chatUrl() . '/messages', ['body' => 'Is my order ready?'])
            ->assertCreated()
            ->assertJsonPath('data.is_mine', true);

        // The chat opened with a system message + the party's line.
        $thread = Thread::query()
            ->where('subject_type', $this->booking->getMorphClass())
            ->where('subject_id', $this->booking->id)
            ->firstOrFail();

        $this->assertFalse((bool) $thread->requires_conduct);
    }

    public function test_a_party_can_attach_evidence(): void
    {
        Sanctum::actingAs($this->client);

        $res = $this->postJson($this->chatUrl() . '/messages', [
            'body' => 'Here is the item photo.',
            'attachments' => [$this->file()],
        ])->assertCreated();

        $this->assertCount(1, $res->json('data.attachments'));
        $path = ThreadMessageAttachment::query()->latest('id')->value('path');
        $this->assertFileExists(storage_path('app/'.$path));
    }

    public function test_the_other_party_reads_it_and_a_stranger_cannot(): void
    {
        Sanctum::actingAs($this->client);
        $this->postJson($this->chatUrl() . '/messages', ['body' => 'hello'])->assertCreated();

        Sanctum::actingAs($this->business);
        $this->getJson($this->chatUrl())
            ->assertOk()
            ->assertJsonPath('data.0.is_mine', false);

        // A stranger must not even learn the operation exists.
        $stranger = User::query()
            ->whereNotIn('id', [(int) $this->booking->user_id, (int) $this->booking->business_id])
            ->orderBy('id')->firstOrFail();

        Sanctum::actingAs($stranger);
        $this->getJson($this->chatUrl())->assertNotFound();
        $this->postJson($this->chatUrl() . '/messages', ['body' => 'x'])->assertNotFound();
    }

    public function test_an_unknown_operation_type_is_not_found(): void
    {
        Sanctum::actingAs($this->client);
        $this->getJson("/api/v2/operation-chats/widget/{$this->booking->id}")->assertNotFound();
    }

    // ─────────────────────────── retention ───────────────────────────

    public function test_completion_starts_the_retention_window_and_expiry_locks_it(): void
    {
        Sanctum::actingAs($this->client);
        $this->postJson($this->chatUrl() . '/messages', ['body' => 'thanks'])->assertCreated();

        // Not complete yet: the sweep leaves it alone.
        $this->chats->sweep();
        $thread = $this->threadRow();
        $this->assertNull($thread->retain_until);

        // Complete the operation → the sweep starts the 7-day clock.
        $this->booking->update(['status' => Booking::STATUS_COMPLETED]);
        $this->chats->sweep();
        $thread = $this->threadRow();
        $this->assertNotNull($thread->retain_until);
        $this->assertFalse($thread->isLocked());

        // Once the window has passed, the next sweep locks it.
        $thread->update(['retain_until' => now()->subDay()]);
        $this->chats->sweep();
        $thread = $this->threadRow();
        $this->assertTrue($thread->isLocked());

        // A locked chat refuses new messages.
        Sanctum::actingAs($this->client);
        $this->postJson($this->chatUrl() . '/messages', ['body' => 'too late'])->assertStatus(422);
    }

    // ─────────────────────────── admin cleanup ───────────────────────────

    public function test_admin_sees_expired_chats_and_can_delete_them_with_their_files(): void
    {
        Sanctum::actingAs($this->client);
        $this->postJson($this->chatUrl() . '/messages', [
            'body' => 'proof',
            'attachments' => [$this->file()],
        ])->assertCreated();

        $path = ThreadMessageAttachment::query()->latest('id')->value('path');
        $this->assertFileExists(storage_path('app/'.$path));

        // Make it expired.
        $thread = $this->threadRow();
        $thread->update(['retain_until' => now()->subDay(), 'status' => Thread::STATUS_LOCKED]);

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/operation-chats')
            ->assertOk()
            ->assertSee('#' . $this->booking->id);

        $this->actingAs($admin)
            ->delete("/admin/operation-chats/{$thread->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('threads', ['id' => $thread->id]);
        $this->assertFileDoesNotExist(storage_path('app/'.$path), 'deleting the chat must remove its files');
    }

    public function test_admin_cannot_delete_a_chat_that_is_not_expired(): void
    {
        Sanctum::actingAs($this->client);
        $this->postJson($this->chatUrl() . '/messages', ['body' => 'still active'])->assertCreated();

        $thread = $this->threadRow(); // retain_until null → not expired
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/admin/operation-chats/{$thread->id}")->assertRedirect();

        $this->assertDatabaseHas('threads', ['id' => $thread->id]);
    }

    // ─────────────── ending notice / auto-delete / party delete ───────────────

    public function test_expiry_notifies_both_parties_and_a_party_can_delete_it(): void
    {
        Sanctum::actingAs($this->client);
        $this->postJson($this->chatUrl() . '/messages', ['body' => 'done, thanks'])->assertCreated();

        $thread = $this->threadRow();
        $thread->update(['retain_until' => now()->subDay()]); // window passed
        $this->chats->sweep();

        // Both parties were told the chat ended.
        foreach ([(int) $this->booking->user_id, (int) $this->booking->business_id] as $uid) {
            $this->assertDatabaseHas('app_notifications', [
                'user_id' => $uid,
                'type' => \App\Models\AppNotification::TYPE_SYSTEM,
                'source_type' => Thread::class,
                'source_id' => $thread->id,
            ]);
        }

        // A single party can delete it now.
        Sanctum::actingAs($this->business);
        $this->deleteJson($this->chatUrl())->assertOk();
        $this->assertDatabaseMissing('threads', ['id' => $thread->id]);
    }

    public function test_a_party_cannot_delete_before_the_window_ends(): void
    {
        Sanctum::actingAs($this->client);
        $this->postJson($this->chatUrl() . '/messages', ['body' => 'still going'])->assertCreated();

        // retain_until null → not expired.
        $this->deleteJson($this->chatUrl())->assertStatus(422);
        $this->assertDatabaseHas('threads', ['id' => $this->threadRow()->id]);
    }

    public function test_the_sweep_auto_deletes_an_undisputed_chat_after_the_grace_window(): void
    {
        Sanctum::actingAs($this->client);
        $this->postJson($this->chatUrl() . '/messages', [
            'body' => 'evidence',
            'attachments' => [$this->file()],
        ])->assertCreated();

        $path = ThreadMessageAttachment::query()->latest('id')->value('path');
        $thread = $this->threadRow();

        // Past the retention window AND its grace period.
        $thread->update([
            'retain_until' => now()->subDays(OperationChatService::AUTO_DELETE_GRACE_DAYS + 1),
        ]);

        $this->chats->sweep();

        $this->assertDatabaseMissing('threads', ['id' => $thread->id]);
        $this->assertFileDoesNotExist(storage_path('app/'.$path), 'auto-delete must remove the files too');
    }

    public function test_a_disputed_chat_is_kept_not_auto_deleted_or_deletable_by_a_party(): void
    {
        Sanctum::actingAs($this->client);
        $this->postJson($this->chatUrl() . '/messages', ['body' => 'there is a problem'])->assertCreated();

        // A dispute exists on the same operation (raw insert — hasDispute()
        // only checks existence by disputeable_type/id).
        \Illuminate\Support\Facades\DB::table('disputes')->insert([
            'disputeable_type' => $this->booking->getMorphClass(),
            'disputeable_id' => $this->booking->id,
            'opened_by_user_id' => (int) $this->booking->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $thread = $this->threadRow();
        $thread->update(['retain_until' => now()->subDays(OperationChatService::AUTO_DELETE_GRACE_DAYS + 1)]);

        $this->chats->sweep();
        $this->assertDatabaseHas('threads', ['id' => $thread->id]); // evidence — never auto-deleted

        // And a party cannot force it either.
        Sanctum::actingAs($this->client);
        $this->deleteJson($this->chatUrl())->assertStatus(422);
        $this->assertDatabaseHas('threads', ['id' => $thread->id]);
    }

    private function threadRow(): Thread
    {
        return Thread::query()
            ->where('subject_type', $this->booking->getMorphClass())
            ->where('subject_id', $this->booking->id)
            ->firstOrFail();
    }
}
