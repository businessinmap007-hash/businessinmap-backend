<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminV2\BookingController;
use App\Models\Booking;
use App\Models\Deposit;
use App\Models\GuaranteeLevel;
use App\Models\OperationGuarantor;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Services\Guarantees\OperationGuarantorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guarantee-only (no wallet deposit) booking completion/cancellation from the
 * AdminV2 booking screen must return any frozen co-guarantor coverage — it never
 * flows through BookingDepositService, so releaseGuarantees() has to be wired
 * into complete()/cancel() directly. All rows rolled back.
 */
class AdminBookingGuaranteeReleaseTest extends TestCase
{
    use DatabaseTransactions;

    private OperationGuarantorService $guarantors;

    private Booking $booking;

    private User $client;

    private User $friend;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guarantors = app(OperationGuarantorService::class);

        $booking = Booking::withTrashed()
            ->whereNotNull('user_id')->whereNotNull('business_id')
            ->whereColumn('user_id', '!=', 'business_id')
            ->first();

        if ($booking && $booking->trashed()) {
            $booking->restore();
        }

        $levelId = (int) DB::table('guarantee_levels')->value('id');
        $client = $booking?->user;
        $friend = $client
            ? User::query()->whereNotIn('id', [(int) $booking->user_id, (int) $booking->business_id])->first()
            : null;

        if (! $booking || ! $client || ! $friend || $levelId <= 0) {
            $this->markTestSkipped('Needs a booking with a client, a distinct friend, and a guarantee level.');
        }

        $this->booking = $booking;
        $this->client = $client;
        $this->friend = $friend;

        // Friend's platform-purchased guarantee (coverage 500, nothing used).
        UserGuarantee::query()->where('user_id', $friend->id)->where('target_type', 'client')->delete();
        UserGuarantee::create([
            'user_id' => $friend->id, 'target_type' => GuaranteeLevel::TARGET_CLIENT,
            'purchased_level_id' => $levelId, 'effective_level_id' => $levelId,
            'status' => UserGuarantee::STATUS_ACTIVE, 'current_coverage_amount' => 500, 'used_coverage_amount' => 0,
        ]);

        // Clean slate: no lingering guarantors or deposit — this is the
        // guarantee-only path (no wallet deposit at all).
        OperationGuarantor::query()->forOperation('booking', (int) $booking->id)->delete();
        Deposit::query()->where('target_type', Booking::class)->where('target_id', $booking->id)->delete();
    }

    private function friendGuarantee(): UserGuarantee
    {
        return UserGuarantee::query()->where('user_id', $this->friend->id)->where('target_type', 'client')->first();
    }

    private function freezeFriendCoverage(float $amount): OperationGuarantor
    {
        $row = $this->guarantors->invite('booking', (int) $this->booking->id, $this->client, $this->friend);
        $this->guarantors->accept($row, $amount);

        return $row;
    }

    public function test_admin_complete_releases_frozen_guarantee_coverage(): void
    {
        $row = $this->freezeFriendCoverage(200);
        $this->assertEqualsWithDelta(200.0, (float) $this->friendGuarantee()->used_coverage_amount, 0.001, 'frozen after accept');

        $this->booking->status = Booking::STATUS_IN_PROGRESS;
        $this->booking->save();

        app(BookingController::class)->complete($this->booking);

        $this->assertEqualsWithDelta(0.0, (float) $this->friendGuarantee()->used_coverage_amount, 0.001, 'returned on completion');
        $this->assertSame(
            OperationGuarantor::STATUS_RELEASED,
            OperationGuarantor::query()->whereKey($row->id)->value('status')
        );
    }

    public function test_admin_cancel_releases_frozen_guarantee_coverage(): void
    {
        $row = $this->freezeFriendCoverage(150);
        $this->assertEqualsWithDelta(150.0, (float) $this->friendGuarantee()->used_coverage_amount, 0.001, 'frozen after accept');

        $this->booking->status = Booking::STATUS_IN_PROGRESS;
        $this->booking->save();

        app(BookingController::class)->cancel($this->booking);

        $this->assertEqualsWithDelta(0.0, (float) $this->friendGuarantee()->used_coverage_amount, 0.001, 'returned on cancellation');
        $this->assertSame(
            OperationGuarantor::STATUS_RELEASED,
            OperationGuarantor::query()->whereKey($row->id)->value('status')
        );
    }
}
