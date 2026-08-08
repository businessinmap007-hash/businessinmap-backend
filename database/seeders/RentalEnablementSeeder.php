<?php

namespace Database\Seeders;

use App\Models\PlatformService;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Renting, built the way apartment renting already works.
 *
 * A مكتب عقاري rents a flat by holding a NAMED unit for a period: kind
 * `booking_stay`, `requires_bookable_item`, and a bookable_items row per unit
 * whose `line_option_id` says which kind of property it is. The engine is
 * entirely kind-agnostic — `starts_at`/`ends_at`/`quantity`/`bookable_id` and an
 * overlap check — so renting a car needs no engine work at all. It needed a
 * vocabulary and a config, which is what this writes.
 *
 * **Two things had to change first, and both were live faults:**
 *
 * 1. `booking_stay` was called «حجز فندق». Real estate has ridden the kind
 *    since the collapse, so an estate office renting a flat was already being
 *    shown «حجز فندق». Renamed «حجز بالمدة» in service_kinds.php.
 *
 * 2. `requires_bookable_item` is per CHILD and was enforced for every kind, so
 *    a showroom could rent OR be visited, never both — and «مكتب عقاري», whose
 *    config demands a unit for everything, could not take a viewing appointment
 *    at all. `bookable_item_kinds` now names the kinds that reserve an
 *    instance; see ServiceExecutionEngine::assertBookableItemChosen().
 *
 * What this does NOT invent: the vehicle line options (سيدان/SUV/بيك أب) and
 * the deal-type axis (بيع/إيجار/تبديل) already exist — see
 * VehicleDealTypeSeeder. A showroom registers each car as a unit, names its
 * kind, and prices it; nothing else is required of it.
 *
 * Idempotent, and additive: it never removes a kind a child already offers.
 */
class RentalEnablementSeeder extends Seeder
{
    private const STAY = 'booking_stay';

    /**
     * Children that hold a named unit for a period, and the kinds among the
     * ones they offer that actually reserve an instance.
     *
     * @var array<int,string>
     */
    private const RENTERS = [
        188 => 'معرض سيارات',
        53 => 'سيارات',
        189 => 'معرض موتوسيكلات',
        238 => 'تسويق عقاري',
        517 => 'مكتب عقاري',
        518 => 'مطور عقاري',
        522 => 'مالك عقار',
    ];

    public function run(): void
    {
        $serviceId = (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)
            ->where('is_active', 1)
            ->value('id');

        if ($serviceId <= 0) {
            $this->command?->warn('The booking service is not active — nothing to do.');

            return;
        }

        $writer = app(ChildServiceWriter::class);
        $touched = 0;

        foreach (self::RENTERS as $childId => $name) {
            /*
             * Every root the child sits under. «سيارات» is reachable from more
             * than one, and a showroom rents the same way through each.
             */
            $roots = DB::table('category_parent_child')
                ->where('child_id', $childId)
                ->pluck('parent_id')
                ->map(fn ($id) => (int) $id);

            foreach ($roots as $rootId) {
                $stored = $writer->storedConfig($rootId, $childId, $serviceId);

                // A child with no booking at all: «مالك عقار» is precisely who
                // rents, and had only a menu.
                $kinds = collect($stored['allowed_item_types'] ?? [])
                    ->map(fn ($kind) => trim((string) $kind))
                    ->filter();

                $before = $kinds->all();

                if (! $kinds->contains(self::STAY)) {
                    $kinds = $kinds->push(self::STAY);
                }

                $writer->enable(
                    rootId: $rootId,
                    childId: $childId,
                    serviceId: $serviceId,
                    configPatch: [
                        'allowed_item_types' => $kinds->unique()->values()->all(),
                        'requires_bookable_item' => true,
                        // Only the period booking holds an instance. A test
                        // drive or a viewing stays an ordinary appointment.
                        'bookable_item_kinds' => [self::STAY],
                    ],
                    source: 'rental_enablement'
                );

                if ($before !== $kinds->unique()->values()->all()) {
                    $this->command?->info("«{$name}» (root {$rootId}) can now rent by the period.");
                }

                $touched++;
            }
        }

        $this->command?->info("Rental: {$touched} config(s) written.");
    }
}
