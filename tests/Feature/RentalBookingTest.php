<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\PlatformService;
use App\Services\Catalog\ChildServiceWriter;
use App\Services\ServiceExecutionEngine;
use Database\Seeders\RentalEnablementSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Renting a car, built the way renting a flat already worked.
 *
 * An estate office rents by holding a NAMED unit for a period — kind
 * `booking_stay`, `requires_bookable_item`, one bookable_items row per unit.
 * The engine is kind-agnostic (starts/ends, quantity, overlap), so a rental car
 * needed no engine work: it needed the kind, the config, and one thing that was
 * genuinely broken.
 *
 * **The broken thing:** `requires_bookable_item` is per CHILD and was enforced
 * for every kind at once. A showroom that rents cars AND takes test drives
 * could only have one of the two, and «مكتب عقاري» — whose config demands a
 * unit — could not take a viewing appointment at all. `bookable_item_kinds`
 * names the kinds that reserve an instance, and only those.
 */
class RentalBookingTest extends TestCase
{
    use DatabaseTransactions;

    private const SHOWROOM = 188;   // معرض سيارات

    private const ESTATE = 517;     // مكتب عقاري

    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceId = (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)
            ->where('is_active', 1)
            ->value('id');

        if ($this->serviceId <= 0) {
            $this->markTestSkipped('The booking service is not active.');
        }
    }

    private function configOf(int $childId): array
    {
        $rootId = (int) DB::table('category_service_configs')
            ->where('child_id', $childId)
            ->where('platform_service_id', $this->serviceId)
            ->where('is_active', 1)
            ->value('category_id');

        return [$rootId, app(ChildServiceWriter::class)->storedConfig($rootId, $childId, $this->serviceId)];
    }

    /** The label a merchant reads. It said «حجز فندق» to an estate office. */
    public function test_the_period_kind_is_not_called_a_hotel_any_more(): void
    {
        $this->assertSame(
            'حجز بالمدة',
            DB::table('platform_service_item_types')->where('key', 'booking_stay')->value('name_ar')
        );

        $declared = (require database_path('seeders/data/service_kinds.php'))['booking']['kinds']['booking_stay'];

        $this->assertSame('حجز بالمدة', $declared[0], 'the next seed run would rename it back');
    }

    /** A car showroom can now be rented from, the same way a flat is. */
    public function test_a_car_showroom_offers_the_period_booking(): void
    {
        [, $config] = $this->configOf(self::SHOWROOM);

        $this->assertContains('booking_stay', $config['allowed_item_types'] ?? []);
        $this->assertTrue((bool) ($config['requires_bookable_item'] ?? false));
        $this->assertSame(['booking_stay'], $config['bookable_item_kinds'] ?? []);
    }

    /** The whole point: rent AND be visited, which one boolean could not say. */
    public function test_the_showroom_keeps_its_unit_free_appointment(): void
    {
        [, $config] = $this->configOf(self::SHOWROOM);

        $this->assertContains('booking_appointment', $config['allowed_item_types'] ?? []);
        $this->assertNotContains('booking_appointment', $config['bookable_item_kinds'] ?? []);
    }

    /** «مالك عقار» is precisely who rents, and had only a menu. */
    public function test_the_property_owner_can_rent_at_last(): void
    {
        [$rootId, $config] = $this->configOf(522);

        $this->assertGreaterThan(0, $rootId, 'مالك عقار still has no live booking config');
        $this->assertContains('booking_stay', $config['allowed_item_types'] ?? []);
    }

    /**
     * A child whose every kind reserves an instance still refuses a booking
     * that names none — the guard that stopped a hotel pricing a roomless stay
     * off the generic slot.
     */
    public function test_a_booking_with_no_unit_is_refused_when_every_kind_needs_one(): void
    {
        [$rootId] = $this->configOf(self::ESTATE);

        $business = DB::table('users')
            ->where('type', 'business')
            ->where('category_child_id', self::ESTATE)
            ->value('id');

        if (! $business || $rootId <= 0) {
            $this->markTestSkipped('No live estate office to book against.');
        }

        $this->expectException(ValidationException::class);

        app(ServiceExecutionEngine::class)->prepare(
            businessId: (int) $business,
            serviceId: $this->serviceId,
            bookableId: null
        );
    }

    /**
     * And the mixed child does NOT refuse it: with an unnamed unit the customer
     * can only be asking for one of the unit-free kinds.
     */
    public function test_a_booking_with_no_unit_is_allowed_when_some_kind_needs_none(): void
    {
        $business = DB::table('users')
            ->where('type', 'business')
            ->where('category_child_id', self::SHOWROOM)
            ->value('id');

        if (! $business) {
            $this->markTestSkipped('No live car showroom to book against.');
        }

        /*
         * Asserted on the ERROR KEY, not on success: no showroom has priced
         * anything yet (2 businesses on the whole platform carry a price row),
         * so prepare() still stops later at «no price». What must not happen is
         * stopping at `bookable_id` — that is the guard refusing a test drive
         * because the showroom also rents.
         */
        try {
            app(ServiceExecutionEngine::class)->prepare(
                businessId: (int) $business,
                serviceId: $this->serviceId,
                bookableId: null
            );
        } catch (ValidationException $e) {
            $this->assertArrayNotHasKey(
                'bookable_id',
                $e->errors(),
                'a test drive was refused because the showroom also rents'
            );

            return;
        }

        $this->assertTrue(true, 'prepared without needing a named unit');
    }

    /** A named car prices apart from a named van, exactly as room 101 does. */
    public function test_a_registered_car_carries_its_own_kind_and_label(): void
    {
        $business = (int) DB::table('users')
            ->where('type', 'business')->where('category_child_id', self::SHOWROOM)->value('id');

        $sedan = (int) DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'نوع المركبة')->where('o.name_ar', 'سيدان')->value('o.id');

        if ($business <= 0 || $sedan <= 0) {
            $this->markTestSkipped('Needs a showroom and the vehicle-type options.');
        }

        $unit = BookableItem::create([
            'business_id' => $business,
            'service_id' => $this->serviceId,
            'item_type' => 'booking_stay',
            'line_option_id' => $sedan,
            'title' => 'ص ب ٤٢٧',
            'code' => 'ص ب ٤٢٧',
            'capacity' => 5,
            'quantity' => 1,
            'is_active' => 1,
        ]);

        $this->assertStringContainsString('سيدان', $unit->fresh()->displayLabel());
        $this->assertStringContainsString('ص ب ٤٢٧', $unit->fresh()->displayLabel());
    }

    /**
     * The screen the merchant actually opens. Everything above is config; this
     * is whether a showroom owner can, in the app, say «this car, of this kind,
     * for rent» — the whole point of the exercise.
     */
    public function test_a_showroom_owner_is_offered_the_period_kind_and_the_vehicle_types(): void
    {
        $owner = \App\Models\User::query()
            ->where('type', 'business')
            ->where('category_child_id', self::SHOWROOM)
            ->first();

        if (! $owner) {
            $this->markTestSkipped('No live car showroom to act as.');
        }

        $data = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v2/business/bookable-items/options')
            ->assertOk()
            ->json('data');

        $booking = collect($data['services'])->firstWhere('key', PlatformService::KEY_BOOKING);

        $this->assertNotNull($booking, 'the showroom owner is not offered booking at all');
        $this->assertContains(
            'booking_stay',
            collect($booking['item_types'])->pluck('key')->all(),
            'the owner cannot register a unit for renting'
        );

        // And a unit can say WHICH kind of vehicle it is, so a sedan prices
        // apart from a pickup — the same way room 101 prices apart from a suite.
        $groups = collect($data['line_options'])->pluck('group');

        $this->assertContains('نوع المركبة', $groups->all(), 'a car cannot say what kind of car it is');
    }

    /** Re-running must not duplicate a kind or drop one. */
    public function test_the_seeder_is_idempotent(): void
    {
        [, $before] = $this->configOf(self::SHOWROOM);

        (new RentalEnablementSeeder)->run();

        [, $after] = $this->configOf(self::SHOWROOM);

        $this->assertSame($before['allowed_item_types'], $after['allowed_item_types']);
        $this->assertSame($before['bookable_item_kinds'], $after['bookable_item_kinds']);
    }
}
