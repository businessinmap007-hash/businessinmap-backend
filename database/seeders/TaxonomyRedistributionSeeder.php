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
 *   Can the merchant put a PRICE on it on its own?
 *     yes → item type  ("قاعة أفراح: 5000")   — offer = filter = index
 *     no  → option     ("من 200 إلى 300 فرد") — a property you filter by
 *
 * That rule exposed the real reason the platform felt crowded, and it was not
 * "too many services". The «قاعات ومناسبات» branch held 39 entries of which only
 * 9 were bookable: the other 30 were a hall's capacity (10), its class (7) and a
 * «مقاس» scale (13). A customer hunting a wedding hall scrolled 39 rows to find
 * 9 real ones. Capacity and class are not things you buy — they are how you
 * narrow down the thing you buy.
 *
 * The blueprint already said options survive as attributes (Phase 1 kept group
 * #12 «أنماط خدمة وتجارية» — cash/installment — as "the legitimate residual role
 * for options"). This seeder is that decision finally applied to the item types
 * that were never sorted.
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
    /** Hall capacity — a range, not a thing you buy. */
    private const HALL_CAPACITY = [
        'from_10_to_20_person' => ['من 10 إلى 20 فرد', '10 to 20 people'],
        'from_20_to_40_person' => ['من 20 إلى 40 فرد', '20 to 40 people'],
        'from_40_to_60_person' => ['من 40 إلى 60 فرد', '40 to 60 people'],
        'from_60_to_100_person' => ['من 60 إلى 100 فرد', '60 to 100 people'],
        'from_100_to_150_person' => ['من 100 إلى 150 فرد', '100 to 150 people'],
        'from_150_to_200_person' => ['من 150 إلى 200 فرد', '150 to 200 people'],
        'from_200_to_300_person' => ['من 200 إلى 300 فرد', '200 to 300 people'],
        // The key really is misspelled "monitorfrom_" in the source data.
        'monitorfrom_300_to_500_person' => ['من 300 إلى 500 فرد', '300 to 500 people'],
        'from_500_to_750_person' => ['من 500 إلى 750 فرد', '500 to 750 people'],
        'from_750_to_1000_person' => ['من 750 إلى 1000 فرد', '750 to 1000 people'],
    ];

    /** Hall class — a grade, not a thing you buy. */
    private const HALL_CLASS = [
        '1st_class' => ['فئة أولى', '1st class'],
        '2nd_class' => ['فئة ثانية', '2nd class'],
        '3th_class' => ['فئة ثالثة', '3rd class'],
        '4rd_class' => ['فئة رابعة', '4th class'],
        '5th_class' => ['فئة خامسة', '5th class'],
        '6th_class' => ['فئة سادسة', '6th class'],
        '7th_class' => ['فئة سابعة', '7th class'],
    ];

    /** Facilities a venue HAS. Nobody buys wifi. */
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
        $this->dimensionsToOptions();
        $this->retireItemTypes();
        $this->mergeDuplicates();
        $this->cleanServiceModesGroup();
        $this->pruneConfigs();
    }

    /**
     * Create the option groups the dimensions move into. The item types
     * themselves are retired by retireItemTypes(); this only builds the
     * destination.
     */
    private function dimensionsToOptions(): void
    {
        $groups = [
            'hall_capacity' => ['سعة القاعة', 'Hall Capacity', self::HALL_CAPACITY],
            'hall_class' => ['فئة القاعة', 'Hall Class', self::HALL_CLASS],
            'venue_amenities' => ['مرافق ومعدات', 'Venue Amenities', self::VENUE_AMENITIES],
        ];

        $sort = 1 + (int) OptionGroup::max('reorder');

        foreach ($groups as $key => [$ar, $en, $members]) {
            // option_groups has no `key` column — the Arabic name is the identity.
            $group = OptionGroup::updateOrCreate(
                ['name_ar' => $ar],
                ['name_en' => $en, 'reorder' => $sort++, 'is_active' => 1]
            );

            foreach ($members as [$optionAr, $optionEn]) {
                Option::updateOrCreate(
                    ['group_id' => $group->id, 'name_ar' => $optionAr],
                    ['name_en' => $optionEn]
                );
            }
        }
    }

    /** Deactivate + unbranch everything that is no longer an item type. */
    private function retireItemTypes(): void
    {
        $retire = array_merge(
            array_keys(self::HALL_CAPACITY),
            array_keys(self::HALL_CLASS),
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
