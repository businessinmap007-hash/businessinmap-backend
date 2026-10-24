<?php

namespace Tests\Feature;

use App\Models\AllocationReleaseRequest;
use App\Models\BookableAllocation;
use App\Models\BookableItem;
use App\Models\BookingHold;
use App\Models\BusinessPartnership;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «طلب تحرير غرفة»: a room the hotel promised a partner is not for sale to anyone else. A guest of the hotel who wants it asks; the
 * partner is told and, if it agrees, the room goes back to the hotel and is held for that guest.
 */
class ReleaseRequestsTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const SINGLE_ROOM = 965;

    private User $hotel;
    private User $agency;
    private User $stranger;
    private User $first;
    private User $second;
    private BookableItem $unit;
    private BookableAllocation $allocation;
    private int $serviceId;
    private Carbon $start;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->user(User::TYPE_BUSINESS, true);
        $this->agency = $this->user(User::TYPE_BUSINESS);
        $this->stranger = $this->user(User::TYPE_BUSINESS);
        $this->first = $this->user(User::TYPE_CLIENT);
        $this->second = $this->user(User::TYPE_CLIENT);
        $this->serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');
        $this->start = now()->addDays(50)->startOfDay();

        BusinessServicePrice::create([
            'business_id' => $this->hotel->id, 'child_id' => self::CHILD, 'service_id' => $this->serviceId,
            'bookable_item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        // three rooms of one type; the agency is promised two and has sold none
        $this->unit = BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay',
            'line_option_id' => self::SINGLE_ROOM, 'code' => 'Z-' . uniqid(), 'quantity' => 3, 'is_active' => 1,
        ]);

        $p = BusinessPartnership::create(['owner_business_id' => $this->hotel->id, 'partner_business_id' => $this->agency->id, 'relationship_type' => 'agency', 'status' => 'active', 'approval_required' => true, 'created_by' => $this->hotel->id]);
        $this->allocation = BookableAllocation::create([
            'partnership_id' => $p->id, 'owner_business_id' => $this->hotel->id, 'partner_business_id' => $this->agency->id,
            'bookable_item_id' => $this->unit->id, 'platform_service_id' => $this->serviceId, 'allocation_type' => 'guaranteed',
            'quantity_total' => 2, 'quantity_sold' => 0, 'quantity_reserved' => 0, 'quantity_released' => 0, 'release_days_before' => 0,
            'contract_price' => 800, 'currency' => 'EGP', 'markup_type' => 'none', 'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function user(string $type, bool $hotel = false): User
    {
        return User::create([
            'name' => 'Zz ' . uniqid(), 'email' => 'zz-' . uniqid() . '@test.local', 'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'), 'type' => $type, 'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
            'category_id' => $hotel ? self::ROOT : null, 'category_child_id' => $hotel ? self::CHILD : null,
        ]);
    }

    private function window(): array
    {
        return ['starts_at' => $this->start->toDateTimeString(), 'ends_at' => $this->start->copy()->addDays(2)->toDateTimeString()];
    }

    private function book(User $who, int $rooms = 1)
    {
        Sanctum::actingAs($who);

        return $this->postJson('/api/v2/bookings', ['business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => $rooms] + $this->window());
    }

    private function ask(User $who, int $rooms = 1)
    {
        Sanctum::actingAs($who);

        return $this->postJson('/api/v2/bookings/release-requests', ['bookable_id' => $this->unit->id, 'quantity' => $rooms] + $this->window());
    }

    /** The hotel's own guest takes the one room that is not promised. */
    private function takeTheFreeRoom(): void
    {
        $this->book($this->first)->assertCreated();
    }

    public function test_a_guest_who_wants_a_promised_room_is_offered_to_ask_for_it(): void
    {
        $this->takeTheFreeRoom();

        Sanctum::actingAs($this->second);
        $res = $this->postJson('/api/v2/bookings/holds', ['business_id' => $this->hotel->id, 'bookable_id' => $this->unit->id, 'quantity' => 1] + $this->window());
        $res->assertStatus(409)->assertJsonPath('code', 'held_for_partner')->assertJsonPath('can_request_release', true);

        $this->book($this->second)->assertStatus(422);

        $units = $this->getJson("/api/v2/discovery/units/{$this->hotel->id}?starts_at=" . urlencode($this->window()['starts_at']) . '&ends_at=' . urlencode($this->window()['ends_at']))->json('data.kinds.0.units.0');
        $this->assertTrue($units['held_for_partner']);
    }

    public function test_the_partner_is_told_sees_the_room_but_not_the_guest_and_agrees_so_the_room_is_held_for_the_guest(): void
    {
        $this->takeTheFreeRoom();
        $req = $this->ask($this->second)->assertCreated()->assertJsonPath('data.request.status', 'pending')->json('data.request');

        $this->assertDatabaseHas('app_notifications', ['user_id' => $this->agency->id, 'notifiable_type' => AllocationReleaseRequest::class, 'notifiable_id' => $req['id']]);

        Sanctum::actingAs($this->agency);
        $list = $this->getJson('/api/v2/business/partnerships/release-requests')->assertOk()->json('data.requests');
        $this->assertCount(1, $list);
        $this->assertStringNotContainsString((string) $this->second->name, json_encode($list), 'the partner is told what is asked, not who asks');
        $this->assertArrayNotHasKey('requester_id', $list[0]);

        $this->postJson("/api/v2/business/partnerships/release-requests/{$req['id']}/approve")->assertOk()->assertJsonPath('data.request.status', 'approved');

        $fresh = $this->allocation->fresh();
        $this->assertSame(1, $fresh->availableFor($this->start, $this->start->copy()->addDays(2)), 'for THESE nights one room went back to the hotel and the partner keeps the other');
        $this->assertSame(2, $fresh->availableFor($this->start->copy()->addDays(10), $this->start->copy()->addDays(12)), 'other nights are untouched: rooms are promised per night');
        $this->assertDatabaseHas('app_notifications', ['user_id' => $this->second->id, 'notifiable_type' => AllocationReleaseRequest::class]);
        $this->assertSame(1, BookingHold::query()->where('user_id', $this->second->id)->count(), 'held for the guest, so nobody slips in');

        // and the guest can now book it; a third guest still cannot
        $this->book($this->second)->assertCreated();
        $this->book($this->user(User::TYPE_CLIENT))->assertStatus(422);
    }

    public function test_a_declined_request_tells_the_guest_and_changes_nothing(): void
    {
        $this->takeTheFreeRoom();
        $req = $this->ask($this->second)->assertCreated()->json('data.request');

        Sanctum::actingAs($this->agency);
        $this->postJson("/api/v2/business/partnerships/release-requests/{$req['id']}/decline")->assertOk()->assertJsonPath('data.request.status', 'declined');

        $this->assertSame(0, (int) $this->allocation->fresh()->quantity_released);
        $this->assertSame(0, BookingHold::query()->where('user_id', $this->second->id)->count());

        Sanctum::actingAs($this->second);
        $this->getJson("/api/v2/bookings/release-requests/{$req['id']}")->assertOk()->assertJsonPath('data.request.status', 'declined');
        $this->book($this->second)->assertStatus(422);
    }

    public function test_only_a_partner_that_was_asked_can_answer_and_an_unanswered_request_expires(): void
    {
        $this->takeTheFreeRoom();
        $req = $this->ask($this->second)->assertCreated()->json('data.request');

        Sanctum::actingAs($this->stranger);
        $this->assertSame([], $this->getJson('/api/v2/business/partnerships/release-requests')->assertOk()->json('data.requests'));
        $this->postJson("/api/v2/business/partnerships/release-requests/{$req['id']}/approve")->assertNotFound();
        Sanctum::actingAs($this->hotel);
        $this->postJson("/api/v2/business/partnerships/release-requests/{$req['id']}/approve")->assertNotFound();

        // another guest cannot read it
        Sanctum::actingAs($this->first);
        $this->getJson("/api/v2/bookings/release-requests/{$req['id']}")->assertNotFound();

        Carbon::setTestNow(now()->addMinutes(11));
        Sanctum::actingAs($this->second);
        $this->getJson("/api/v2/bookings/release-requests/{$req['id']}")->assertJsonPath('data.request.status', 'expired');
        Sanctum::actingAs($this->agency);
        $this->postJson("/api/v2/business/partnerships/release-requests/{$req['id']}/approve")->assertNotFound();
    }

    public function test_a_release_is_only_asked_when_a_partners_promise_is_the_only_thing_in_the_way(): void
    {
        // the room is free: no need
        $this->ask($this->first)->assertStatus(422);

        // taken by the hotel's own guests and the partner holds the rest: asking is right
        $this->takeTheFreeRoom();
        $this->ask($this->second)->assertCreated();

        // asking twice is one request, not two
        $this->ask($this->second)->assertCreated();
        $this->assertSame(1, AllocationReleaseRequest::query()->where('requester_id', $this->second->id)->count());

        // no partner holds anything unsold: nobody to ask
        $this->allocation->update(['quantity_released' => 2]);
        $this->ask($this->user(User::TYPE_CLIENT))->assertStatus(422);
    }

    public function test_a_guest_cannot_flood_partners_with_requests_for_many_rooms(): void
    {
        $this->takeTheFreeRoom();

        // three requests open on other rooms of the hotel
        foreach (range(1, 3) as $i) {
            $other = BookableItem::create(['business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'code' => 'F-' . $i . uniqid(), 'quantity' => 1, 'is_active' => 1]);
            AllocationReleaseRequest::create(['bookable_item_id' => $other->id, 'requester_id' => $this->second->id, 'seller_business_id' => $this->hotel->id, 'starts_at' => $this->start, 'ends_at' => $this->start->copy()->addDays(2), 'quantity' => 1, 'status' => 'pending', 'expires_at' => now()->addMinutes(5)]);
        }

        $this->ask($this->second)->assertStatus(422)->assertJsonValidationErrors(['bookable_id']);
    }
}
