<?php

namespace App\Console\Commands;

use App\Models\LabList;
use App\Models\LabListItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Builds the first two demonstration lists in the Taxonomy Lab, exactly as the
 * owner specified:
 *
 *  • «الصحة» — gathers the health-related SERVICE item types AND copies in the
 *    44 medical specialties (children of the «الصحة» category) as category_child
 *    items, so the specialty layer lives in the list too (per owner: later the
 *    specialties become the pick-list a business/customer chooses from).
 *  • «سيارات» — pulls the vehicle brands out of the options «مركبات ونقل»
 *    group and splits them into two sub-lists: «ماركات سيارات» and
 *    «ماركات موتوسيكلات». Non-brand entries (bus, pickup, spare parts, "car
 *    with driver"…) are intentionally left out.
 *
 * Sources: options + item types + category children. Idempotent: only the keyed
 * lists it owns are rebuilt; hand-built lists are never touched.
 */
class BuildTaxonomyLabLists extends Command
{
    protected $signature = 'taxonomy-lab:build-lists';

    protected $description = 'Seed the Health and Cars demonstration lists in the Taxonomy Lab (from options + item types).';

    /** Health-related service item types (ids in platform_service_item_types). */
    private const HEALTH_ITEM_TYPES = [38, 39, 42, 89, 229, 232, 234, 235, 324, 383];

    /** The «الصحة» parent category — its children are the medical specialties. */
    private const HEALTH_CATEGORY_ID = 20;

    /** Motorcycle brands inside the options «مركبات ونقل» group (option ids). */
    private const MOTO_BRANDS = [40, 116, 215, 221, 229, 260, 354, 389];

    /** Vehicle-group options that are NOT brands (types/services/parts) — excluded. */
    private const NON_BRANDS = [51, 57, 58, 60, 62, 63, 65, 184, 194, 214, 220, 248, 250, 251, 280, 281, 365];

    public function handle(): int
    {
        foreach (['options_new', 'platform_service_item_types_new'] as $t) {
            if (DB::table($t)->count() === 0) {
                $this->error("Sandbox table `{$t}` is empty. Run taxonomy-lab:seed first.");
                return self::FAILURE;
            }
        }

        DB::transaction(function () {
            // Rebuild only our own keyed lists (cascade clears items + sub-lists).
            LabList::whereIn('key', ['health', 'cars', 'cars.car_brands', 'cars.moto_brands'])->delete();

            $this->buildHealth();
            $this->buildCars();
        });

        $this->info('Lab lists built.');
        foreach (LabList::whereNull('parent_id')->with('children')->orderBy('sort_order')->get() as $list) {
            $direct = $list->items()->count();
            $this->line("• {$list->name_ar} — {$direct} عنصر مباشر، {$list->children->count()} قسم فرعي");
            foreach ($list->children as $child) {
                $this->line("    └ {$child->name_ar} — {$child->items()->count()} عنصر");
            }
        }

        return self::SUCCESS;
    }

    private function buildHealth(): void
    {
        $health = LabList::create([
            'key' => 'health',
            'name_ar' => 'الصحة',
            'name_en' => 'Health',
            'sort_order' => 1,
        ]);

        // The health SERVICE item types (كيانات الخدمة: عيادة/مستشفى/أشعة…).
        $typeIds = DB::table('platform_service_item_types_new')
            ->whereIn('id', self::HEALTH_ITEM_TYPES)
            ->orderBy('id')
            ->pluck('id');
        $this->attach($health, LabListItem::SOURCE_ITEM_TYPE, $typeIds);

        // The 44 medical specialties — copied from the «الصحة» category children
        // (they STAY as category children too; this list becomes their pool, from
        // which a clinic/hospital/center later picks the specialties it offers).
        $specialtyIds = DB::table('category_parent_child')
            ->where('parent_id', self::HEALTH_CATEGORY_ID)
            ->orderBy('child_id')
            ->pluck('child_id');
        $this->attach($health, LabListItem::SOURCE_CATEGORY_CHILD, $specialtyIds);
    }

    private function buildCars(): void
    {
        $cars = LabList::create([
            'key' => 'cars',
            'name_ar' => 'سيارات',
            'name_en' => 'Cars',
            'sort_order' => 2,
        ]);

        // The vehicle group is defined on the LIVE options table (sandbox group_id
        // is cleared); the ids are identical in options_new.
        $vehicleIds = DB::table('options')->where('group_id', 1)->pluck('id')->all();

        $motoIds = array_values(array_intersect($vehicleIds, self::MOTO_BRANDS));
        $carIds = array_values(array_diff($vehicleIds, self::MOTO_BRANDS, self::NON_BRANDS));

        $carBrands = LabList::create([
            'key' => 'cars.car_brands',
            'parent_id' => $cars->id,
            'name_ar' => 'ماركات سيارات',
            'name_en' => 'Car Brands',
            'sort_order' => 1,
        ]);
        $this->attach($carBrands, LabListItem::SOURCE_OPTION, collect($carIds)->sort()->values());

        $motoBrands = LabList::create([
            'key' => 'cars.moto_brands',
            'parent_id' => $cars->id,
            'name_ar' => 'ماركات موتوسيكلات',
            'name_en' => 'Motorcycle Brands',
            'sort_order' => 2,
        ]);
        $this->attach($motoBrands, LabListItem::SOURCE_OPTION, collect($motoIds)->sort()->values());
    }

    private function attach(LabList $list, string $source, iterable $ids): void
    {
        $sort = (int) $list->items()->max('sort_order');
        foreach ($ids as $id) {
            LabListItem::create([
                'list_id' => $list->id,
                'source' => $source,
                'source_id' => (int) $id,
                'sort_order' => ++$sort,
            ]);
        }
    }
}
