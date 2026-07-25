<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\DisputeFee;
use App\Models\ThreadMessageAttachment;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ArbitrationService;
use App\Services\BookingDepositService;
use App\Services\DisputeService;
use App\Services\Media\ThreadAttachmentStorage;
use App\Services\ThreadService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Evidence upload in the arbitration room. A party could only ever type text,
 * so the photo of the item / the receipt the case turns on had nowhere to go.
 *
 * GD is not installed on this box, so uploads are faked with ->create(), never
 * ->image() (which needs GD). tearDown unlinks every stored file: the DB rolls
 * back, the filesystem does not.
 */
class DisputeRoomAttachmentTest extends TestCase
{
    use DatabaseTransactions;

    private Booking $booking;
    private User $client;
    private User $business;
    private DisputeService $disputes;
    private ThreadService $threads;
    private ArbitrationService $arbitration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disputes = app(DisputeService::class);
        $this->threads = app(ThreadService::class);
        $this->arbitration = app(ArbitrationService::class);

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

    protected function tearDown(): void
    {
        // The DB transaction rolls back, but files on disk do not — unlink each
        // stored evidence file while its row is still readable.
        $uploads = app(ThreadAttachmentStorage::class);

        foreach (ThreadMessageAttachment::query()->pluck('path') as $path) {
            $uploads->delete($path);
        }

        parent::tearDown();
    }

    private function openDispute(): int
    {
        Sanctum::actingAs($this->client);

        return (int) $this->postJson("/api/v2/bookings/{$this->booking->id}/disputes", ['reason_code' => 'quality'])
            ->json('data.id');
    }

    private function file(string $name = 'evidence.jpg'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 64, 'image/jpeg');
    }

    private function makeAdmin(): User
    {
        $admin = new User();
        $admin->name = 'Attachment Test Admin';
        $admin->email = 'attach-' . uniqid() . '@example.test';
        $admin->phone = '0155' . random_int(1000000, 9999999);
        $admin->password = 'secret-password';
        $admin->type = User::TYPE_ADMIN;
        $admin->api_token = Str::random(80);
        $admin->save();

        return $admin;
    }

    private function uploadDirCount(): int
    {
        $dir = storage_path('app/' . ThreadAttachmentStorage::DIR);

        return is_dir($dir) ? count(glob($dir . '/*') ?: []) : 0;
    }

    public function test_a_party_can_attach_evidence_to_a_message(): void
    {
        $id = $this->openDispute();

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/disputes/{$id}/room/conduct")->assertOk();

        $response = $this->postJson("/api/v2/disputes/{$id}/room/messages", [
            'body' => 'Here is the receipt.',
            'attachments' => [$this->file()],
        ])->assertCreated();

        $response->assertJsonPath('data.is_mine', true);
        $this->assertCount(1, $response->json('data.attachments'));

        $url = $response->json('data.attachments.0.url');
        $this->assertNotEmpty($url);

        // The file is really on disk, and the row records it.
        $path = ThreadMessageAttachment::query()->latest('id')->value('path');
        $this->assertNotNull($path);
        $this->assertFileExists(storage_path('app/'.$path));
    }

    public function test_a_message_can_be_an_attachment_with_no_text(): void
    {
        $id = $this->openDispute();

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/disputes/{$id}/room/conduct")->assertOk();

        $this->postJson("/api/v2/disputes/{$id}/room/messages", [
            'attachments' => [$this->file('photo.jpg')],
        ])
            ->assertCreated()
            ->assertJsonPath('data.body', '')
            ->assertJsonCount(1, 'data.attachments');
    }

    public function test_a_message_with_neither_text_nor_a_file_is_refused(): void
    {
        $id = $this->openDispute();

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/disputes/{$id}/room/conduct")->assertOk();

        $this->postJson("/api/v2/disputes/{$id}/room/messages", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');
    }

    public function test_attachments_come_back_when_reading_the_room(): void
    {
        $id = $this->openDispute();

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/disputes/{$id}/room/conduct")->assertOk();
        $this->postJson("/api/v2/disputes/{$id}/room/messages", [
            'body' => 'proof',
            'attachments' => [$this->file()],
        ])->assertCreated();

        Sanctum::actingAs($this->business);
        $room = $this->getJson("/api/v2/disputes/{$id}/room")->assertOk();

        // Newest first; the party's message is index 0 (system open message last).
        $this->assertCount(1, $room->json('data.0.attachments'));
        $this->assertNotEmpty($room->json('data.0.attachments.0.url'));
    }

    public function test_a_refused_post_leaves_no_orphan_file(): void
    {
        $id = $this->openDispute();

        // Seal the room with a ruling; posting is now refused.
        $this->disputes->resolve(Dispute::findOrFail($id), 'refund_client');

        $before = $this->uploadDirCount();

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/disputes/{$id}/room/messages", [
            'body' => 'too late',
            'attachments' => [$this->file()],
        ])->assertStatus(422);

        // The guard runs before the upload, so nothing was written.
        $this->assertSame($before, $this->uploadDirCount(), 'a rejected post must not store its file');
    }

    public function test_purging_the_room_deletes_the_evidence_files(): void
    {
        $id = $this->openDispute();

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/disputes/{$id}/room/conduct")->assertOk();
        $this->postJson("/api/v2/disputes/{$id}/room/messages", [
            'body' => 'evidence',
            'attachments' => [$this->file()],
        ])->assertCreated();

        $path = ThreadMessageAttachment::query()->latest('id')->value('path');
        $this->assertFileExists(storage_path('app/'.$path));

        // Close the case (the room locks), then both parties consent to delete
        // the conversation — the one path that purges.
        $admin = $this->makeAdmin();
        $dispute = Dispute::findOrFail($id);
        $this->arbitration->acceptSession($dispute, (int) $admin->id);
        $this->disputes->resolve($dispute->fresh(), 'release_business', [], (int) $admin->id);
        $this->arbitration->chargeArbitrationFee($dispute->fresh());
        $this->disputes->closeWithCompliance($dispute->fresh(), (int) $admin->id);

        $this->disputes->confirmClosurePurge($dispute->fresh(), (int) $this->booking->user_id);
        $this->disputes->confirmClosurePurge($dispute->fresh(), (int) $this->booking->business_id);

        $this->assertFileDoesNotExist(storage_path('app/'.$path), 'a purged room must not leave its evidence on disk');
    }
}
