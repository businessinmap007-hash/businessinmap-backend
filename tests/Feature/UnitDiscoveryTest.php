<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The booking flow for 21 category children was not completable from the app.
 *
 * Those children set `requires_bookable_item`, so the engine refuses a booking
 * without a named unit and the client must send `bookable_id` — and there was no
 * customer-facing endpoint over `bookable_items` anywhere, so nothing ever told
 * the client what those ids were. Every hotel child, every restaurant, the
 * pitches, the halls, the pools and the coworking spaces were in that state.
 *
 * @see \App\Http\Controllers\Api\V2\UnitDiscoveryController
 */
class UnitDiscoveryTest extends TestCase
{
    use DatabaseTransactions;

    private int $businessId;

    private int $serviceId;

    private int $childId;

    private int $doubleRoomId;

    private int $suiteId;

    protected function setUp(): void
    {
        parent::setUp();

        $business = User::query()->where('type', 'business')->whereNotNull('category_child_id')->first();
        $serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');

        if (! $business || $serviceId <= 0) {
            $this->markTestSkipped('Needs a business with a child and the booking service.');
        }

        $this->businessId = (int) $business->id;
        $this->serviceId = $serviceId;
        $this->childId = (int) $business->category_child_id;

        $rooms = OptionGroup::query()->where('name_ar', 'الغرف')->value('id');
        $this->doubleRoomId = (int) Option::query()->where('group_id', $rooms)->where('name_ar', 'غرفة مزدوجة')->value('id');
        $this->suiteId = (int) Option::query()->where('group_id', $rooms)->where('name_ar', 'جناح')->value('id');

        if ($this->doubleRoomId <= 0 || $this->suiteId <= 0) {
            $this->markTestSkipped('The room vocabulary is missing.');
        }

        BusinessServicePrice::query()
            ->where('business_id', $this->businessId)
            ->where('service_id', $this->serviceId)
            ->delete();

        BookableItem::query()->where('business_id', $this->businessId)->forceDelete();
    }

