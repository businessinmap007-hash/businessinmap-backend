<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «أسعار للمصريين والمقيمين فقط»: a hotel sells the same rooms at a local rate and a tourist rate. A room type says who it is sold to;
 * the guest declares which they are when booking and the hotel checks the papers on arrival.
 */
class GuestScopeRatesTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const SINGLE_ROOM = 965;
    private const DOUBLE_ROOM = 966;

    private User $hotel;
    private User $guest;
    private BookableItem $localRoom;
    private BookableItem $touristRoom;
    private BookableItem $openRoom;
    private int $serviceId;
    private Carbon $start;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->user(User::TYPE_BUSINESS, true);
        $this->guest = $this->user(User::TYPE_CLIENT);
        $this->serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');
        $this->start = now()->addDays(30)->startOfDay();

        foreach ([self::SINGLE_ROOM => 700, self::DOUBLE_ROOM => 1000] as $line => $price) {
            BusinessServicePrice::create([
                'business_id' => $this->hotel->id, 'child_id' => self::CHILD, 'service_id' => $this->serviceId,
                'bookable_item_type' => 'booking_stay', 'line_option_id' => $line, 'price' => $price, 'currency' => 'EGP', 'is_active' => 1,
            ]);
        }

        $make = fn (string $code, int $line, string $scope) => BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay',
            'line_option_id' => $line, 'code' => $code . uniqid(), 'quantity' => 3, 'is_active' => 1, 'guest_scope' => $scope,
        ]);

        $this->localRoom = $make('L-', self::SINGLE_ROOM, 'locals');
        $this->touristRoom = $make('T-', self::SINGLE_ROOM, 'foreigners');
        $this->openRoom = $make('O-', self::DOUBLE_ROOM, 'all');
    }

    private function user(string $type, bool $hotel = false): User
    {
        return User::create([
            'name' => 'Zz ' . uniqid(), 'email' => 'zz-' . uniqid() . '@test.local', 'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'), 'type' => $type, 'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
            'category_id' => $hotel ? self::ROOT : null, 'category_child_id' => $hotel ? self::CHILD : null,
        ]);
    }

    private function book(BookableItem $room, ?string $origin, ?User $who = null)
    {
        Sanctum::actingAs($who ?? $this->guest);

        return $this->postJson('/api/v2/bookings', array_filter([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'bookable_id' => $room->id, 'quantity' => 1,
            'starts_at' => $this->start->toDateTimeString(), 'ends_at' => $this->start->copy()->addDays(2)->toDateTimeString(),
            'guest_origin' => $origin,
        ]));
    }

    public function test_a_local_room_needs_the_guest_to_declare_they_are_a_local(): void
    {
        $this->book($this->localRoom, null)->assertStatus(422)->assertJsonValidationErrors(['guest_origin']);
        $this->book($this->localRoom, 'foreign')->assertStatus(422)->assertJsonValidationErrors(['guest_origin']);

        $id = (int) $this->book($this->localRoom, 'local')->assertCreated()->json('data.booking.id') ?: (int) Booking::query()->latest('id')->value('id');
        $this->assertSame('local', Booking::findOrFail($id)->meta['guest_origin'] ?? null, 'the hotel sees what was declared');
    }

    public function test_a_tourist_room_is_for_foreigners_and_an_open_room_asks_nothing(): void
    {
        $this->book($this->touristRoom, 'local')->assertStatus(422)->assertJsonValidationErrors(['guest_origin']);
        $this->book($this->touristRoom, 'foreign')->assertCreated();

        $open = $this->book($this->openRoom, null, $this->user(User::TYPE_CLIENT))->assertCreated();
        $id = (int) $open->json('data.booking.id') ?: (int) Booking::query()->latest('id')->value('id');
        $this->assertNull(Booking::findOrFail($id)->meta['guest_origin'] ?? null);
    }

    public function test_the_hotel_page_and_the_search_show_the_scope_and_filter_by_who_is_asking(): void
    {
        Sanctum::actingAs($this->guest);

        $all = $this->getJson("/api/v2/discovery/units/{$this->hotel->id}")->assertOk()->json('data.kinds');
        $units = collect($all)->flatMap(fn ($k) => $k['units'])->keyBy('id');
        $this->assertSame('locals', $units[$this->localRoom->id]['guest_scope']);
        $this->assertSame('foreigners', $units[$this->touristRoom->id]['guest_scope']);

        $asLocal = collect($this->getJson("/api/v2/discovery/units/{$this->hotel->id}?guest_origin=local")->json('data.kinds'))->flatMap(fn ($k) => $k['units'])->pluck('id')->all();
        $this->assertContains($this->localRoom->id, $asLocal);
        $this->assertNotContains($this->touristRoom->id, $asLocal);

        $asForeign = collect($this->getJson("/api/v2/discovery/units/{$this->hotel->id}?guest_origin=foreign")->json('data.kinds'))->flatMap(fn ($k) => $k['units'])->pluck('id')->all();
        $this->assertContains($this->touristRoom->id, $asForeign);
        $this->assertNotContains($this->localRoom->id, $asForeign);
        $this->assertContains($this->openRoom->id, $asForeign);
    }

    public function test_the_hotel_board_shows_what_each_guest_declared(): void
    {
        $this->book($this->localRoom, 'local')->assertCreated();

        Sanctum::actingAs($this->hotel);
        $board = $this->getJson('/api/v2/business/occupancy?month=' . $this->start->format('Y-m'))->assertOk()->json('data');
        $local = collect($board['kinds'])->flatMap(fn ($k) => $k['units'])->firstWhere('id', $this->localRoom->id);

        $this->assertSame('locals', $local['guest_scope']);
        $this->assertSame('local', $local['bookings'][0]['guest_origin']);
    }

    public function test_the_hotel_sets_the_scope_and_an_edit_that_does_not_send_it_keeps_it(): void
    {
        Sanctum::actingAs($this->hotel);

        $body = ['service_id' => $this->serviceId, 'item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'code' => 'E-' . uniqid(), 'quantity' => 2];
        $created = $this->postJson('/api/v2/business/bookable-items', $body + ['guest_scope' => 'locals']);

        if ($created->status() === 422) {
            $this->markTestSkipped('the dev hotel child does not allow this item type: ' . json_encode($created->json('errors')));
        }

        $created->assertCreated()->assertJsonPath('data.guest_scope', 'locals');
        $id = (int) $created->json('data.id');

        $this->putJson("/api/v2/business/bookable-items/{$id}", $body + ['code' => $body['code']])->assertOk()->assertJsonPath('data.guest_scope', 'locals');
        $this->putJson("/api/v2/business/bookable-items/{$id}", $body + ['guest_scope' => 'all'])->assertOk()->assertJsonPath('data.guest_scope', 'all');
        $this->putJson("/api/v2/business/bookable-items/{$id}", $body + ['guest_scope' => 'nonsense'])->assertStatus(422);
    }
}
