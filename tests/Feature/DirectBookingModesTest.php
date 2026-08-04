<?php

namespace Tests\Feature;

use App\Models\PlatformService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the direct-booking classification. Before it, every booking child
 * demanded a bookable-unit list — a gym had to file football pitches. The rule
 * now: a child only requires units when the customer reserves a SPECIFIC
 * INSTANCE (a hotel room, a court, a hall); booking a type or a slot does not.
 */
class DirectBookingModesTest extends TestCase
{
    use DatabaseTransactions;

    private function bookingServiceId(): int
    {
        return (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');
    }

    /** @return array{requires:bool,types:array} */
    private function configFor(string $rootSlug, string $childName): array
    {
        $row = DB::table('category_service_configs as c')
            ->join('categories as r', 'r.id', '=', 'c.category_id')
            ->join('category_children_master as ch', 'ch.id', '=', 'c.child_id')
            ->where('c.platform_service_id', $this->bookingServiceId())
            ->where('c.is_active', 1)
            ->where('r.slug', $rootSlug)
            ->where('ch.name_ar', $childName)
            ->value('c.config');

        $this->assertNotNull($row, "«{$childName}» must still have an active booking config");
        $config = json_decode((string) $row, true);

        return [
            'requires' => (bool) ($config['requires_bookable_item'] ?? true),
            'types' => (array) ($config['allowed_item_types'] ?? []),
        ];
    }

    public function test_instance_booked_children_still_require_units(): void
    {
        foreach ([['restaurants-cafes', 'مطعم'], ['halls', 'قاعة مناسبات'], ['sports', 'ملاعب كرة']] as [$root, $child]) {
            $this->assertTrue(
                $this->configFor($root, $child)['requires'],
                "«{$child}» reserves specific instances — it must keep requiring a bookable unit"
            );
        }
    }

    public function test_appointment_children_no_longer_require_units(): void
    {
        foreach ([['sports', 'جيم'], ['professions', 'نجار موبيليا'], ['health', 'عيادة'], ['offices', 'محاماه']] as [$root, $child]) {
            $this->assertFalse(
                $this->configFor($root, $child)['requires'],
                "«{$child}» books a slot, not an instance — it must not demand a unit list"
            );
        }
    }

    /**
     * Every child still names a KIND of booking. The type used to carry the
     * vocabulary too — «زيارة معاينة»، «حارة سباحة» — and 294 of them piled up
     * saying what `offering_options` says better. It now says only how the
     * thing is booked.
     *
     * @see \Database\Seeders\ServiceKindsCollapseSeeder
     */
    public function test_every_booking_child_names_a_kind(): void
    {
        // Read from the map rather than repeated here: booking grew four
        // specialised appointment kinds on 2026-08-04 (حجز كشف، حجز استشارة…),
        // and a hardcoded list would have called each one a retired type.
        $kinds = array_keys((require database_path('seeders/data/service_kinds.php'))['booking']['kinds']);

        foreach ([
            ['sports', 'جيم', 'booking_time'],
            ['professions', 'نجار موبيليا', 'booking_appointment'],
            ['restaurants-cafes', 'مطعم', 'booking_table'],
            // A clinic no longer takes a bare appointment: it was given كشف،
            // متابعة، استشارة أونلاين on 2026-08-05, and the plain «حجز موعد»
            // was replaced rather than kept beside them.
            ['health', 'عيادة', 'booking_examination'],
        ] as [$root, $child, $expected]) {
            $types = $this->configFor($root, $child)['types'];

            $this->assertNotEmpty($types, "«{$child}» must still name a booking kind");
            $this->assertContains($expected, $types, "«{$child}» should book by {$expected}");

            foreach ($types as $t) {
                $this->assertContains($t, $kinds, "«{$child}» still carries the retired type «{$t}»");
            }
        }
    }

    /**
     * A single-purpose venue is still offered only its own thing — the pool was
     * once handed eight football and tennis pitches. That slice moved from the
     * item types into the OPTIONS, where it is sharper than it ever was in the
     * types: the pool gets swimming and diving, the pitches get football and
     * squash, and neither sees the other's.
     */
    public function test_single_purpose_venues_are_offered_only_their_own(): void
    {
        $pool = $this->lineOptionsOf('حمام سباحة');
        $pitches = $this->lineOptionsOf('ملاعب كرة');

        $this->assertNotEmpty($pool, 'a pool must still say what it rents');
        $this->assertContains('سباحة', $pool);
        $this->assertNotContains('كرة قدم', $pool, 'a swimming pool has no football pitch');

        $this->assertContains('كرة قدم', $pitches);
        $this->assertNotContains('سباحة', $pitches, 'nor a football ground a swimming lane');

        // A multi-sport club legitimately carries a wider list than either.
        $this->assertGreaterThan(count($pool), count($this->lineOptionsOf('نادي رياضي')));
    }

    /** @return array<int,string> what this child may say it sells */
    private function lineOptionsOf(string $childName): array
    {
        $id = (int) DB::table('category_children_master')->where('name_ar', $childName)->value('id');

        return DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $id)
            ->where('g.price_role', 'line')
            ->pluck('o.name_ar')
            ->all();
    }

    /** A child detached from its root must not linger as bookable. */
    public function test_detached_children_have_no_live_booking_config(): void
    {
        $orphans = DB::table('category_service_configs as c')
            ->where('c.platform_service_id', $this->bookingServiceId())
            ->where('c.is_active', 1)
            ->whereNotExists(function ($q) {
                $q->from('category_parent_child as pc')
                    ->whereColumn('pc.parent_id', 'c.category_id')
                    ->whereColumn('pc.child_id', 'c.child_id');
            })
            ->count();

        $this->assertSame(0, $orphans, 'configs for children no longer under their root must be deactivated');
    }
}
