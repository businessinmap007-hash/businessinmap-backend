<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The admin booking screen used to show "Client confirmed / Business confirmed"
 * with no hint of WHICH confirmation that was (start-readiness, not payment or
 * service completion), and a deposit "status" string with no signal that a
 * completed booking can sit there fully frozen forever unless an admin manually
 * releases or refunds it. Both were a real point of confusion. Display-only
 * fix: relabel the confirmation, and surface an explicit warning when a
 * booking is completed but its deposit is still frozen.
 */
class AdminBookingDepositDisplayTest extends TestCase
{
    use DatabaseTransactions;

    private function actingAdmin(): User
    {
        $admin = User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin account to act as.');
        }

        return $admin;
    }

    private function anyBooking(): Booking
    {
        $booking = Booking::withTrashed()->whereNotNull('user_id')->whereNotNull('business_id')->first();

        if ($booking && $booking->trashed()) {
            $booking->restore();
        }

        if (! $booking) {
            $this->markTestSkipped('Needs a booking.');
        }

        return $booking;
    }

    public function test_warns_when_a_completed_booking_still_holds_a_frozen_deposit(): void
    {
        $admin = $this->actingAdmin();
        $booking = $this->anyBooking();

        $booking->status = Booking::STATUS_COMPLETED;
        $booking->save();

        Deposit::query()->where('target_type', Booking::class)->where('target_id', $booking->id)->delete();
        Deposit::create([
            'client_id' => $booking->user_id,
            'business_id' => $booking->business_id,
            'target_type' => Booking::class,
            'target_id' => $booking->id,
            'total_amount' => 90,
            'client_amount' => 90,
            'business_amount' => 0,
            'status' => 'frozen',
        ]);

        $html = $this->actingAs($admin)
            ->get(route('admin.bookings.show', $booking, false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('لسه متجمّد', $html, 'the frozen-after-completion state must be visible');
        $this->assertStringContainsString(
            'مفيش فك تلقائي بعد اكتمال الحجز',
            $html,
            'must warn that release/refund is a manual admin action'
        );
        $this->assertStringContainsString('تأكيد الاستعداد لبدء التنفيذ', $html, 'the start-confirm section must be relabeled');
    }

    public function test_no_warning_once_the_deposit_is_released(): void
    {
        $admin = $this->actingAdmin();
        $booking = $this->anyBooking();

        $booking->status = Booking::STATUS_COMPLETED;
        $booking->save();

        Deposit::query()->where('target_type', Booking::class)->where('target_id', $booking->id)->delete();
        Deposit::create([
            'client_id' => $booking->user_id,
            'business_id' => $booking->business_id,
            'target_type' => Booking::class,
            'target_id' => $booking->id,
            'total_amount' => 90,
            'client_amount' => 90,
            'business_amount' => 0,
            'status' => 'released',
            'released_at' => now(),
        ]);

        $html = $this->actingAs($admin)
            ->get(route('admin.bookings.show', $booking, false))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('مفيش فك تلقائي بعد اكتمال الحجز', $html);
        $this->assertStringContainsString('تم فك التجميد', $html, 'a released deposit must say so plainly');
    }
}
