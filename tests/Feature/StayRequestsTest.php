<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\BookableItemRoom;
use App\Models\Booking;
use App\Models\PlatformService;
use App\Models\StayRequest;
use App\Models\StayServiceOption;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * «زر ابلاغ عن مشكلة بالغرفة وزر طلب خدمة … وتصل لشاشة البزنس برقم الغرفة والطلب» — المالك، 2026-10-07.
 *
 * A guest in a started hotel stay reports a problem or orders something; it reaches the hotel carrying the room number
 * the hotel gave the stay. Rolls back.
 */
class StayRequestsTest extends TestCase
{
    use DatabaseTransactions;

    private User $hotel;

    private User $guest;

    private Booking $booking;

    private BookableItem $type;

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

        $this->booking = $booking;
        $this->hotel = $hotel;
        $this->guest = $booking->user;

        $this->type = BookableItem::create([
            'business_id' => $hotel->id,
            'service_id' => (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id'),
            'item_type' => 'booking_stay',
            'title' => 'غرفة مزدوجة',
            'code' => 'TYPE-A',
            'capacity' => 2,
            'quantity' => 1,
            'is_active' => 1,
        ]);
        $room = BookableItemRoom::create(['bookable_item_id' => $this->type->id, 'business_id' => $hotel->id, 'number' => '305', 'status' => 'available']);

        StayServiceOption::query()->where('business_id', $hotel->id)->delete();
        StayRequest::query()->where('booking_id', $booking->id)->delete();

        $booking->forceFill([
            'status' => Booking::STATUS_IN_PROGRESS,
            'bookable_type' => BookableItem::class,
            'bookable_id' => $this->type->id,
            'room_id' => $room->id,
        ])->save();
    }

