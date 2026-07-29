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
 * Also seeds the remaining domain parent-lists (flat, like الصحة) so every
 * child business under a domain picks from one simple shared list:
 *  • «منيو المطاعم» / «مشتريات سوبر ماركت» — from the menu service item types
 *    (the clean canonical sets 55–68 and 69–86; the messy 259–293 duplicates
 *    are left out).
 *  • «الكورسات والتدريب» / «الحرفيون» — from category children (parents 12 / 6),
 *    de-duplicated by name.
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

    /** Motorcycle-only brands inside the options «مركبات ونقل» group (option ids). */
    private const MOTO_BRANDS = [40, 116, 215, 221, 229, 260, 354, 389];

    /**
     * Brands that make BOTH cars and motorcycles — they appear in each sub-list.
     * BMW (44), Honda (185), Suzuki (351).
     */
    private const DUAL_BRANDS = [44, 185, 351];

    /** Vehicle-group options that are NOT brands (types/services/parts) — excluded. */
    private const NON_BRANDS = [51, 57, 58, 60, 62, 63, 65, 184, 194, 214, 220, 248, 250, 251, 280, 281, 365];

    /** Restaurant menu sections (menu item types 55–68 — the clean canonical set). */
    private const RESTAURANT_TYPES = [55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68];

    /** Supermarket product categories (menu item types 69–86 — the clean grocery set). */
    private const SUPERMARKET_TYPES = [69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86];

    /** Parent categories whose children seed the courses / craftsmen lists. */
    private const COURSES_CATEGORY_ID = 12;   // دورات وتدريب
    private const CRAFTSMEN_CATEGORY_ID = 6;   // مهن وحرفيين

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
            LabList::whereIn('key', [
                'health', 'cars', 'cars.car_brands', 'cars.moto_brands',
                'restaurant', 'supermarket', 'courses', 'craftsmen',
            ])->delete();

            $this->buildHealth();
            $this->buildCars();
            $this->buildRestaurant();
            $this->buildSupermarket();
            $this->buildCourses();
            $this->buildCraftsmen();
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

        // Motorcycle list = pure moto brands + the dual makers; car list keeps the
        // dual makers too (they build cars as well), so dual brands live in both.
        $motoIds = array_values(array_intersect($vehicleIds, array_merge(self::MOTO_BRANDS, self::DUAL_BRANDS)));
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

    private function buildRestaurant(): void
    {
        $list = LabList::create([
            'key' => 'restaurant',
            'name_ar' => 'منيو المطاعم',
            'name_en' => 'Restaurant Menu',
            'sort_order' => 3,
        ]);

        $ids = DB::table('platform_service_item_types_new')
            ->whereIn('id', self::RESTAURANT_TYPES)->orderBy('id')->pluck('id');
        $this->attach($list, LabListItem::SOURCE_ITEM_TYPE, $ids);
    }

    private function buildSupermarket(): void
    {
        $list = LabList::create([
            'key' => 'supermarket',
            'name_ar' => 'مشتريات سوبر ماركت',
            'name_en' => 'Supermarket',
            'sort_order' => 4,
        ]);

        $ids = DB::table('platform_service_item_types_new')
            ->whereIn('id', self::SUPERMARKET_TYPES)->orderBy('id')->pluck('id');
        $this->attach($list, LabListItem::SOURCE_ITEM_TYPE, $ids);
    }

    private function buildCourses(): void
    {
        $list = LabList::create([
            'key' => 'courses',
            'name_ar' => 'الكورسات والتدريب',
            'name_en' => 'Courses & Training',
            'sort_order' => 5,
        ]);

        $this->attach($list, LabListItem::SOURCE_CATEGORY_CHILD, $this->categoryChildIds(self::COURSES_CATEGORY_ID));
    }

    private function buildCraftsmen(): void
    {
        $list = LabList::create([
            'key' => 'craftsmen',
            'name_ar' => 'الحرفيون',
            'name_en' => 'Craftsmen',
            'sort_order' => 6,
        ]);

        $this->attach($list, LabListItem::SOURCE_CATEGORY_CHILD, $this->categoryChildIds(self::CRAFTSMEN_CATEGORY_ID));
    }

    /**
     * Child ids of a parent category, de-duplicated by name (some crafts, e.g.
     * «حداد», exist as two master rows) — keeps the lowest id per name.
     */
    private function categoryChildIds(int $parentId): \Illuminate\Support\Collection
    {
        return DB::table('category_parent_child as pc')
            ->join('category_children_master as m', 'm.id', '=', 'pc.child_id')
            ->where('pc.parent_id', $parentId)
            ->orderBy('m.name_ar')->orderBy('m.id')
            ->get(['m.id', 'm.name_ar'])
            ->unique('name_ar')
            ->pluck('id')
            ->values();
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
