<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Guards the split of «أنماط خدمة وتجارية» into eight single-question groups
 * and the service scope that went with it.
 *
 * @see \Database\Seeders\ChildOptionGroupsSeeder
 * @see \Database\Seeders\ChildServiceScopeSeeder
 */
class ChildOptionRedistributionTest extends TestCase
{
    // Every seeder these run writes to the LIVE dev database. Without this
    // a single test run leaves the taxonomy changed for the next one — which
    // is exactly how «ملابس» lost all 29 of its options mid-suite.
    use DatabaseTransactions;

    /** The grab-bag is emptied, not re-filled by a later screen save. */
    public function test_the_commerce_grab_bag_holds_no_options(): void
    {
        $groupId = DB::table('option_groups')->where('name_ar', 'أنماط خدمة وتجارية')->value('id');

        if (! $groupId) {
            $this->markTestSkipped('The grab-bag group is gone entirely, which is also fine.');
        }

        $this->assertSame(
            0,
            DB::table('options')->where('group_id', $groupId)->count(),
            'the 24 options were split into eight groups; anything back in here is a regression'
        );
    }

    /** A craftsman is never asked whether he exports or sells wholesale. */
    public function test_field_trades_are_not_offered_wholesale_or_export(): void
    {
        $tradeScope = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'نطاق التعامل')
            ->pluck('o.id');

        $this->assertNotEmpty($tradeScope, 'the trade-scope group must exist');

        // نقاش, سباك and كهربائي sell labour; none of them import anything
        $offenders = DB::table('category_child_option')
            ->whereIn('child_id', [206, 227, 89])
            ->whereIn('option_id', $tradeScope)
            ->count();