    private function send(array $body, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->guest, 'sanctum')->postJson("/api/v2/bookings/{$this->booking->id}/stay-requests", $body);
    }

    public function test_the_guest_reports_a_problem_and_the_hotel_sees_it_with_the_room_number(): void
    {
        $this->send(['kind' => 'issue', 'category' => 'ac', 'note' => 'التكييف متوقف'])
            ->assertCreated()
            ->assertJsonPath('data.request.title', 'تكييف')
            ->assertJsonPath('data.request.status', 'new');

        $list = $this->actingAs($this->hotel, 'sanctum')->getJson('/api/v2/business/stay-requests')->assertOk();
        $list->assertJsonPath('data.open_count', 1)
            ->assertJsonPath('data.requests.0.room_number', '305')
            ->assertJsonPath('data.requests.0.note', 'التكييف متوقف')
            ->assertJsonPath('data.requests.0.kind', 'issue');
    }

    public function test_the_guest_orders_a_service_from_the_starting_list_until_the_hotel_writes_its_own(): void
    {
        $options = $this->actingAs($this->guest, 'sanctum')->getJson("/api/v2/bookings/{$this->booking->id}/stay-requests")->assertOk();
        $this->assertTrue($options->json('data.can_request'));
        $this->assertContains('قهوة', array_column($options->json('data.services'), 'title'));

        $this->send(['kind' => 'service', 'title' => 'قهوة'])->assertCreated()->assertJsonPath('data.request.kind', 'service');

        // the hotel writes its own list: the starting list is kept (it was the hotel's to edit) and the new one is added
        $this->actingAs($this->hotel, 'sanctum')->postJson('/api/v2/business/stay-services', ['titles' => ['كيكة الشيف']])
            ->assertCreated()->assertJsonPath('data.custom', true);
        $this->send(['kind' => 'service', 'title' => 'كيكة الشيف'])->assertCreated();

        // …and switching one off takes it away from the guest
        $coffee = StayServiceOption::query()->where('business_id', $this->hotel->id)->where('title', 'قهوة')->firstOrFail();
        $this->actingAs($this->hotel, 'sanctum')->patchJson("/api/v2/business/stay-services/{$coffee->id}", ['is_active' => false])->assertOk();
        $this->send(['kind' => 'service', 'title' => 'قهوة'])->assertStatus(422);

        // a service nobody offers
        $this->send(['kind' => 'service', 'title' => 'مروحية'])->assertStatus(422);
    }

    public function test_an_issue_of_another_kind_must_be_described(): void
    {
        $this->send(['kind' => 'issue', 'category' => 'other'])->assertStatus(422);
        $this->send(['kind' => 'issue', 'category' => 'other', 'note' => 'ريحة غريبة'])->assertCreated();
        $this->send(['kind' => 'issue', 'category' => 'no_such'])->assertStatus(422);
    }

    public function test_nothing_can_be_requested_before_the_stay_starts_or_on_a_booking_that_is_not_a_stay(): void
    {
        $this->booking->forceFill(['status' => Booking::STATUS_ACCEPTED])->save();
        $this->send(['kind' => 'issue', 'category' => 'ac'])->assertStatus(422);

        $this->booking->forceFill(['status' => Booking::STATUS_IN_PROGRESS])->save();
        $table = BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->type->service_id, 'item_type' => 'booking_table', 'code' => 'T1', 'quantity' => 1, 'is_active' => 1,
        ]);
        $this->booking->forceFill(['bookable_id' => $table->id])->save();
        $this->send(['kind' => 'issue', 'category' => 'ac'])->assertStatus(422);
    }

    public function test_only_the_guest_of_the_stay_may_ask(): void
    {
        $other = User::query()->where('id', '!=', $this->guest->id)->where('id', '!=', $this->hotel->id)->firstOrFail();

        $this->send(['kind' => 'issue', 'category' => 'ac'], $other)->assertForbidden();
    }

    public function test_the_hotel_moves_a_request_along_and_a_finished_one_stays_finished(): void
    {
        $id = $this->send(['kind' => 'service', 'title' => 'فطار'])->assertCreated()->json('data.request.id');

        $this->actingAs($this->hotel, 'sanctum')->patchJson("/api/v2/business/stay-requests/{$id}", ['status' => 'in_progress'])
            ->assertOk()->assertJsonPath('data.request.status', 'in_progress');
        $this->actingAs($this->hotel, 'sanctum')->patchJson("/api/v2/business/stay-requests/{$id}", ['status' => 'done'])
            ->assertOk()->assertJsonPath('data.request.status', 'done');
        $this->actingAs($this->hotel, 'sanctum')->patchJson("/api/v2/business/stay-requests/{$id}", ['status' => 'in_progress'])
            ->assertStatus(422);

        $this->actingAs($this->hotel, 'sanctum')->getJson('/api/v2/business/stay-requests')->assertJsonPath('data.open_count', 0);
        $this->actingAs($this->hotel, 'sanctum')->getJson('/api/v2/business/stay-requests?status=done')->assertJsonCount(1, 'data.requests');
    }

    public function test_the_guest_may_withdraw_only_what_the_hotel_has_not_started(): void
    {
        $first = $this->send(['kind' => 'service', 'title' => 'شاي'])->json('data.request.id');
        $second = $this->send(['kind' => 'service', 'title' => 'فطار'])->json('data.request.id');

        $this->actingAs($this->guest, 'sanctum')->postJson("/api/v2/bookings/{$this->booking->id}/stay-requests/{$first}/cancel")
            ->assertOk()->assertJsonPath('data.request.status', 'cancelled');

        $this->actingAs($this->hotel, 'sanctum')->patchJson("/api/v2/business/stay-requests/{$second}", ['status' => 'in_progress'])->assertOk();
        $this->actingAs($this->guest, 'sanctum')->postJson("/api/v2/bookings/{$this->booking->id}/stay-requests/{$second}/cancel")->assertStatus(422);
    }

    public function test_another_business_cannot_touch_the_request(): void
    {
        $id = $this->send(['kind' => 'issue', 'category' => 'noise'])->json('data.request.id');
        $other = User::query()->where('type', User::TYPE_BUSINESS)->where('id', '!=', $this->hotel->id)->firstOrFail();

        $this->actingAs($other, 'sanctum')->patchJson("/api/v2/business/stay-requests/{$id}", ['status' => 'done'])->assertNotFound();
    }

    public function test_one_stay_cannot_bury_the_front_desk(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->send(['kind' => 'issue', 'category' => 'noise'])->assertCreated();
        }

        $this->send(['kind' => 'issue', 'category' => 'noise'])->assertStatus(422);
    }
}