    private function price(float $amount, ?int $lineOptionId): BusinessServicePrice
    {
        $row = BusinessServicePrice::create([
            'business_id' => $this->businessId,
            'service_id' => $this->serviceId,
            'child_id' => $this->childId,
            'bookable_item_type' => 'booking_stay',
            'price' => $amount,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);

        if ($lineOptionId) {
            $row->syncOfferingOptions($lineOptionId, []);
        }

        return $row->refresh();
    }

    /**
     * The endpoint is locale-aware (SetApiLocale), so a bare request answers in
     * whatever the default is. These assertions are about the Arabic names, so
     * ask for Arabic — the same way the app does.
     */
    private function browse(string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson("/api/v2/discovery/units/{$this->businessId}{$query}");
    }

    private function unit(string $code, ?int $lineOptionId, int $quantity = 1): BookableItem
    {
        return BookableItem::create([
            'business_id' => $this->businessId,
            'service_id' => $this->serviceId,
            'item_type' => 'booking_stay',
            'line_option_id' => $lineOptionId,
            'title' => 'وحدة ' . $code,
            'code' => $code,
            'quantity' => $quantity,
            'is_active' => 1,
        ]);
    }

    /** The question the app could not ask: which rooms, and for how much. */
    public function test_the_units_come_back_grouped_by_kind_each_with_its_own_price(): void
    {
        $this->price(900, $this->doubleRoomId);
        $this->price(2500, $this->suiteId);

        foreach (['101', '102', '103'] as $code) {
            $this->unit($code, $this->doubleRoomId);
        }

        $this->unit('س301', $this->suiteId);

        $kinds = $this->browse()
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data.kinds');

        $this->assertCount(2, $kinds);

        $byName = collect($kinds)->keyBy('name');

        $this->assertEquals(900, $byName['غرفة مزدوجة']['price']);
        $this->assertSame(3, $byName['غرفة مزدوجة']['units_count']);

        $this->assertEquals(2500, $byName['جناح']['price']);
        $this->assertSame(1, $byName['جناح']['units_count']);

        // Cheapest first: an app renders this as a list and leads with it.
        $this->assertSame('غرفة مزدوجة', $kinds[0]['name']);

        // The ids the client must send back, which is the entire point.
        $codes = collect($byName['غرفة مزدوجة']['units'])->pluck('code')->all();
        $this->assertSame(['101', '102', '103'], $codes);

        foreach ($byName['جناح']['units'] as $unit) {
            $this->assertGreaterThan(0, $unit['id']);
            $this->assertSame('جناح — س301', $unit['label']);
        }
    }

    /** Without dates the answer is what exists and what it costs, not what is free. */
    public function test_availability_is_only_reported_when_a_window_is_asked_for(): void
    {
        $this->price(900, $this->doubleRoomId);
        $this->unit('101', $this->doubleRoomId);

        $bare = $this->browse()->assertOk()->json('data.kinds.0');

        $this->assertNull($bare['available_count']);
        $this->assertArrayNotHasKey('available', $bare['units'][0]);

        $starts = now()->addDays(3)->format('Y-m-d H:i:s');
        $ends = now()->addDays(5)->format('Y-m-d H:i:s');

        $dated = $this->browse("?starts_at={$starts}&ends_at={$ends}")->assertOk()->json('data.kinds.0');

        $this->assertSame(1, $dated['available_count']);
        $this->assertTrue($dated['units'][0]['available']);
    }

    /** A unit the business closed by hand must not be offered as free. */
    public function test_a_blocked_unit_is_reported_unavailable(): void
    {
        $this->price(900, $this->doubleRoomId);
        $unit = $this->unit('101', $this->doubleRoomId);

        $starts = now()->addDays(3);
        $ends = now()->addDays(5);

        DB::table('bookable_item_blocked_slots')->insert([
            'bookable_item_id' => $unit->id,
            'starts_at' => $starts->copy()->subDay(),
            'ends_at' => $ends->copy()->addDay(),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kind = $this->browse(
            '?starts_at=' . $starts->format('Y-m-d H:i:s')
            . '&ends_at=' . $ends->format('Y-m-d H:i:s')
        )->assertOk()->json('data.kinds.0');

        $this->assertSame(0, $kind['available_count']);
        $this->assertFalse($kind['units'][0]['available']);
        $this->assertNotNull($kind['units'][0]['reason']);
    }

    /**
     * A kind nobody priced still has to appear — it is the business's own
     * unfinished work, and hiding it makes a missing price look like a missing
     * room. It just does not lead the list.
     */
    public function test_an_unpriced_kind_is_listed_last_rather_than_hidden(): void
    {
        $this->price(900, $this->doubleRoomId);

        $this->unit('101', $this->doubleRoomId);
        $this->unit('س301', $this->suiteId);

        $kinds = $this->browse()->assertOk()->json('data.kinds');

        $this->assertCount(2, $kinds);
        $this->assertSame('غرفة مزدوجة', $kinds[0]['name']);

        // And it appears as what it is: a kind with no price. It used to borrow
        // the double room's row and report 900, which is the failure mode this
        // test's own docblock warns about — a missing price wearing someone
        // else's number.
        $this->assertSame('جناح', $kinds[1]['name']);
        $this->assertNull($kinds[1]['offering_id']);
        $this->assertNull($kinds[1]['price']);
    }

    public function test_an_unknown_business_is_not_found(): void
    {
        $this->getJson('/api/v2/discovery/units/99999999')->assertNotFound();
    }

    /** Browsing rooms must not require signing in. */
    public function test_the_endpoint_is_public(): void
    {
        $this->price(900, $this->doubleRoomId);
        $this->unit('101', $this->doubleRoomId);

        $this->browse()->assertOk();
    }

    /** And it answers in the language it was asked in. */
    public function test_the_kind_is_named_in_the_requested_language(): void
    {
        $this->price(900, $this->doubleRoomId);
        $this->unit('101', $this->doubleRoomId);

        $this->assertSame('غرفة مزدوجة', $this->browse()->json('data.kinds.0.name'));

        $this->assertSame(
            'Double Room',
            $this->withHeaders(['Accept-Language' => 'en'])
                ->getJson("/api/v2/discovery/units/{$this->businessId}")
                ->json('data.kinds.0.name')
        );
    }
}
