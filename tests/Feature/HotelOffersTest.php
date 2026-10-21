<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «عروض الحجز»: a hotel's offer on its rooms comes off the nights of a booking — a percentage, an amount, or «ادفع ليلتين وأقم
 * ثلاثًا» — optionally only for a season, and shows on the kind's card.
 */
class HotelOffersTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const SINGLE_ROOM = 965;

    private User $hotel;
    private User $guest;
    private BookableItem $unit;
    private BusinessServicePrice $price;
    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->user(User::TYPE_BUSINESS, true);
        $this->guest = $this->user(User::TYPE_CLIENT);
        $this->serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');

        $this->price = BusinessServicePrice::create([
            'business_id' => $this->hotel->id, 'child_id' => self::CHILD, 'service_id' => $this->serviceId,
            'bookable_item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        $this->unit = BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay',
            'line_option_id' => self::SINGLE_ROOM, 'code' => 'Z-' . uniqid(), 'quantity' => 2, 'is_active' => 1,
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

    private function promo(string $kind, array $params, array $targets, array $extra = []): Promotion
    {
        $p = Promotion::create($extra + ['business_id' => $this->hotel->id, 'kind' => $kind, 'params' => $params, 'audience' => 'all', 'ends_at' => now()->addDays(30), 'is_active' => true]);
        foreach ($targets as [$type, $id]) {
            $p->targets()->create(['target_type' => $type, 'target_id' => $id]);
        }

        return $p;
    }

    private function body(int $nights, int $startInDays, int $rooms): array
    {
        $from = now()->addDays($startInDays)->startOfDay();

        return [
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => $rooms,
            'starts_at' => $from->toDateTimeString(), 'ends_at' => $from->copy()->addDays($nights)->toDateTimeString(),
        ];
    }

    /** The price breakdown the preview shows for a stay. */
    private function breakdown(int $nights = 3, int $startInDays = 10, int $rooms = 1): array
    {
        Sanctum::actingAs($this->guest);
        $res = $this->postJson('/api/v2/bookings/preview', $this->body($nights, $startInDays, $rooms));
        $res->assertOk();

        return (array) ($res->json('data.price_breakdown') ?? $res->json('data.pricing') ?? $res->json('data'));
    }

    public function test_a_percentage_on_a_kind_of_room_comes_off_the_nights(): void
    {
        $this->promo(Promotion::PERCENT, ['value' => 20], [[Promotion::TARGET_BOOKABLE_KIND, self::SINGLE_ROOM]]);

        $b = $this->breakdown(3);

        $this->assertEquals(3000.0, $b['original_price']);
        $this->assertEquals(600.0, $b['offer_discount_amount']);
        $this->assertEquals(2400.0, $b['final_price']);
    }

    public function test_pay_two_nights_stay_three(): void
    {
        $this->promo(Promotion::N_FOR_PRICE_OF, ['n' => 3, 'pay' => 2], [[Promotion::TARGET_BOOKABLE_ITEM, $this->unit->id]]);

        $this->assertEquals(2000.0, $this->breakdown(3)['final_price']);
        $this->assertEquals(3000.0, $this->breakdown(4)['final_price'], 'the fourth night is a new, incomplete group');
        $this->assertEquals(2000.0, $this->breakdown(2)['final_price'], 'two nights make no group');
        $this->assertEquals(4000.0, $this->breakdown(3, 10, 2)['final_price'], 'two rooms: every night is two rooms');
    }

    public function test_a_season_limits_the_nights_the_offer_covers(): void
    {
        $day = now()->addDays(11)->toDateString();
        $this->promo(Promotion::PERCENT, ['value' => 50, 'stay_from' => $day, 'stay_to' => $day], [[Promotion::TARGET_ALL, null]]);

        // nights of days 10, 11 and 12: only the night of day 11 is inside the season
        $this->assertEquals(500.0, $this->breakdown(3, 10)['offer_discount_amount']);
    }

    public function test_it_does_not_stack_with_the_stay_discount_the_larger_wins(): void
    {
        app(\App\Services\BookingStayDiscountService::class)->save($this->unit, 10, 20);

        $p = $this->promo(Promotion::PERCENT, ['value' => 5], [[Promotion::TARGET_ALL, null]]);
        $small = $this->breakdown(7);
        $this->assertEquals(0.0, $small['offer_discount_amount'], 'the weekly 10% is larger than the 5% offer');
        $this->assertEquals(6300.0, $small['final_price']);

        $p->update(['params' => ['value' => 30]]);
        $big = $this->breakdown(7);
        $this->assertEquals(2100.0, $big['offer_discount_amount']);
        $this->assertEquals(4900.0, $big['final_price']);
    }

    public function test_a_room_whose_price_rose_is_not_discounted_until_the_quiet_days_pass(): void
    {
        $this->price->update(['price' => 1300]);   // +30%
        $this->promo(Promotion::PERCENT, ['value' => 20], [[Promotion::TARGET_ALL, null]]);

        $this->assertEquals(0.0, $this->breakdown(2)['offer_discount_amount']);

        \App\Models\OfferingPriceChange::query()->where('priceable_id', $this->price->id)->update(['changed_at' => now()->subDays(16)]);
        $this->assertEquals(520.0, $this->breakdown(2)['offer_discount_amount']);
    }

    public function test_a_paused_offer_or_another_hotels_offer_prices_nothing(): void
    {
        $p = $this->promo(Promotion::PERCENT, ['value' => 20], [[Promotion::TARGET_ALL, null]], ['is_active' => false]);
        $this->assertEquals(0.0, $this->breakdown(2)['offer_discount_amount']);

        $p->update(['is_active' => true, 'business_id' => $this->user(User::TYPE_BUSINESS)->id]);
        $this->assertEquals(0.0, $this->breakdown(2)['offer_discount_amount']);
    }

    public function test_the_kinds_card_carries_the_offer_and_a_booking_counts_a_use(): void
    {
        $p = $this->promo(Promotion::PERCENT, ['value' => 20], [[Promotion::TARGET_BOOKABLE_KIND, self::SINGLE_ROOM]], ['ends_at' => null, 'max_uses' => 5]);

        $kinds = $this->getJson("/api/v2/discovery/units/{$this->hotel->id}")->assertOk()->json('data.kinds');
        $card = collect($kinds)->firstWhere('line_option_id', self::SINGLE_ROOM);
        $this->assertEquals(800.0, $card['offer']['price_after']);
        $this->assertSame('percent', $card['offer']['kind']);

        Sanctum::actingAs($this->guest);
        $res = $this->postJson('/api/v2/bookings', $this->body(2, 10, 1));
        $res->assertCreated();

        $this->assertEquals(1600.0, (float) \App\Models\Booking::query()->where('user_id', $this->guest->id)->latest('id')->value('price'));
        $this->assertSame(1, $p->fresh()->used_count);
    }

    public function test_a_hotel_writes_a_room_offer_from_its_catalog_and_only_with_its_own_rooms(): void
    {
        Sanctum::actingAs($this->hotel);
        $catalog = $this->getJson('/api/v2/business/promotions/catalog')->assertOk()->json('data.booking');
        $group = collect($catalog)->firstWhere('id', self::SINGLE_ROOM);
        $this->assertEquals(1000.0, $group['items'][0]['price']);

        $body = ['kind' => 'percent', 'params' => ['value' => 15], 'ends_at' => now()->addWeek()->toDateString(), 'targets' => [['type' => 'bookable_kind', 'id' => self::SINGLE_ROOM]]];
        $this->postJson('/api/v2/business/promotions', $body)->assertCreated()->assertJsonPath('data.promotion.targets.0.type', 'bookable_kind');

        // a minimum order has no meaning for a night; a room that is not his is refused
        $this->postJson('/api/v2/business/promotions', ['kind' => 'spend_percent', 'params' => ['min_total' => 500, 'value' => 10]] + $body)->assertStatus(422);
        $rival = $this->user(User::TYPE_BUSINESS, true);
        $theirs = BookableItem::create(['business_id' => $rival->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'code' => 'R-' . uniqid(), 'quantity' => 1, 'is_active' => 1]);
        $this->postJson('/api/v2/business/promotions', ['targets' => [['type' => 'bookable_item', 'id' => $theirs->id]]] + $body)->assertStatus(422);
    }
}
