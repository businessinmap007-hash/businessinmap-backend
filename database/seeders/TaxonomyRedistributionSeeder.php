<?php

namespace Database\Seeders;

use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\PlatformServiceItemType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Redistribute the platform taxonomy between item types and options.
 *
 * The rule that drives every decision here:
 *
 * There are THREE axes, asked in order (blueprint §3.1):
 *   1. Can the merchant put a PRICE on it alone?  → item type ("قاعة أفراح: 5000")
 *   2. No — does it describe the whole BUSINESS?   → option    ("تقسيط", "كاش")
 *   3. Does it describe one bookable UNIT?          → unit row  (bookable_items)
 *
 * That exposed the real reason the platform felt crowded, and it was not "too
 * many services". The «قاعات ومناسبات» branch held 39 entries of which only 9
 * were bookable: the other 30 were a hall's capacity (10), its class (7) and a
 * «مقاس» scale (13). A customer hunting a wedding hall scrolled 39 rows to find
 * 9 real ones.
 *
 * Capacity and class are axis 3, NOT axis 2 — they describe one hall, not the
 * business. An option "this venue has a 300-500 hall" still leaves the customer
 * hunting inside, and a capacity is a NUMBER (>= 320), not a bucket. So they are
 * deactivated as item types and their home is `bookable_items.capacity` (an
 * existing column) and `bookable_items.meta.class`. Only AMENITIES (wifi) become
 * an option — a business-level yes/no. An earlier version of this seeder put
 * capacity/class in option groups too; dropMisplacedDimensionGroups() reverses
 * that.
 *
 * The blueprint already said options survive as attributes (Phase 1 kept group
 * #12 «أنماط خدمة وتجارية» — cash/installment — as "the legitimate residual role
 * for options").
 *
 * Deactivate, never delete, for item types: a live `business_service_prices` row
 * may reference a key, and MenuBranchesSeeder already set that precedent (it
 * kept `3dmax` active for exactly that reason). `options` has no `is_active`
 * column, so retired options are deleted — safe here because only 4
 * `category_child_option` links and 2 `option_user` rows exist at all.
 *
 * Idempotent: re-running changes nothing.
 */
class TaxonomyRedistributionSeeder extends Seeder
{
    /**
     * Hall CAPACITY — the keys of the item types that encoded it.
     *
     * These are deactivated (a capacity is not a bookable thing), but they do
     * NOT become options. Capacity is a THIRD axis: it describes one bookable
     * UNIT, not the whole business. A wedding venue has three halls at 100 / 300
     * / 800 — an option on the business would say "this venue has a 300-500 hall"
     * and still leave the customer hunting inside. It lives on
     * `bookable_items.capacity` (an existing integer column), where a filter can
     * be exact (>= 320) instead of a bucket. An earlier version of this seeder
     * wrongly turned these into an option group; dropMisplacedDimensionGroups()
     * undoes that.
     */
    private const HALL_CAPACITY = [
        'from_10_to_20_person', 'from_20_to_40_person', 'from_40_to_60_person',
        'from_60_to_100_person', 'from_100_to_150_person', 'from_150_to_200_person',
        'from_200_to_300_person', 'monitorfrom_300_to_500_person',
        'from_500_to_750_person', 'from_750_to_1000_person',
    ];

    /** Hall CLASS — a grade of one unit, not a thing you buy. Lives on
     * `bookable_items.meta.class`. Deactivated as item types; not options. */
    private const HALL_CLASS = [
        '1st_class', '2nd_class', '3th_class', '4rd_class',
        '5th_class', '6th_class', '7th_class',
    ];

    /** Option groups a prior version of this seeder created by mistake. */
    private const MISPLACED_DIMENSION_GROUPS = ['سعة القاعة', 'فئة القاعة'];

    /**
     * Facilities a venue HAS. Nobody buys wifi, and unlike capacity it is a
     * yes/no property of the whole business, not a number on one unit — so this
     * one genuinely is an option (axis 2).
     */
    private const VENUE_AMENITIES = [
        'wifi' => ['واي فاي', 'Wi-Fi'],
        'whiteboard' => ['وايت بورد', 'Whiteboard'],
    ];

    /** «مقاس 4 … مقاس 16» — meaningless scale, zero references. Owner's call: junk. */
    private const JUNK_SIZES = [
        'size_4', 'size_5', 'size_6', 'size_7', 'size_8', 'size_9', 'size_10',
        'size_11', 'size_12', 'size_13', 'size_14', 'size_15', 'size_16',
    ];

    /**
     * Products sitting inside booking — «خدمات ومهمات» is a CRAFTSMEN branch and
     * held toys, vegetables and mobile phones. retail already carries every one
     * of these, so they are duplicates in the wrong service.
     */
    private const MISFILED_PRODUCTS = [
        'toys', 'vegetables', 'athletic_devices', 'mobil', 'mobile',
        'mobil_accessories', 'computer_laptop', 'computers', 'cosmetics',
        'medical_supplies', 'electrical_devices', 'electronics_home_appliances',
    ];

    /**
     * Booking duplicates: duplicate key => the key that survives.
     *
     * `five_side_field` wins over `football_5_field` because a live price
     * references it — always keep the key someone already sells. «VIP» standing
     * beside «قاعة VIP» in the same branch is the same hall said twice.
     */
    private const BOOKING_MERGES = [
        'football_5_field' => 'five_side_field',
        'electricity' => 'electrical',
        'vip' => 'hall_vip',
    ];

    /**
     * Menu import duplicates: duplicate key => the key that survives.
     *
     * The `_2` / `_1` suffixes and «مواد غذائية 1» / «مواد غذائية 2» are the
     * fingerprint of an automated import that never deduplicated. Distinct real
     * products (فسيخ، رنجة، بهارات، فحم، آيس كريم، عصائر) are deliberately NOT
     * merged — they are not duplicates, just specific.
     */
    private const MENU_MERGES = [
        'canned_food_2' => 'canned_food',
        'tuna' => 'canned_food',
        'cold_drink' => 'cold_drinks',
        'hot_drink' => 'hot_drinks',
        'drinks' => 'beverages',
        'sweets' => 'sweets_chocolate',
        'candy_and_biscuit' => 'sweets_chocolate',
        'pasta_2' => 'pasta_rice_grains',
        'rice_and_sugar' => 'pasta_rice_grains',
        'flour_and_legumes' => 'pasta_rice_grains',
        'meat' => 'meat_poultry',
        'chickens' => 'meat_poultry',
        'fish' => 'seafood_grocery',
        'dairy_products' => 'dairy_eggs',
        'frozen' => 'frozen_food',
        'freezers_and_fresh' => 'frozen_food',
        'fresh' => 'fresh_produce',
        'fruits' => 'fresh_produce',
        'ghee_and_oil' => 'oils_ghee',
        'cleaners_and_sterilizers' => 'cleaning_supplies',
        'moad_ghthayy_1' => 'foodstuffs',
        'moad_ghthayy_2' => 'foodstuffs',
        'sandwich' => 'sandwiches',
        'sandwitches' => 'sandwiches',
    ];

    /**
     * Option group #12 held real attributes AND specialties that wandered in.
     * These are services/products — booking and retail already carry them
     * (شغالة → «عاملة نظافة», دادة أطفال → «ناني اطفال», إقامة ولائم → «إقامة
     * حفلات»). «spear 1» and «خدمات» are junk. Removed by exact Arabic name.
     *
     * Kept on purpose, because they ARE commercial modes: بيع وشراء · إستيراد ·
     * تصدير · تسليم أرض المصنع · شحن — how a business deals, not what it sells.
     */
    private const OPTION_GROUP_12_REMOVALS = [
        'حجز طيران', 'حجز فنادق', 'سياحة داخلية', 'إقامة ولائم', 'مساعدة فى البيت',
        'شغالة', 'دادة أطفال', 'خدمة مدارس', 'غزل ونسيج', 'أخشاب', 'بترول',
        'الكريتال', 'spear 1', 'خدمات',
    ];

    private const SERVICE_MODES_GROUP_ID = 12;

    public function run(): void
    {
        $this->amenitiesToOptions();
        $this->dropMisplacedDimensionGroups();
        $this->retireItemTypes();
        $this->mergeDuplicates();
        $this->cleanServiceModesGroup();
        $this->pruneConfigs();
    }

    /**
     * Only AMENITIES become options — the one dimension that is a business-level
     * yes/no (axis 2). Capacity and class describe a single bookable unit
     * (axis 3) and live on `bookable_items`, not here.
     */
    private function amenitiesToOptions(): void
    {
        // option_groups has no `key` column — the Arabic name is the identity.
        $group = OptionGroup::updateOrCreate(
            ['name_ar' => 'مرافق ومعدات'],
            ['name_en' => 'Venue Amenities', 'reorder' => 1 + (int) OptionGroup::max('reorder'), 'is_active' => 1]
        );

        foreach (self::VENUE_AMENITIES as [$optionAr, $optionEn]) {
            Option::updateOrCreate(
                ['group_id' => $group->id, 'name_ar' => $optionAr],
                ['name_en' => $optionEn]
            );
        }
    }

    /**
     * Undo the earlier mistake: capacity/class were briefly turned into option
     * groups. They are a per-unit axis, so the groups are removed and their
     * options with them. Self-correcting — safe whether or not they exist.
     */
    private function dropMisplacedDimensionGroups(): void
    {
        $groupIds = OptionGroup::query()
            ->whereIn('name_ar', self::MISPLACED_DIMENSION_GROUPS)
            ->pluck('id');

        if ($groupIds->isEmpty()) {
            return;
        }

        $optionIds = Option::query()->whereIn('group_id', $groupIds)->pluck('id');

        // Cascade by hand — no foreign keys on either pivot.
        if ($optionIds->isNotEmpty()) {
            DB::table('category_child_option')->whereIn('option_id', $optionIds)->delete();
            DB::table('option_user')->whereIn('option_id', $optionIds)->delete();
            Option::query()->whereIn('id', $optionIds)->delete();
        }

        OptionGroup::query()->whereIn('id', $groupIds)->delete();
    }

    /** Deactivate + unbranch everything that is no longer an item type. */
    private function retireItemTypes(): void
    {
        $retire = array_merge(
            self::HALL_CAPACITY,
            self::HALL_CLASS,
            array_keys(self::VENUE_AMENITIES),
            self::JUNK_SIZES,
            self::MISFILED_PRODUCTS,
        );

        $this->deactivate($retire);
    }

    /**
     * Point every reference at the surviving key BEFORE retiring the duplicate,
     * so no merchant's priced offering and no category config is orphaned.
     */
    private function mergeDuplicates(): void
    {
        $merges = self::MENU_MERGES + self::BOOKING_MERGES;

        foreach ($merges as $from => $to) {
            DB::table('business_service_prices')
                ->where('bookable_item_type', $from)
                ->update(['bookable_item_type' => $to]);
        }

        $this->remapConfigs($merges);
        $this->deactivate(array_keys($merges));
    }

    /** Deactivate the given item type keys and drop their branch membership. */
    private function deactivate(array $keys): void
    {
        if ($keys === []) {
            return;
        }

        $ids = PlatformServiceItemType::query()->whereIn('key', $keys)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        PlatformServiceItemType::query()->whereIn('id', $ids)->update(['is_active' => 0]);

        // A retired type must not keep haunting a branch's picker.
        DB::table('platform_service_item_group_type')->whereIn('item_type_id', $ids)->delete();
    }

    /**
     * Strip retired keys out of `category_service_configs.config.allowed_item_types`.
     *
     * This is the dependency that is easy to miss: the configs reference item
     * types by KEY inside a JSON array, so nothing errors when a type retires —
     * the merchant just keeps being offered it. 552 of the 789 configs narrow to
     * an average of 15.5 types, and some name the dimensions we just moved.
     */
    private function pruneConfigs(): void
    {
        $dead = PlatformServiceItemType::query()
            ->where('is_active', 0)
            ->pluck('key')
            ->flip();

        if ($dead->isEmpty()) {
            return;
        }

        foreach (DB::table('category_service_configs')->get() as $row) {
            $config = json_decode((string) $row->config, true);

            if (! is_array($config) || ! isset($config['allowed_item_types']) || ! is_array($config['allowed_item_types'])) {
                continue;
            }

            $kept = array_values(array_filter(
                $config['allowed_item_types'],
                fn ($key) => ! $dead->has($key)
            ));

            if ($kept === $config['allowed_item_types']) {
                continue;
            }

            $config['allowed_item_types'] = $kept;

            DB::table('category_service_configs')
                ->where('id', $row->id)
                ->update(['config' => json_encode($config, JSON_UNESCAPED_UNICODE)]);
        }
    }

    /** Rewrite merged keys inside the configs' allowed_item_types, deduped. */
    private function remapConfigs(array $map): void
    {
        foreach (DB::table('category_service_configs')->get() as $row) {
            $config = json_decode((string) $row->config, true);

            if (! is_array($config) || ! isset($config['allowed_item_types']) || ! is_array($config['allowed_item_types'])) {
                continue;
            }

            $before = $config['allowed_item_types'];
            $after = array_values(array_unique(array_map(
                fn ($key) => $map[$key] ?? $key,
                $before
            )));

            if ($after === $before) {
                continue;
            }

            $config['allowed_item_types'] = $after;

            DB::table('category_service_configs')
                ->where('id', $row->id)
                ->update(['config' => json_encode($config, JSON_UNESCAPED_UNICODE)]);
        }
    }

    /** Remove the specialties that wandered into the attributes group. */
    private function cleanServiceModesGroup(): void
    {
        $ids = Option::query()
            ->where('group_id', self::SERVICE_MODES_GROUP_ID)
            ->whereIn('name_ar', self::OPTION_GROUP_12_REMOVALS)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        // Cascade by hand — neither pivot declares a foreign key.
        DB::table('category_child_option')->whereIn('option_id', $ids)->delete();
        DB::table('option_user')->whereIn('option_id', $ids)->delete();
        Option::query()->whereIn('id', $ids)->delete();
    }
}
