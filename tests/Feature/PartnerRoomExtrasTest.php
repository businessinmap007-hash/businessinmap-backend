<?php

namespace Tests\Feature;

use App\Models\BookableAllocation;
use App\Models\BookableItem;
use App\Models\BusinessPartnership;
use App\Models\BusinessServicePrice;
use App\Models\OfferingOption;
use App\Models\Option;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A company that sells a hotel's room through an allocation sells it with the hotel's own add-ons (the meal plan, at the hotel's listed
 * price) and may run its OWN offers on it — which come off the company's margin and never take a night below the contract price.
 */
class PartnerRoomExtrasTest extends TestCase
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
    private BookableAllocation $allocation;
    private Option $meal;
    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->user(User::TYPE_BUSINESS, true);
        $this->agency = $this->user(User::TYPE_BUSINESS);
        $this->stranger = $this->user(User::TYPE_BUSINESS);
        $this->guest = $this->user(User::TYPE_CLIENT);
        $this->serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');

        $row = BusinessServicePrice::create([
            'business_id' => $this->hotel->id, 'child_id' => self::CHILD, 'service_id' => $this->serviceId,
            'bookable_item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);
        $row->syncOfferingOptions(self::SINGLE_ROOM, []);

        $this->unit = BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay',
            'line_option_id' => self::SINGLE_ROOM, 'code' => 'Z-' . uniqid(), 'quantity' => 5, 'is_active' => 1,
        ]);
        $this->unit->syncOfferingOptions(self::SINGLE_ROOM, []);

        // the hotel's meal plan: written once for the whole business, 100 a night
        $this->meal = Option::query()->where('name_ar', 'like', '%إفطار%')->first() ?? $this->markTestSkipped('no breakfast option in the dev vocabulary');
        OfferingOption::create([
            'offering_type' => (new User)->getMorphClass(), 'offering_id' => $this->hotel->id, 'option_id' => $this->meal->id,
            'role' => OfferingOption::ROLE_MODIFIER, 'adjust_type' => OfferingOption::ADJUST_AMOUNT, 'adjust_value' => 100, 'per_person' => false, 'sort_order' => 1,
        ]);

        $p = BusinessPartnership::create(['owner_business_id' => $this->hotel->id, 'partner_business_id' => $this->agency->id, 'relationship_type' => 'agency', 'status' => 'active', 'approval_required' => true, 'created_by' => $this->hotel->id]);

        // contract 800, the company sells at 1000
        $this->allocation = BookableAllocation::create([
            'partnership_id' => $p->id, 'owner_business_id' => $this->hotel->id, 'partner_business_id' => $this->agency->id,
            'bookable_item_id' => $this->unit->id, 'platform_service_id' => $this->serviceId, 'allocation_type' => 'guaranteed',
            'quantity_total' => 3, 'quantity_sold' => 0, 'quantity_reserved' => 0, 'quantity_released' => 0, 'release_days_before' => 0,
            'contract_price' => 800, 'currency' => 'EGP', 'markup_type' => 'fixed', 'markup_value' => 200, 'status' => 'active',
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

    private function preview(int $nights = 2, array $extra = []): array
    {
        $from = now()->addDays(20)->startOfDay();
        Sanctum::actingAs($this->guest);

        $res = $this->postJson('/api/v2/bookings/preview', $extra + [
            'business_id' => $this->agency->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => 1,
            'starts_at' => $from->toDateTimeString(), 'ends_at' => $from->copy()->addDays($nights)->toDateTimeString(),
        ])->assertOk();

        return (array) ($res->json('data.price_breakdown') ?? $res->json('data'));
    }

    private function offer(array $overrides = [])
    {
        Sanctum::actingAs($this->agency);

        return $this->postJson('/api/v2/business/promotions', $overrides + [
            'kind' => 'percent', 'params' => ['value' => 50], 'max_uses' => 10,
            'targets' => [['type' => 'bookable_item', 'id' => $this->unit->id]],
        ]);
    }

    public function test_the_partner_room_carries_the_hotels_meal_plan_at_the_hotels_price(): void
    {
        Sanctum::actingAs($this->guest);
        $room = $this->getJson("/api/v2/discovery/units/{$this->agency->id}")->assertOk()->json('data.partner_rooms.0');

        $choice = collect($room['choices'])->firstWhere('option_id', $this->meal->id);
        $this->assertNotNull($choice, 'the hotel\'s breakfast is offered with its room');
        $this->assertEquals(100.0, $choice['adjust_value']);
    }

    public function test_a_chosen_meal_plan_is_added_to_every_night_at_the_hotels_price(): void
    {
        $this->assertEquals(2000.0, $this->preview(2)['final_price']);

        $with = $this->preview(2, ['option_ids' => [$this->meal->id]]);
        $this->assertEquals(2200.0, $with['final_price'], 'two nights at the company\'s 1000, plus 100 a night for the breakfast');
        $this->assertEquals(100.0, $with['modifiers_total']);
    }

    public function test_the_company_runs_its_own_offer_on_the_hotels_room(): void
    {
        $this->offer()->assertCreated();

        // 50% of 1000 would be 500 a night, but the night cannot go under the contract price of 800: 200 off a night
        $b = $this->preview(2);
        $this->assertEquals(2000.0, $b['original_price']);
        $this->assertEquals(400.0, $b['offer_discount_amount']);
        $this->assertEquals(1600.0, $b['final_price']);

        // and the card says so
        Sanctum::actingAs($this->guest);
        $room = $this->getJson("/api/v2/discovery/units/{$this->agency->id}")->json('data.partner_rooms.0');
        $this->assertNotNull($room['offer']);
        $this->assertEquals(800.0, $room['offer']['price_after'], 'the card shows the night after the offer, never below the contract');
    }

    public function test_the_hotels_own_offer_does_not_reach_the_companys_price_and_a_stranger_cannot_target_the_room(): void
    {
        // the hotel's 50% on its own room
        Sanctum::actingAs($this->hotel);
        $this->postJson('/api/v2/business/promotions', ['kind' => 'percent', 'params' => ['value' => 50], 'max_uses' => 10, 'targets' => [['type' => 'bookable_item', 'id' => $this->unit->id]]])->assertCreated();

        $this->assertEquals(2000.0, $this->preview(2)['final_price'], 'the company sells at its own price; the hotel\'s offer is the hotel\'s');

        // a business that was given no allocation cannot put the room in an offer
        Sanctum::actingAs($this->stranger);
        $this->postJson('/api/v2/business/promotions', ['kind' => 'percent', 'params' => ['value' => 10], 'max_uses' => 10, 'targets' => [['type' => 'bookable_item', 'id' => $this->unit->id]]])->assertStatus(422);
    }

    public function test_the_offers_catalog_lists_the_rooms_the_company_was_given(): void
    {
        Sanctum::actingAs($this->agency);
        $groups = $this->getJson('/api/v2/business/promotions/catalog')->assertOk()->json('data.booking');

        $item = collect($groups)->flatMap(fn ($g) => $g['items'])->firstWhere('id', $this->unit->id);
        $this->assertNotNull($item);
        $this->assertEquals(1000.0, $item['price'], 'priced at the company\'s own night');
    }

    public function test_a_booking_through_the_offer_counts_one_use(): void
    {
        $promo = (int) $this->offer()->assertCreated()->json('data.promotion.id') ?: (int) \App\Models\Promotion::query()->where('business_id', $this->agency->id)->value('id');

        $from = now()->addDays(20)->startOfDay();
        Sanctum::actingAs($this->guest);
        $this->postJson('/api/v2/bookings', [
            'business_id' => $this->agency->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => 1,
            'starts_at' => $from->toDateTimeString(), 'ends_at' => $from->copy()->addDays(2)->toDateTimeString(),
        ])->assertCreated();

        $this->assertSame(1, (int) \App\Models\Promotion::query()->whereKey($promo)->value('used_count'));
    }
}
