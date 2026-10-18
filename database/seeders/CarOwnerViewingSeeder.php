<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «سيارة من المالك» can be VISITED: a buyer books a time to see the car and try it.
 *
 *     php artisan db:seed --class=CarOwnerViewingSeeder
 *
 * The private seller listed his car on the menu but had no booking config at all, so a buyer could only message him.
 * The showroom (#188) already takes an appointment beside renting; the owner only needs the appointment half — a
 * unit-free `booking_appointment` (no bookable item: the car is not held, the TIME is). The shape is the plain
 * appointment every other trade uses (item group 84, `datetime`), written through the one writer that keeps link and
 * config in step; the pattern map (`data/booking_patterns.php`, 549 => appointment) is what the pattern seeder checks.
 *
 * Idempotent.
 */
class CarOwnerViewingSeeder extends Seeder
{
    private const CHILD_NAME = 'سيارة من المالك';

    public function run(): void
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', self::CHILD_NAME)->value('id');
        $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        if ($childId <= 0 || $serviceId <= 0) {
            $this->command?->warn('  ! «' . self::CHILD_NAME . '» أو خدمة الحجز غير موجودة.');

            return;
        }

        $writer = app(ChildServiceWriter::class);
        $n = 0;

        foreach (DB::table('category_parent_child')->where('child_id', $childId)->pluck('parent_id') as $rootId) {
            $writer->enable((int) $rootId, $childId, $serviceId, [
                'booking_modes' => [],
                'item_family' => null,
                'requires_bookable_item' => false,
                'requires_start_end' => false,
                'supports_quantity' => false,
                'supports_guest_count' => false,
                'supports_extras' => false,
                'required_fields' => ['datetime'],
                'item_groups' => [84],
                'allowed_item_types' => ['booking_appointment'],
                'booking_pattern' => 'appointment',
                'booking_patterns' => ['appointment'],
            ], null, null, 'car-owner-viewing');
            $n++;
        }

        $this->command?->info("Car owner viewing: {$n} config(s) written.");
    }
}
