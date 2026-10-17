<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\BusinessDepositPolicy;
use App\Models\BusinessServicePrice;
use App\Models\Deposit;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «جاء عميل بدون حجز»: the business books a customer who is standing there — a named GUEST with no account, or an
 * ACCOUNT (found by his phone) whose booking is then his own, with his history; and attendance is by BOTH, the
 * customer first. Rolls back.
 */
class BookingGuestTest extends TestCase
{
    use DatabaseTransactions;

    private User $hotel;

    private User $account;

    private BookableItem $type;

    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $booking = Booking::withTrashed()
            ->whereNotNull('user_id')->whereNotNull('business_id')->whereColumn('user_id', '!=', 'business_id')
            ->first();

        if ($booking && $booking->trashed()) {
            $booking->restore();
        }

        $hotel = $booking?->business;

        if (! $booking || ! $hotel || (string) $hotel->type !== User::TYPE_BUSINESS) {
            $this->markTestSkipped('Needs a booking whose business is a business account.');
        }

        $this->hotel = $hotel;
        $this->account = $booking->user;
        $this->account->forceFill(['phone' => '010' . random_int(10000000, 99999999)])->save();
        $this->serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');

        $this->type = BookableItem::create([
            'business_id' => $hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay', 'line_option_id' => 965,
            'title' => 'غرفة مزدوجة', 'code' => 'TYPE-G', 'capacity' => 2, 'quantity' => 1, 'is_active' => 1,
        ]);

