<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\BusinessServicePriceResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A unit says WHICH kind it is, so it can point at its own price.
 *
 * The room kinds used to live in the item type — `single_room`, `suite`,
 * `villa` — and collapsed onto one key, `booking_stay`. Since then a hotel's
 * six priced rows are separated by `business_service_prices.line_option_id`
 * alone, and the unit had no way to name one: room 101 and جناح س301 both
 * resolved through the same ladder, which orders by id. Every room in the
 * hotel cost whatever the newest row said.
 *
 * `bookable_items.line_option_id` closes that. It is nullable on purpose — a
 * clinic with one price per type never needs it, and the old ladder still runs
 * for those.
 */
class BookableUnitLineOptionTest extends TestCase
{
    use DatabaseTransactions;

    private BusinessServicePriceResolver $resolver;

    private int $businessId;

    private int $serviceId;

    private int $childId;

    private int $doubleRoomId;

    private int $suiteId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new BusinessServicePriceResolver();

        $business = User::query()->where('type', 'business')->whereNotNull('category_child_id')->first();
        $serviceId = (int) PlatformService::query()->value('id');

        if (! $business || $serviceId <= 0) {
            $this->markTestSkipped('Needs a business with a child and a platform service.');
        }

        $this->businessId = (int) $business->id;
        $this->serviceId = $serviceId;
        $this->childId = (int) $business->category_child_id;

        // The real vocabulary: whatever «الغرف» actually holds. Inventing options
        // would prove the column works and nothing about the hotel it exists for.
        $rooms = OptionGroup::query()->where('name_ar', 'الغرف')->first();

        if (! $rooms) {
            $this->markTestSkipped('The «الغرف» group is gone.');
        }

        $this->doubleRoomId = (int) Option::query()->where('group_id', $rooms->id)->where('name_ar', 'غرفة مزدوجة')->value('id');
        $this->suiteId = (int) Option::query()->where('group_id', $rooms->id)->where('name_ar', 'جناح')->value('id');

        if ($this->doubleRoomId <= 0 || $this->suiteId <= 0) {
            $this->markTestSkipped('«غرفة مزدوجة» or «جناح» is missing from the rooms group.');
        }

