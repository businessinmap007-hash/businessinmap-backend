<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\CommercialOffer;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * «غرف الفنادق بتصنيف معين»: pick the class (stars), the governorate and the city and see the rooms of the matching
 * hotels, cheapest first — with a partner company's offer on the same room listed beside the hotel's own price.
 * Rolls back.
 */
class HotelRoomSearchTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const SINGLE_ROOM = 965;

    private int $five;
    private int $unclassified;
    private int $govA;
    private int $govB;
    private int $cityA;
    private int $cityA2;

    protected function setUp(): void
    {
        parent::setUp();

        $classes = DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')->where('g.name_ar', 'تصنيف الإقامة')->pluck('o.id', 'o.name_ar');
        $this->five = (int) ($classes['خمس نجوم'] ?? 0);
        $this->unclassified = (int) ($classes['غير مصنّف'] ?? 0);

        if ($this->five <= 0 || $this->unclassified <= 0) {
            $this->markTestSkipped('Needs the class list of «تصنيف الإقامة».');
        }

        $this->govA = (int) DB::table('governorates')->orderBy('id')->value('id');
        $this->govB = (int) DB::table('governorates')->where('id', '!=', $this->govA)->orderBy('id')->value('id');
        $cities = DB::table('cities')->where('governorate_id', $this->govA)->orderBy('id')->limit(2)->pluck('id');
        [$this->cityA, $this->cityA2] = [(int) $cities[0], (int) $cities[1]];
    }

    private function hotel(string $tag, int $gov, int $city, ?int $classOption, float $price): array
    {
        $hotel = User::create([
            'name' => $tag . ' ' . uniqid(),
            'email' => 'zz-hotel-' . uniqid() . '@test.local',
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => self::ROOT,
            'category_child_id' => self::CHILD,
            'governorate_id' => $gov,
            'city_id' => $city,
            'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ]);

        $serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');

        BusinessServicePrice::create([
            'business_id' => $hotel->id, 'child_id' => self::CHILD, 'service_id' => $serviceId,
            'bookable_item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM,
            'price' => $price, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        $unit = BookableItem::create([
            'business_id' => $hotel->id, 'service_id' => $serviceId, 'item_type' => 'booking_stay',
            'line_option_id' => self::SINGLE_ROOM, 'code' => 'Z-' . uniqid(), 'quantity' => 1, 'is_active' => 1,
        ]);

        if ($classOption) {
            DB::table('option_user')->insert(['user_id' => $hotel->id, 'option_id' => $classOption]);
        }

        return [$hotel, $unit];
    }

    private function rooms(array $query = []): array
    {
        return $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/discovery/hotel-rooms?' . http_build_query($query))->assertOk()->json('data.data');
    }

    private function mine(array $rows, array $hotels): array
    {
        $ids = array_map(fn ($h) => $h->id, $hotels);

        return array_values(array_filter($rows, fn ($r) => in_array($r['hotel']['id'], $ids, true)));
    }

    public function test_the_class_filters_the_hotels_and_a_room_inherits_its_hotels_class(): void
    {
        [$five, ] = $this->hotel('Five', $this->govA, $this->cityA, $this->five, 1800);
        [$plain, ] = $this->hotel('Plain', $this->govA, $this->cityA, null, 700);

        $rows = $this->mine($this->rooms(['class_option_id' => $this->five]), [$five, $plain]);

        $this->assertCount(1, $rows);
        $this->assertSame($five->id, $rows[0]['hotel']['id']);
        $this->assertSame('خمس نجوم', $rows[0]['hotel']['class']['name']);
        $this->assertEquals(1800, $rows[0]['price']);

        // a hotel that ticked nothing is «غير مصنّف»
        $rows = $this->mine($this->rooms(['class_option_id' => $this->unclassified]), [$five, $plain]);
        $this->assertSame([$plain->id], array_column(array_column($rows, 'hotel'), 'id'));
    }

    public function test_governorate_and_city_narrow_it_and_the_cheapest_comes_first(): void
    {
        [$a1, ] = $this->hotel('A1', $this->govA, $this->cityA, $this->five, 2500);
        [$a2, ] = $this->hotel('A2', $this->govA, $this->cityA2, $this->five, 1500);
        [$b, ] = $this->hotel('B', $this->govB, (int) DB::table('cities')->where('governorate_id', $this->govB)->value('id'), $this->five, 900);

        $all = $this->mine($this->rooms(['class_option_id' => $this->five]), [$a1, $a2, $b]);
        $this->assertSame([$b->id, $a2->id, $a1->id], array_column(array_column($all, 'hotel'), 'id'), 'cheapest first');

        $gov = $this->mine($this->rooms(['class_option_id' => $this->five, 'governorate_id' => $this->govA]), [$a1, $a2, $b]);
        $this->assertSame([$a2->id, $a1->id], array_column(array_column($gov, 'hotel'), 'id'));

        $city = $this->mine($this->rooms(['class_option_id' => $this->five, 'governorate_id' => $this->govA, 'city_id' => $this->cityA]), [$a1, $a2, $b]);
        $this->assertSame([$a1->id], array_column(array_column($city, 'hotel'), 'id'));

        $cheap = $this->mine($this->rooms(['class_option_id' => $this->five, 'price_max' => 1600]), [$a1, $a2, $b]);
        $this->assertSame([$b->id, $a2->id], array_column(array_column($cheap, 'hotel'), 'id'));
    }

    public function test_the_filters_list_only_what_a_live_hotel_offers(): void
    {
        [$h, ] = $this->hotel('F', $this->govA, $this->cityA, $this->five, 1000);

        $f = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/discovery/hotel-rooms/filters?governorate_id=' . $this->govA)->assertOk()->json('data');

        $this->assertContains($this->five, array_column($f['classes'], 'id'));
        $this->assertContains($this->govA, array_column($f['governorates'], 'id'));
        $this->assertContains($this->cityA, array_column($f['cities'], 'id'));
        $this->assertNotContains(PHP_INT_MAX, array_column($f['classes'], 'id'));
    }

    public function test_a_partners_offer_on_the_same_room_is_listed_and_the_cheapest_leads(): void
    {
        [$hotel, $unit] = $this->hotel('Quota', $this->govA, $this->cityA, $this->five, 2000);
        $agency = User::create([
            'name' => 'Agency ' . uniqid(), 'email' => 'zz-agency-' . uniqid() . '@test.local', 'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'), 'type' => User::TYPE_BUSINESS, 'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ]);

        $offer = fn (array $extra = []) => CommercialOffer::create($extra + [
            'offerable_type' => CommercialOffer::OFFERABLE_BOOKABLE_ITEM, 'offerable_id' => $unit->id,
            'owner_business_id' => $hotel->id, 'seller_business_id' => $agency->id,
            'source_type' => CommercialOffer::SOURCE_ALLOCATION, 'source_id' => random_int(100000, 999999),
            'audience_type' => CommercialOffer::AUDIENCE_BOTH, 'title_ar' => 'room', 'title_en' => 'room',
            'base_price' => 1500, 'final_price' => 1650, 'currency' => 'EGP',
            'availability_mode' => CommercialOffer::AVAILABILITY_LIMITED, 'available_quantity' => 3, 'status' => CommercialOffer::STATUS_ACTIVE,
        ]);

        $offer();
        $offer(['final_price' => 1200, 'status' => CommercialOffer::STATUS_PAUSED, 'source_id' => random_int(100000, 999999)]);   // paused: never shown
        $offer(['final_price' => 1100, 'available_quantity' => 0, 'source_id' => random_int(100000, 999999)]);                    // sold out
        $offer(['final_price' => 1000, 'audience_type' => CommercialOffer::AUDIENCE_B2B, 'source_id' => random_int(100000, 999999)]); // trade only

        $row = $this->mine($this->rooms(['class_option_id' => $this->five]), [$hotel])[0];

        $this->assertEquals(2000, $row['price'], "the hotel's own price");
        $this->assertCount(1, $row['partner_offers'], 'only the live offer a customer may see');
        $this->assertEquals(1650, $row['partner_offers'][0]['price']);
        $this->assertSame($agency->id, $row['partner_offers'][0]['seller']['id']);
        $this->assertEquals(1650, $row['best_price'], 'the list is sorted on the cheapest of the two');
    }

    public function test_with_dates_only_free_rooms_are_listed_with_the_total_of_the_stay_and_they_carry_what_a_booking_needs(): void
    {
        [$free, $freeUnit] = $this->hotel('Free', $this->govA, $this->cityA, $this->five, 1000);
        [$busy, $busyUnit] = $this->hotel('Busy', $this->govA, $this->cityA, $this->five, 800);

        $from = now()->addDays(10)->startOfDay();
        $to = $from->copy()->addDays(3);

        \App\Models\BookableItemBlockedSlot::create([
            'bookable_item_id' => $busyUnit->id, 'business_id' => $busy->id, 'platform_service_id' => $busyUnit->service_id,
            'block_type' => \App\Models\BookableItemBlockedSlot::TYPE_MAINTENANCE, 'starts_at' => $from->copy()->addDay(), 'ends_at' => $to->copy()->subDay(), 'is_active' => 1,
        ]);

        $rows = $this->mine($this->rooms(['class_option_id' => $this->five, 'starts_at' => $from->toDateString(), 'ends_at' => $to->toDateString()]), [$free, $busy]);

        $this->assertSame([$free->id], array_column(array_column($rows, 'hotel'), 'id'), 'the closed room is not offered for those nights');
        $this->assertEquals(3, $rows[0]['nights']);
        $this->assertEquals(1000, $rows[0]['price']);
        $this->assertEquals(3000, $rows[0]['total']);
        $this->assertSame($freeUnit->id, $rows[0]['room']['unit_id']);
        $this->assertGreaterThan(0, $rows[0]['room']['offering_id']);
        $this->assertSame('booking_stay', $rows[0]['room']['item_type']);

        // without dates both are listed and no total is claimed
        $any = $this->mine($this->rooms(['class_option_id' => $this->five]), [$free, $busy]);
        $this->assertCount(2, $any);
        $this->assertNull($any[0]['total']);
    }

    public function test_a_partner_offer_whose_nights_do_not_fit_the_stay_is_left_out(): void
    {
        [$hotel, $unit] = $this->hotel('Min', $this->govA, $this->cityA, $this->five, 2000);
        $agency = User::create([
            'name' => 'Agency ' . uniqid(), 'email' => 'zz-agency-' . uniqid() . '@test.local', 'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'), 'type' => User::TYPE_BUSINESS, 'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ]);

        CommercialOffer::create([
            'offerable_type' => CommercialOffer::OFFERABLE_BOOKABLE_ITEM, 'offerable_id' => $unit->id,
            'owner_business_id' => $hotel->id, 'seller_business_id' => $agency->id,
            'source_type' => CommercialOffer::SOURCE_ALLOCATION, 'source_id' => random_int(100000, 999999),
            'audience_type' => CommercialOffer::AUDIENCE_BOTH, 'title_ar' => 'room', 'title_en' => 'room',
            'base_price' => 1500, 'final_price' => 1650, 'currency' => 'EGP', 'availability_mode' => CommercialOffer::AVAILABILITY_LIMITED,
            'available_quantity' => 3, 'status' => CommercialOffer::STATUS_ACTIVE, 'meta' => ['min_nights' => 3],
        ]);

        $from = now()->addDays(20)->startOfDay();
        $two = $this->mine($this->rooms(['class_option_id' => $this->five, 'starts_at' => $from->toDateString(), 'ends_at' => $from->copy()->addDays(2)->toDateString()]), [$hotel]);
        $four = $this->mine($this->rooms(['class_option_id' => $this->five, 'starts_at' => $from->toDateString(), 'ends_at' => $from->copy()->addDays(4)->toDateString()]), [$hotel]);

        $this->assertCount(0, $two[0]['partner_offers'], 'two nights is under the allocation minimum of three');
        $this->assertCount(1, $four[0]['partner_offers']);
    }
}