        $childId = (int) ($hotel->category_child_id ?? 0);
        BusinessServicePrice::query()->where('business_id', $hotel->id)->where('service_id', $this->serviceId)->where('child_id', $childId)->delete();
        BusinessServicePrice::create([
            'business_id' => $hotel->id, 'service_id' => $this->serviceId, 'child_id' => $childId,
            'bookable_item_type' => BusinessServicePrice::DEFAULT_ITEM_TYPE, 'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);
        BusinessDepositPolicy::query()->where('business_id', $hotel->id)->delete();
        Deposit::query()->where('target_type', Booking::class)->whereIn('target_id', Booking::query()->where('bookable_id', $this->type->id)->pluck('id'))->delete();
        DB::table('category_child_service_fees')->where('category_id', (int) $hotel->category_id)->where('child_id', $childId)->delete();

        $this->actingAs($this->hotel, 'sanctum')->putJson(
            "/api/v2/business/bookable-items/{$this->type->id}/day-use",
            ['enabled' => true, 'from' => '09:00', 'to' => '18:00', 'price' => 350]
        )->assertOk();
    }

    private function body(array $extra = [], int $days = 3): array
    {
        return $extra + [
            'service_id' => $this->serviceId,
            'bookable_id' => $this->type->id,
            'day_use' => true,
            'date' => now()->addDays($days)->toDateString(),
        ];
    }

    private function asHotel()
    {
        return $this->actingAs($this->hotel, 'sanctum');
    }

    public function test_a_named_guest_with_no_account_holds_the_room_and_is_never_counted(): void
    {
        $res = $this->asHotel()->postJson('/api/v2/bookings/by-business', $this->body(['guest_name' => 'Hassan', 'guest_phone' => '01099990000']))
            ->assertCreated()
            ->assertJsonPath('data.booking.status', 'accepted')
            ->assertJsonPath('data.booking.guest.name', 'Hassan')
            ->assertJsonPath('data.booking.attendance.client', true)
            ->assertJsonPath('data.booking.attendance.business', false);

        $booking = Booking::findOrFail($res->json('data.booking.id'));

        // the business's own account stands in; the name is on the booking — and he is not a returning customer
        $this->assertSame((int) $this->hotel->id, (int) $booking->user_id);
        $this->assertSame('walk_in', $booking->meta['source']);
        $this->assertNull($res->json('data.booking.client_history'));

        // the room is held: a customer cannot take the same afternoon
        $this->actingAs($this->account, 'sanctum')->postJson('/api/v2/bookings', $this->body(['business_id' => $this->hotel->id]))->assertStatus(422);

        // a nameless guest is refused
        $this->asHotel()->postJson('/api/v2/bookings/by-business', $this->body([], 5))->assertStatus(422)->assertJsonValidationErrors('guest_name');

        // attendance is by both: the business must confirm before it starts (his own side is the fact of standing there)
        $this->asHotel()->postJson("/api/v2/bookings/{$booking->id}/start")->assertStatus(422);
        $this->asHotel()->postJson("/api/v2/bookings/{$booking->id}/business-confirm")->assertOk();
        $this->asHotel()->postJson("/api/v2/bookings/{$booking->id}/start")->assertOk()->assertJsonPath('data.booking.status', 'in_progress');
        $this->asHotel()->postJson("/api/v2/bookings/{$booking->id}/complete")->assertOk()->assertJsonPath('data.booking.status', 'completed');
    }

    public function test_a_guest_who_has_an_account_is_booked_on_his_own_account_and_confirms_first(): void
    {
        // the phone is written differently — it is the same number
        $typed = '+2' . $this->account->phone;

        $lookup = $this->asHotel()->getJson('/api/v2/bookings/customer-lookup?phone=' . urlencode($typed))->assertOk();
        $lookup->assertJsonPath('data.found', true)->assertJsonPath('data.customer.id', (int) $this->account->id);

        $res = $this->asHotel()->postJson('/api/v2/bookings/by-business', $this->body(['guest_name' => 'ignored', 'guest_phone' => $typed]))
            ->assertCreated()
            ->assertJsonPath('data.booking.status', 'accepted')
            ->assertJsonPath('data.booking.guest', null)
            ->assertJsonPath('data.booking.attendance.client', false);

        $booking = Booking::findOrFail($res->json('data.booking.id'));
        $this->assertSame((int) $this->account->id, (int) $booking->user_id, 'recorded on his own account');
        $this->assertSame('business', $booking->meta['source']);

        // the business cannot confirm attendance before the customer did
        $this->asHotel()->postJson("/api/v2/bookings/{$booking->id}/business-confirm")->assertStatus(422);

        $this->actingAs($this->account, 'sanctum')->postJson("/api/v2/bookings/{$booking->id}/client-confirm")->assertOk();
        $this->asHotel()->postJson("/api/v2/bookings/{$booking->id}/business-confirm")->assertOk()
            ->assertJsonPath('data.booking.attendance.client', true)->assertJsonPath('data.booking.attendance.business', true);
    }

    public function test_a_returning_customer_is_badged_with_his_earlier_dealings_for_the_business_only(): void
    {
        foreach ([0, 1] as $i) {
            Booking::create([
                'user_id' => $this->account->id, 'business_id' => $this->hotel->id, 'service_id' => $this->serviceId,
                'status' => Booking::STATUS_COMPLETED, 'price' => 100, 'quantity' => 1,
                'date' => now()->subDays(10 + $i)->toDateString(), 'time' => '12:00', 'starts_at' => now()->subDays(10 + $i), 'meta' => ['source' => 'guest_test'],
            ]);
        }

        $id = $this->asHotel()->postJson('/api/v2/bookings/by-business', $this->body(['guest_phone' => $this->account->phone]))->assertCreated()
            ->assertJsonPath('data.booking.client_history.previous_dealings', 2)->json('data.booking.id');

        // the lookup says the same
        $this->asHotel()->getJson('/api/v2/bookings/customer-lookup?phone=' . $this->account->phone)->assertOk()
            ->assertJsonPath('data.previous_dealings', 2);

        // the business's list carries it; the customer's own list never does
        $row = collect($this->asHotel()->getJson('/api/v2/business/bookings?per_page=50')->assertOk()->json('data.bookings.data'))->firstWhere('id', $id);
        $this->assertSame(2, $row['client_history']['previous_dealings']);

        $mine = collect($this->actingAs($this->account, 'sanctum')->getJson('/api/v2/bookings?scope=my&per_page=50')->assertOk()->json('data.bookings.data'))->firstWhere('id', $id);
        $this->assertNull($mine['client_history']);

        // a phone nobody owns is not found
        $this->asHotel()->getJson('/api/v2/bookings/customer-lookup?phone=01' . Str::random(0) . '000000001')->assertOk()->assertJsonPath('data.found', false);
    }

    public function test_the_business_may_book_a_guest_on_a_day_it_closed_for_new_bookings(): void
    {
        $this->hotel->forceFill(['booking_closed_date' => now()->addDays(3)->toDateString()])->save();

        $this->asHotel()->postJson('/api/v2/bookings/by-business', $this->body(['guest_name' => 'Karim']))->assertCreated();
    }
}
