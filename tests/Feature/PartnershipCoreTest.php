<?php

namespace Tests\Feature;

use App\Models\BookableAllocation;
use App\Models\BookableItem;
use App\Models\BusinessPartnership;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «الشراكات والحصص» as a core: a contract between two businesses that ONLY the two can read. The first contract kind is a room
 * allocation — a hotel gives a travel agency some rooms at a contract price; the agency prices them itself (never below) and sells
 * them on its own page, with the room's card for a trip programme.
 */
class PartnershipCoreTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const SINGLE_ROOM = 965;

    private User $hotel;
    private User $agency;
    private User $stranger;
    private User $guest;
    private BookableItem $unit;
    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->user(User::TYPE_BUSINESS, true);
        $this->agency = $this->user(User::TYPE_BUSINESS);
        $this->stranger = $this->user(User::TYPE_BUSINESS);
        $this->guest = $this->user(User::TYPE_CLIENT);
        $this->serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');

        BusinessServicePrice::create([
            'business_id' => $this->hotel->id, 'child_id' => self::CHILD, 'service_id' => $this->serviceId,
            'bookable_item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        $this->unit = BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay',
            'line_option_id' => self::SINGLE_ROOM, 'code' => 'Z-' . uniqid(), 'quantity' => 5, 'is_active' => 1,
        ]);
    }

    private function user(string $type, bool $hotel = false): User
    {
        return User::create([
            'name' => 'Zz ' . uniqid(), 'email' => 'zz-' . uniqid() . '@test.local', 'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'), 'type' => $type, 'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
            'category_id' => $hotel ? self::ROOT : null, 'category_child_id' => $hotel ? self::CHILD : null,
        ]);
    }

    /** An accepted partnership with one grant, the way the two businesses would make it. @return array{0:int,1:int} [partnership, allocation] */
    private function granted(int $quantity = 3, float $contract = 800): array
    {
        Sanctum::actingAs($this->hotel);
        $pid = (int) $this->postJson('/api/v2/business/partnerships', ['partner_business_id' => $this->agency->id, 'type' => 'agency'])->assertCreated()->json('data.partnership.id');

        Sanctum::actingAs($this->agency);
        $this->postJson("/api/v2/business/partnerships/{$pid}/accept")->assertOk();

        Sanctum::actingAs($this->hotel);
        $aid = (int) $this->postJson("/api/v2/business/partnerships/{$pid}/allocations", [
            'bookable_item_id' => $this->unit->id, 'quantity_total' => $quantity, 'contract_price' => $contract,
        ])->assertCreated()->json('data.allocation.id');

        return [$pid, $aid];
    }

    public function test_the_owner_invites_and_only_the_invited_business_can_answer(): void
    {
        Sanctum::actingAs($this->hotel);
        $res = $this->postJson('/api/v2/business/partnerships', ['partner_business_id' => $this->agency->id])->assertCreated();
        $pid = $res->json('data.partnership.id');
        $this->assertSame('owner', $res->json('data.partnership.role'));
        $this->assertSame('pending', $res->json('data.partnership.status'));

        // the owner cannot accept its own invitation
        $this->postJson("/api/v2/business/partnerships/{$pid}/accept")->assertStatus(422);

        Sanctum::actingAs($this->agency);
        $mine = $this->getJson('/api/v2/business/partnerships')->assertOk()->json('data.partnerships');
        $this->assertTrue($mine[0]['awaiting_my_answer']);
        $this->assertSame('partner', $mine[0]['role']);
        $this->postJson("/api/v2/business/partnerships/{$pid}/accept")->assertOk()->assertJsonPath('data.partnership.status', 'active');
    }

    public function test_a_third_business_and_a_customer_get_404_on_everything(): void
    {
        [$pid, $aid] = $this->granted();

        // a customer is turned away at the door, whichever way — never shown the partnership
        Sanctum::actingAs($this->guest);
        $this->assertContains($this->getJson("/api/v2/business/partnerships/{$pid}")->status(), [403, 404]);

        Sanctum::actingAs($this->stranger);
        $this->getJson("/api/v2/business/partnerships/{$pid}")->assertNotFound();
        $this->postJson("/api/v2/business/partnerships/{$pid}/pause")->assertNotFound();
        $this->putJson("/api/v2/business/partnerships/{$pid}/allocations/{$aid}/pricing", ['markup_type' => 'percent', 'markup_value' => 10])->assertNotFound();
        $this->assertSame([], $this->getJson('/api/v2/business/partnerships')->assertOk()->json('data.partnerships'));
    }

    public function test_the_agency_cannot_grant_and_the_hotel_cannot_price(): void
    {
        [$pid, $aid] = $this->granted();

        Sanctum::actingAs($this->agency);
        $this->postJson("/api/v2/business/partnerships/{$pid}/allocations", ['bookable_item_id' => $this->unit->id, 'quantity_total' => 1, 'contract_price' => 500])->assertNotFound();
        $this->putJson("/api/v2/business/partnerships/{$pid}/allocations/{$aid}", ['contract_price' => 1])->assertNotFound();

        Sanctum::actingAs($this->hotel);
        $this->putJson("/api/v2/business/partnerships/{$pid}/allocations/{$aid}/pricing", ['markup_type' => 'percent', 'markup_value' => 10])->assertNotFound();
    }

    public function test_a_grant_cannot_exceed_the_room_and_needs_an_active_partnership(): void
    {
        [$pid] = $this->granted(3);

        Sanctum::actingAs($this->hotel);
        $this->postJson("/api/v2/business/partnerships/{$pid}/allocations", ['bookable_item_id' => $this->unit->id, 'quantity_total' => 3, 'contract_price' => 800])->assertStatus(422);

        // another business's room is not the hotel's to grant
        $other = BookableItem::create(['business_id' => $this->stranger->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'code' => 'Y-' . uniqid(), 'quantity' => 2, 'is_active' => 1]);
        $this->postJson("/api/v2/business/partnerships/{$pid}/allocations", ['bookable_item_id' => $other->id, 'quantity_total' => 1, 'contract_price' => 800])->assertStatus(422);

        $this->postJson("/api/v2/business/partnerships/{$pid}/pause")->assertOk();
        $this->postJson("/api/v2/business/partnerships/{$pid}/allocations", ['bookable_item_id' => $this->unit->id, 'quantity_total' => 1, 'contract_price' => 800])->assertStatus(422);
    }

    public function test_the_partner_prices_above_the_contract_price_never_below_it(): void
    {
        [$pid, $aid] = $this->granted(3, 800);

        Sanctum::actingAs($this->agency);
        $this->putJson("/api/v2/business/partnerships/{$pid}/allocations/{$aid}/pricing", ['markup_type' => 'percent', 'markup_value' => 25])
            ->assertOk()->assertJsonPath('data.allocation.final_price', 1000)->assertJsonPath('data.allocation.contract_price', 800);
        $this->putJson("/api/v2/business/partnerships/{$pid}/allocations/{$aid}/pricing", ['markup_type' => 'fixed', 'markup_value' => 150])
            ->assertOk()->assertJsonPath('data.allocation.final_price', 950);
        $this->putJson("/api/v2/business/partnerships/{$pid}/allocations/{$aid}/pricing", ['markup_type' => 'fixed', 'markup_value' => -1])->assertStatus(422);
        $this->putJson("/api/v2/business/partnerships/{$pid}/allocations/{$aid}/pricing", ['markup_type' => 'none'])
            ->assertOk()->assertJsonPath('data.allocation.final_price', 800);
    }

    public function test_the_agencys_page_lists_the_rooms_it_was_given_and_never_the_contract(): void
    {
        [$pid, $aid] = $this->granted(3, 800);

        Sanctum::actingAs($this->agency);
        $this->putJson("/api/v2/business/partnerships/{$pid}/allocations/{$aid}/pricing", ['markup_type' => 'fixed', 'markup_value' => 200])->assertOk();

        // a customer opens the AGENCY's page
        Sanctum::actingAs($this->guest);
        $res = $this->getJson("/api/v2/discovery/units/{$this->agency->id}")->assertOk();
        $rooms = $res->json('data.partner_rooms');

        $this->assertCount(1, $rooms);
        $this->assertEquals(1000.0, $rooms[0]['price']);
        $this->assertSame($this->unit->id, $rooms[0]['bookable_id']);
        $this->assertSame($this->hotel->id, $rooms[0]['from']['id']);
        $this->assertSame(3, $rooms[0]['available_quantity']);
        $this->assertArrayHasKey('images', $rooms[0]);

        $raw = json_encode($res->json('data'));
        foreach (['contract_price', 'markup', 'quantity_total', 'quantity_sold', 'terms'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw, "{$secret} is the two parties' alone");
        }

        // and a paused partnership takes the rooms off the page
        Sanctum::actingAs($this->hotel);
        $this->postJson("/api/v2/business/partnerships/{$pid}/pause")->assertOk();
        Sanctum::actingAs($this->guest);
        $this->assertSame([], $this->getJson("/api/v2/discovery/units/{$this->agency->id}")->json('data.partner_rooms'));
    }

    public function test_a_guest_books_the_allocated_room_through_the_agency_at_its_price(): void
    {
        [$pid, $aid] = $this->granted(3, 800);

        Sanctum::actingAs($this->agency);
        $this->putJson("/api/v2/business/partnerships/{$pid}/allocations/{$aid}/pricing", ['markup_type' => 'fixed', 'markup_value' => 200])->assertOk();

        $from = now()->addDays(10)->startOfDay();
        Sanctum::actingAs($this->guest);
        $res = $this->postJson('/api/v2/bookings/preview', [
            'business_id' => $this->agency->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => 1,
            'starts_at' => $from->toDateTimeString(), 'ends_at' => $from->copy()->addDays(2)->toDateTimeString(),
        ]);
        $res->assertOk();
        $b = (array) ($res->json('data.price_breakdown') ?? $res->json('data'));
        $this->assertEquals(2000.0, $b['final_price'], 'two nights at the agency\'s 1000, not the hotel\'s 1000 list price nor the 800 contract price');
    }

    public function test_ending_the_partnership_stops_what_was_given(): void
    {
        [$pid, $aid] = $this->granted();

        Sanctum::actingAs($this->agency);
        $this->postJson("/api/v2/business/partnerships/{$pid}/end")->assertOk()->assertJsonPath('data.partnership.status', 'cancelled');
        $this->assertSame(BookableAllocation::STATUS_STOPPED, BookableAllocation::find($aid)->status);
        $this->postJson("/api/v2/business/partnerships/{$pid}/resume")->assertStatus(422);
    }

    public function test_the_room_card_for_a_trip_programme_carries_the_room_but_not_the_price(): void
    {
        [$pid] = $this->granted();

        Sanctum::actingAs($this->agency);
        $room = $this->getJson("/api/v2/business/partnerships/{$pid}")->assertOk()->json('data.allocations.0.room');
        $this->assertSame($this->unit->id, $room['unit_id']);
        foreach (['label', 'kind', 'images', 'features'] as $key) {
            $this->assertArrayHasKey($key, $room);
        }
        $this->assertArrayNotHasKey('contract_price', $room);
    }
}
