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

    /** direct_typed keeps its price list; only the unit requirement goes. */
    public function test_typed_direct_children_keep_their_priced_types(): void
    {
        $gym = $this->configFor('sports', 'جيم');
        $this->assertFalse($gym['requires']);
        $this->assertNotEmpty($gym['types'], 'a gym still prices its offerings');

        $carpenter = $this->configFor('professions', 'نجار موبيليا');
        $this->assertContains('inspection_visit', $carpenter['types'], 'craft children price the generic tasks');
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