        $this->assertSame(0, $offenders, 'a painter, a plumber and an electrician have no trade scope to declare');
    }

    /** Every option a business already chose is still offered by its child. */
    public function test_no_merchant_selection_was_orphaned(): void
    {
        $orphans = DB::table('option_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->whereNotNull('u.category_child_id')
            ->whereNotExists(function ($q) {
                $q->from('category_child_option as co')
                    ->whereColumn('co.child_id', 'u.category_child_id')
                    ->whereColumn('co.option_id', 'ou.option_id');
            })
            ->count();

        $this->assertSame(0, $orphans, 'redistribution must never strip an option a merchant had already ticked');
    }

    /** A hotel declares its facilities; the grab-bag never described one. */
    public function test_hotels_carry_facilities_and_not_factory_terms(): void
    {
        $hotel = DB::table('category_children_master')->where('name_ar', 'فندق')->value('id');

        if (! $hotel) {
            $this->markTestSkipped('No hospitality taxonomy in this database.');
        }

        $groups = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $hotel)
            ->distinct()
            ->pluck('g.name_ar');

        $this->assertContains('مرافق الإقامة', $groups->all());
        $this->assertNotContains('نطاق التعامل', $groups->all(), 'a hotel does not export');
    }

    /**
     * «ضيّق تيك أواى وتسليم أرض المصنع» — owner, 2026-08-16.
     *
     * A bundle is granted per TRADE, and two rows of the fulfilment bundle are
     * narrower than the trade that carries it. Handing them out with the bundle
     * is how «تيك أواى» reached a gold dealer, a marble yard and a freight
     * company, and how «تسليم أرض المصنع» reached a juice bar.
     *
     * Each list is the option's own name read literally: the second one says
     * FACTORY GROUNDS, so it is every child under «مصانع» plus the trades whose
     * goods leave on the buyer's lorry. The first is a counter's word.
     *
     * @dataProvider narrowedFulfilmentRows
     */
    public function test_a_fulfilment_row_narrower_than_its_bundle_reaches_only_its_trades(
        string $option,
        array $mustNot,
    ): void {
        $carriers = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('category_children_master as c', 'c.id', '=', 'co.child_id')
            // A child detached from every root is unreachable — `idsFor()` is
            // only ever called with a root the child IS under — so its links
            // are a retirement's leftovers and not this rule's business.
            ->whereExists(fn ($q) => $q->from('category_parent_child as pc')->whereColumn('pc.child_id', 'c.id'))
            ->where('o.name_ar', $option)
            ->distinct()->pluck('c.name_ar')->all();

        foreach ($mustNot as $trade) {
            $this->assertNotContains($trade, $carriers, "«{$trade}» has no business saying «{$option}»");
        }
    }

    /** @return array<string,array{0:string,1:array<int,string>}> */
    public static function narrowedFulfilmentRows(): array
    {
        return [
            // A counter's word. NOT asserted: «صينى وخزف», which the owner
            // pinned by hand at 03:58 on 2026-08-16 — one child, one save, and
            // a pin outranks any map.
            'تيك أواى' => ['تيك أواى', ['ذهب', 'رخام', 'حديد تسليح', 'معدات ثقيلة', 'شحن بري وبحري وجوى', 'سوبر ماركت']],
            // A factory's word. The service companies still holding it were
            // pinned root-wide under «شركات» in two seconds on 2026-08-11 and
            // are reported rather than reverted — a hand save is his.
            'تسليم أرض المصنع' => ['تسليم أرض المصنع', ['ذهب', 'معرض سيارات', 'معرض موتوسيكلات', 'خضار وفاكهة', 'قطع غيار', 'تبريد وتكييف']],
        ];
    }

    /**
     * «ارجع تثبيت شركات» — owner, 2026-08-16.
     *
     * A bulk save under «شركات» on 2026-08-11 23:41, two timestamps one second
     * apart, pinned an entire GOODS vocabulary onto eleven service companies:
     * تصدير، إستيراد، تجزئة، جملة، جديد، مستعمل، تغيير، استبدال، شحن، تسليم أرض
     * المصنع، توصيل مجانى. An insurance company was answering «جديد / مستعمل»
     * and a money-transfer office was quoting ex-works.
     *
     * The pins were what kept them there: a pin outranks the map, so the
     * narrowing could not reach them and neither could ChildOptionGroupsSeeder.
     *
     * Reverted per CHILD, not per save. The same two timestamps legitimately
     * gave 26 goods children their كاش/تقسيط and their trade scope, and undoing
     * that would be the accident again in the other direction. Which children
     * are service is not my reading either — it is `child_option_groups.php`'s
     * own bundle for them under «شركات»: service_mode and payment_terms, and
     * nothing that describes goods.
     */
    public function test_no_service_company_answers_a_goods_question(): void
    {
        $map = require database_path('seeders/data/child_option_groups.php');
        $goodsBundles = ['trade_scope', 'product_condition', 'fulfilment', 'returns_policy'];

        $companies = (int) DB::table('categories')->where('slug', 'companies')->value('id');

        $offenders = [];

        foreach (DB::table('category_parent_child')->where('parent_id', $companies)->pluck('child_id') as $childId) {
            $bundle = $map['child_overrides']["companies:{$childId}"] ?? $map['root_defaults']['companies'];

            if (array_intersect($bundle, $goodsBundles)) {
                continue;   // a company that really does sell goods
            }

            $held = DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->join('category_children_master as c', 'c.id', '=', 'co.child_id')
                ->where('co.child_id', $childId)
                ->whereIn('g.name_ar', ['التسليم والاستلام', 'نطاق التعامل', 'حالة المنتج', 'الاستبدال والإرجاع'])
                ->distinct()->pluck(DB::raw("concat(c.name_ar, ' · ', o.name_ar) as label"))->all();

            $offenders = array_merge($offenders, $held);
        }

        $this->assertSame([], $offenders, 'a service company is answering about goods: ' . implode('، ', $offenders));
    }

    /** The trades each word was kept for are still able to say it. */
    public function test_the_narrowing_did_not_silence_the_trades_the_words_belong_to(): void
    {
        $carries = fn (string $child, string $option) => DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('category_children_master as c', 'c.id', '=', 'co.child_id')
            ->where('c.name_ar', $child)->where('o.name_ar', $option)->exists();

        foreach (['مطعم', 'كافيه', 'مجمع مطاعم', 'أكل بيتى', 'مطعم وكافيه'] as $kitchen) {
            $this->assertTrue($carries($kitchen, 'تيك أواى'), "«{$kitchen}» lost takeaway");
        }

        foreach (['طوب', 'اسمنت', 'حديد تسليح', 'أخشاب', 'معدات ثقيلة', 'مواشي وأرانب'] as $bulk) {
            $this->assertTrue($carries($bulk, 'تسليم أرض المصنع'), "«{$bulk}» lost ex-works");
        }
    }

    /**
     * An active service with an empty `allowed_item_types` is UNBOUNDED, not
     * empty: both readers treat the missing list as «no restriction», so the
     * child offers every type the service has.
     *
     * That was harmless while a branch was a coarse group and became wrong with
     * the kinds collapse — «صيدلية» came out of a bulk save on 2026-08-07 with
     * nothing ticked, which let a pharmacy take a hotel stay and a restaurant
     * table. BookingChildKindsSeeder bounds these from the declared branches.
     *
     * @see \App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog
     * @see \App\Support\CategoryBServiceSupport::allowsItemType()
     *
     * Scoped to services that actually declare item types
     * (`platform_service_item_types`). `business_offers` has none — it never
     * lists "types" per child; an offer names one already-priced row
     * (`OfferableResolver`), so `allowed_item_types` is not a concept that
     * service has, and an empty config there restricts nothing because there
     * is nothing to restrict. Its 313 `business_offers_enablement` configs
     * (2026-08-23) are the blanket "this child may publish offers" switch, not
     * an unbounded item-type list.
     */
    public function test_no_active_service_config_allows_nothing(): void
    {
        $empty = DB::table('category_service_configs as c')
            ->join('platform_services as s', 's.id', '=', 'c.platform_service_id')
            ->join('category_children_master as ch', 'ch.id', '=', 'c.child_id')
            ->where('c.is_active', 1)
            ->where('s.is_active', 1)
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('platform_service_item_types as t')
                    ->whereColumn('t.platform_service_id', 's.id');
            })
            ->get(['ch.name_ar', 's.key', 'c.config'])
            ->filter(function ($row) {
                $cfg = json_decode($row->config ?: '{}', true) ?: [];

                return empty($cfg['allowed_item_types']);
            })
            ->map(fn ($r) => "{$r->name_ar}/{$r->key}");

        $this->assertEmpty(
            $empty->all(),
            'these children have a live service with no bound on what they may list, '
                . 'so they offer every type it has: ' . $empty->implode('، ')
        );
    }

    /**
     * Discovery matches a business by its own classification AND a price row
     * for the same child, so a price left on a detached child is invisible.
     *
     * @see \Database\Seeders\StrandedPriceChildSeeder
     */
    public function test_no_price_row_points_at_a_child_with_no_root(): void
    {
        $linked = DB::table('category_parent_child')->distinct()->pluck('child_id');

        $stranded = DB::table('business_service_prices as p')
            ->join('users as u', 'u.id', '=', 'p.business_id')
            ->whereNotIn('p.child_id', $linked)
            ->whereIn('u.category_child_id', $linked)
            ->count();

        $this->assertSame(0, $stranded, 'a price on a rootless child is money the customer can never reach');
    }

    /**
     * A child under a root must be able to list something. `exhibitions` failed
     * this on all 28 of its children — they were wired to `retail` alone, which
     * is switched off — and `cars` on all seven of its own.
     */
    public function test_every_live_child_has_a_service_it_can_sell_under(): void
    {
        $mute = DB::table('category_parent_child as pc')
            ->join('category_children_master as ch', 'ch.id', '=', 'pc.child_id')
            ->join('categories as r', 'r.id', '=', 'pc.parent_id')
            ->whereNotExists(function ($q) {
                $q->from('category_service_configs as c')
                    ->join('platform_services as s', 's.id', '=', 'c.platform_service_id')
                    ->whereColumn('c.category_id', 'pc.parent_id')
                    ->whereColumn('c.child_id', 'pc.child_id')
                    ->where('c.is_active', 1)
                    ->where('s.is_active', 1);
            })
            ->distinct()
            ->pluck('ch.name_ar', 'r.slug');

        $this->assertEmpty($mute->all(), 'these children can list nothing: ' . $mute->implode('، '));
    }

    /**
     * The config says what MAY be listed; the service link is what the owner
     * panel and discovery actually read. A config without its link is a screen
     * no merchant can reach.
     */
    public function test_every_live_service_config_has_its_availability_link(): void
    {
        $unlinked = DB::table('category_service_configs as c')
            ->join('platform_services as s', 's.id', '=', 'c.platform_service_id')
            ->join('category_children_master as ch', 'ch.id', '=', 'c.child_id')
            ->where('c.is_active', 1)
            ->where('s.is_active', 1)
            ->whereExists(function ($q) {
                $q->from('category_parent_child as pc')
                    ->whereColumn('pc.parent_id', 'c.category_id')
                    ->whereColumn('pc.child_id', 'c.child_id');
            })
            ->whereNotExists(function ($q) {
                $q->from('category_platform_services as cps')
                    ->whereColumn('cps.category_id', 'c.category_id')
                    ->whereColumn('cps.child_id', 'c.child_id')
                    ->whereColumn('cps.platform_service_id', 'c.platform_service_id')
                    ->where('cps.is_active', 1);
            })
            ->count();

        $this->assertSame(
            0,
            $unlinked,
            'a live service config must be reachable through category_platform_services'
        );
    }

    /** The root that held 638 businesses and sold nothing. */
    public function test_the_limousine_child_can_sell_something(): void
    {
        $configs = DB::table('category_service_configs as c')
            ->join('platform_services as s', 's.id', '=', 'c.platform_service_id')
            ->where('c.child_id', 169)
            ->where('c.is_active', 1)
            ->where('s.is_active', 1)
            ->pluck('c.config');

        if ($configs->isEmpty()) {
            $this->markTestSkipped('The limousine child is absent from this database.');
        }

        $types = $configs->flatMap(fn ($c) => json_decode($c, true)['allowed_item_types'] ?? []);

        $this->assertNotEmpty($types, 'خدمة ليموزين must have at least one sellable item type');
    }
}