        BusinessServicePrice::query()
            ->where('business_id', $this->businessId)
            ->where('service_id', $this->serviceId)
            ->where('child_id', $this->childId)
            ->delete();
    }

    private function seedPrice(string $itemType, float $price, ?int $lineOptionId = null): BusinessServicePrice
    {
        $row = BusinessServicePrice::create([
            'business_id' => $this->businessId,
            'service_id' => $this->serviceId,
            'child_id' => $this->childId,
            'bookable_item_type' => $itemType,
            'price' => $price,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);

        if ($lineOptionId) {
            // line_option_id is a mirror of offering_options; only the sync may
            // write it, so go through the same door the pricing screen uses.
            $row->syncOfferingOptions($lineOptionId, []);
            $row->refresh();
        }

        return $row;
    }

    private function unit(string $code, ?int $lineOptionId): BookableItem
    {
        return BookableItem::create([
            'business_id' => $this->businessId,
            'service_id' => $this->serviceId,
            'item_type' => 'booking_stay',
            'line_option_id' => $lineOptionId,
            'title' => 'وحدة ' . $code,
            'code' => $code,
            'quantity' => 1,
            'is_active' => 1,
        ]);
    }

    /** The whole point: two units of one item type, two different prices. */
    public function test_two_units_of_one_item_type_resolve_to_different_prices(): void
    {
        $double = $this->seedPrice('booking_stay', 800, $this->doubleRoomId);
        $suite = $this->seedPrice('booking_stay', 2500, $this->suiteId);

        $room101 = $this->unit('101', $this->doubleRoomId);
        $suite301 = $this->unit('س301', $this->suiteId);

        $this->assertSame($double->id, $this->resolver->resolveForBookableItem($room101)->id);
        $this->assertSame($suite->id, $this->resolver->resolveForBookableItem($suite301)->id);

        $this->assertSame(800.0, $room101->resolvedBasePrice());
        $this->assertSame(2500.0, $suite301->resolvedBasePrice());
    }

    /**
     * Before the column, both units answered to the same row — and the ladder
     * orders by id, so it was the LAST one created. This pins the old behaviour
     * as the thing that changed, not as a thing that still happens.
     */
    public function test_a_unit_without_a_kind_still_uses_the_old_ladder(): void
    {
        $this->seedPrice('booking_stay', 800, $this->doubleRoomId);
        $suite = $this->seedPrice('booking_stay', 2500, $this->suiteId);

        $unnamed = $this->unit('102', null);

        $this->assertSame(
            $suite->id,
            $this->resolver->resolveForBookableItem($unnamed)->id,
            'a unit naming no kind must keep resolving exactly as it did before'
        );
    }

    /**
     * Narrowing is all-or-nothing, and this test used to contradict its own
     * docblock: it said a suite priced nowhere «must not silently sell at the
     * double room's price», then asserted that it does.
     *
     * The docblock was right. The unrestricted fallback took the newest row for
     * the service whatever kind it named, and it had already happened twice in
     * the live database — a single room sold at the «شقة» rate of 2000, and nine
     * double rooms sold at the «فردية» rate of 600. The fallback now reaches
     * only the row that names NO kind.
     */
    public function test_an_unpriced_kind_does_not_borrow_a_siblings_row(): void
    {
        $this->seedPrice('booking_stay', 800, $this->doubleRoomId);

        $suite301 = $this->unit('س301', $this->suiteId);

        $this->assertNull(
            $this->resolver->resolveForBookableItem($suite301),
            'the suite borrowed the double room\'s price'
        );
    }

    /** It falls to the generic row instead — what the unit screen promises. */
    public function test_an_unpriced_kind_falls_to_the_row_that_names_no_kind(): void
    {
        $this->seedPrice('booking_stay', 800, $this->doubleRoomId);
        $generic = $this->seedPrice('booking_stay', 500);

        $got = $this->resolver->resolveForBookableItem($this->unit('س301', $this->suiteId));

        $this->assertNotNull($got, 'the generic row must still be reachable');
        $this->assertSame($generic->id, $got->id);
    }

    /** An inactive row is invisible to the narrowed pass too. */
    public function test_an_inactive_row_for_the_kind_does_not_resolve(): void
    {
        $suite = $this->seedPrice('booking_stay', 2500, $this->suiteId);
        $suite->update(['is_active' => 0]);

        $default = $this->seedPrice(BusinessServicePrice::DEFAULT_ITEM_TYPE, 400);

        $got = $this->resolver->resolveForBookableItem($this->unit('س302', $this->suiteId));

        $this->assertSame($default->id, $got->id);
    }

    /** The unit has to be able to say what it is out loud. */
    public function test_the_unit_labels_itself_with_its_kind(): void
    {
        $suite301 = $this->unit('س301', $this->suiteId);
        $suite301->load('lineOption');

        $this->assertSame('جناح — س301', $suite301->displayLabel());

        $unnamed = $this->unit('102', null);

        $this->assertSame('102', $unnamed->displayLabel(), 'a unit with no kind falls back to its code');
    }

    /**
     * The admin bulk table is where «101 إلى 109» is actually typed, so the
     * kind has to be per ROW — rooms and suites go in together and must not
     * come out sharing one price.
     */
    public function test_the_admin_bulk_table_stores_a_kind_per_row(): void
    {
        $admin = User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin account to act as.');
        }

        $hotel = User::query()->where('type', 'business')->find(212);

        if (! $hotel) {
            $this->markTestSkipped('The reference hotel is gone.');
        }

        $service = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        // Ask the controller which types this hotel's child may actually use,
        // rather than the first active one platform-wide — the screen refuses
        // anything outside that set, and the taxonomy moves.
        $allowed = new \ReflectionMethod(\App\Http\Controllers\AdminV2\BookableItemController::class, 'allowedItemTypesFor');
        $allowed->setAccessible(true);
        $itemType = $allowed->invoke(app(\App\Http\Controllers\AdminV2\BookableItemController::class), (int) $hotel->id, $service)[0] ?? null;

        if (! $service || ! $itemType) {
            $this->markTestSkipped('The hotel has no allowed booking item type to file a unit under.');
        }

        $suffix = strtoupper(substr(md5(uniqid('', true)), 0, 5));

        $this->actingAs($admin)
            ->post(route('admin.bookable-items.store', [], false), [
                'business_id' => $hotel->id,
                'service_id' => $service,
                'items' => [
                    ['item_type' => $itemType, 'line_option_id' => $this->doubleRoomId, 'code' => "R{$suffix}1", 'quantity' => 1, 'is_active' => 1],
                    ['item_type' => $itemType, 'line_option_id' => $this->suiteId, 'code' => "S{$suffix}1", 'quantity' => 1, 'is_active' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(
            $this->doubleRoomId,
            (int) BookableItem::query()->where('code', "R{$suffix}1")->value('line_option_id')
        );

        $this->assertSame(
            $this->suiteId,
            (int) BookableItem::query()->where('code', "S{$suffix}1")->value('line_option_id')
        );
    }

    /**
     * The reported blocker: «العناصر القابلة للحجز لا يمكننى تسعيرها».
     *
     * The admin price screen keyed on (business, child, service, item type) —
     * one row for a whole hotel. Its unique rule refused the second stay price
     * outright, and store()'s upsert matched the same four columns, so an admin
     * adding «جناح 1000» would have rewritten «غرفة مزدوجة 900» and called it a
     * save. The line is part of the row's identity, exactly as the DB key says.
     */
    public function test_the_admin_can_price_two_kinds_under_one_item_type(): void
    {
        $admin = User::query()->where('type', 'admin')->first();
        $hotel = User::query()->where('type', 'business')->find(212);

        if (! $admin || ! $hotel) {
            $this->markTestSkipped('Needs an admin and the reference hotel.');
        }

        $service = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        $allowed = new \ReflectionMethod(\App\Http\Controllers\AdminV2\BusinessServicePriceController::class, 'allowedItemTypesForChildService');
        $allowed->setAccessible(true);
        $itemType = $allowed->invoke(
            app(\App\Http\Controllers\AdminV2\BusinessServicePriceController::class),
            (int) $hotel->category_child_id,
            $service
        )[0] ?? null;

        if (! $itemType) {
            $this->markTestSkipped('The hotel has no allowed booking item type.');
        }

        $post = fn (int $line, float $price) => $this->actingAs($admin)
            ->post(route('admin.business_service_prices.store', [], false), [
                'business_id' => $hotel->id,
                'child_id' => $hotel->category_child_id,
                'service_id' => $service,
                'bookable_item_type' => $itemType,
                'line_option_id' => $line,
                'price' => $price,
                'currency' => 'EGP',
                'is_active' => 1,
            ]);

        $post($this->doubleRoomId, 900)->assertRedirect()->assertSessionHasNoErrors();
        $post($this->suiteId, 2500)->assertRedirect()->assertSessionHasNoErrors();

        $rows = BusinessServicePrice::query()
            ->where('business_id', $hotel->id)
            ->where('service_id', $service)
            ->where('bookable_item_type', $itemType)
            ->whereIn('line_option_id', [$this->doubleRoomId, $this->suiteId])
            ->get()
            ->keyBy('line_option_id');

        $this->assertCount(2, $rows, 'the second kind overwrote the first instead of standing beside it');
        $this->assertEquals(900, $rows[$this->doubleRoomId]->price);
        $this->assertEquals(2500, $rows[$this->suiteId]->price);

        // The mirror is written through the sync, so the row can name itself.
        $this->assertSame('جناح', $rows[$this->suiteId]->lineOption()?->name_ar);
    }

    /** Retiring an option must not delete the room standing in the hotel. */
    public function test_deleting_the_option_leaves_the_unit_standing(): void
    {
        $scratch = Option::create([
            'name_ar' => 'نوع اختبار مؤقت',
            'name_en' => 'Temp test kind ' . uniqid(),
            'group_id' => OptionGroup::query()->where('name_ar', 'الغرف')->value('id'),
        ]);

        $unit = $this->unit('T1', (int) $scratch->id);

        DB::table('options')->where('id', $scratch->id)->delete();

        $unit->refresh();

        $this->assertTrue($unit->exists);
        $this->assertNull($unit->line_option_id, 'the unit must survive with its kind cleared, not be deleted');
    }
}
