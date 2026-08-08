<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\PlatformService;
use App\Models\PlatformServiceItemType;
use App\Models\User;
use Database\Seeders\BookingKindGranularitySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «يكون البوكينج باليوم والعيادات بالساعة» — owner, 2026-08-08.
 *
 * The kind said HOW a thing is booked and never in what UNIT. `duration_unit`
 * arrived from the app and was validated against the enum alone, so «day» on a
 * كشف was accepted and three live bookings carry no unit at all.
 *
 * The unit belongs to the KIND, not the child: a car showroom rents by the day
 * and takes a test drive by the half hour, and one child-level setting can no
 * more say both than one `requires_bookable_item` could say which kinds need a
 * named unit.
 */
class BookingKindGranularityTest extends TestCase
{
    use DatabaseTransactions;

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

    private function kind(string $key): ?PlatformServiceItemType
    {
        return PlatformServiceItemType::query()
            ->where('platform_service_id', $this->serviceId)
            ->where('key', $key)
            ->first();
    }

    /** The owner's own example, both halves of it. */
    public function test_a_stay_is_counted_in_days_and_a_clinic_slot_in_minutes(): void
    {
        $stay = $this->kind('booking_stay')?->granularity();
        $exam = $this->kind('booking_examination')?->granularity();

        $this->assertNotNull($stay, 'the period booking has no unit');
        $this->assertSame('day', $stay['unit']);
        $this->assertTrue($stay['all_day'], 'a stay occupies whole days');

        $this->assertNotNull($exam, 'the examination has no unit');
        $this->assertSame('minute', $exam['unit']);
        $this->assertFalse($exam['all_day']);
    }

    /** Every live kind must answer, or the client silently decides again. */
    public function test_every_live_booking_kind_declares_its_unit(): void
    {
        $silent = PlatformServiceItemType::query()
            ->where('platform_service_id', $this->serviceId)
            ->where('is_active', 1)
            ->get()
            ->filter(fn (PlatformServiceItemType $kind) => $kind->granularity() === null)
            ->map(fn (PlatformServiceItemType $kind) => (string) $kind->name_ar);

        $this->assertEmpty(
            $silent->all(),
            'these kinds let the caller pick the unit: ' . $silent->implode('، ')
        );
    }

    /** A hall is an hour, a table is a sitting — not everything is a minute. */
    public function test_the_units_differ_by_what_the_kind_actually_is(): void
    {
        $this->assertSame('hour', $this->kind('booking_time')?->granularity()['unit']);
        $this->assertSame('hour', $this->kind('booking_table')?->granularity()['unit']);
        $this->assertSame('minute', $this->kind('booking_follow_up')?->granularity()['unit']);

        // A follow-up is shorter than a procedure; the slot says so.
        $this->assertLessThan(
            $this->kind('booking_procedure')?->granularity()['slot_minutes'],
            $this->kind('booking_follow_up')?->granularity()['slot_minutes']
        );
    }

    /** The declaration is the source; the DB is only where it lands. */
    public function test_the_seeder_is_idempotent_and_merges(): void
    {
        $kind = $this->kind('booking_stay');

        $kind->update(['meta' => array_merge($kind->meta ?? [], ['kept_by_someone_else' => 'yes'])]);

        (new BookingKindGranularitySeeder)->run();

        $fresh = $this->kind('booking_stay');

        $this->assertSame('yes', $fresh->meta['kept_by_someone_else'] ?? null, 'the seeder clobbered other meta');
        $this->assertSame('day', $fresh->granularity()['unit']);
    }

    /**
     * The refusal: a unit that contradicts the kind is a booking nobody can
     * read — a كشف of «one day» prices and displays as nonsense.
     */
    public function test_a_unit_that_contradicts_the_kind_is_refused(): void
    {
        [$client, $business, $unit] = $this->rentalFixture();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v2/bookings', [
                'business_id' => $business->id,
                'service_id' => $this->serviceId,
                'bookable_id' => $unit->id,
                'starts_at' => now()->addDays(3)->toDateTimeString(),
                'ends_at' => now()->addDays(5)->toDateTimeString(),
                'duration_unit' => 'minute',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('duration_unit');
    }

    /**
     * And the completion: an app that sends nothing gets the kind's own unit
     * rather than a NULL nobody can interpret later.
     */
    public function test_an_omitted_unit_is_filled_from_the_kind(): void
    {
        [$client, $business, $unit] = $this->rentalFixture();

        $response = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v2/bookings', [
                'business_id' => $business->id,
                'service_id' => $this->serviceId,
                'bookable_id' => $unit->id,
                'starts_at' => now()->addDays(3)->toDateTimeString(),
                'ends_at' => now()->addDays(5)->toDateTimeString(),
            ]);

        /*
         * Asserted, not skipped. Renting is booked as a RANGE, and `date`/`time`
         * are NOT NULL columns that predate the window — so a stay booked the
         * only way a stay can be booked used to come back as a 500 out of the
         * driver. The controller fills them from the start of the window now.
         */
        $response->assertStatus(201);

        $booking = DB::table('bookings')->find($response->json('data.booking.id'));

        $this->assertSame('day', $booking->duration_unit);
        $this->assertTrue((bool) $booking->all_day, 'a stay was written as a timed window');
    }

    /**
     * A business that rents, with one unit priced — built rather than found,
     * because two businesses on the whole platform carry a price row.
     *
     * @return array{0:User,1:User,2:BookableItem}
     */
    private function rentalFixture(): array
    {
        $business = User::query()
            ->where('type', User::TYPE_BUSINESS)
            ->where('category_child_id', 188) // معرض سيارات
            ->first();

        $client = User::query()->where('type', User::TYPE_CLIENT)->first();

        if (! $business || ! $client) {
            $this->markTestSkipped('Needs a showroom and a client account.');
        }

        $sedan = (int) DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'نوع المركبة')
            ->where('o.name_ar', 'سيدان')
            ->value('o.id');

        $unit = BookableItem::create([
            'business_id' => $business->id,
            'service_id' => $this->serviceId,
            'item_type' => 'booking_stay',
            'line_option_id' => $sedan ?: null,
            'title' => 'سيارة اختبار',
            'code' => 'TEST-1',
            'capacity' => 5,
            'quantity' => 1,
            'is_active' => 1,
        ]);

        // The key is five columns wide, and the showroom may already carry a
        // row for this exact combination — see the admin price screen.
        DB::table('business_service_prices')->updateOrInsert(
            [
                'business_id' => $business->id,
                'child_id' => 188,
                'service_id' => $this->serviceId,
                'bookable_item_type' => 'booking_stay',
                'line_option_id' => $sedan ?: null,
            ],
            ['price' => 900, 'currency' => 'EGP', 'is_active' => 1, 'updated_at' => now()]
        );

        return [$client, $business, $unit];
    }
}
