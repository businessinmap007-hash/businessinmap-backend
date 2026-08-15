<?php

namespace Tests\Feature;

use App\Models\PlatformService;
use Database\Seeders\UnsellableChildrenSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «استمر في باقي التصنيفات» — owner, 2026-08-09, after approving the fashion
 * collapse.
 *
 * Measured across every root, the fashion disease is NOT widespread, and the
 * measurement is the point: of the 160 children carrying no `line` option, 0
 * have menu (every menu child already has a band), 64 have retail only (the
 * central catalog is their vocabulary), 78 have booking only (`direct_typed`,
 * where the item type IS the price list) — and 18 had NO selling service at all.
 *
 * Those 18 could be delivered from and could publish an offer, and could not
 * list, price or be booked. Seventeen real businesses sat on four of them.
 */
class UnsellableChildrenTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * A child with no way to sell anything.
     *
     * `delivery` counts, and the six it rescues are the reason: for شركة شحن،
     * مكتب، مندوب، نقل دولي، شحن بري وبحري وجوى and معدات ثقيلة the delivery
     * service IS the product, priced on its own rows. On a fertiliser shop the
     * same service means only «we deliver» — same table, opposite meaning, and
     * the trade decides which, not the schema. So delivery is accepted here and
     * the goods children were still given a list of their own.
     *
     * `business_offers` never counts: an offer points AT something priced, so a
     * child with nothing else can publish an advertisement for nothing.
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    private function silentAndUnsellable()
    {
        $selling = PlatformService::query()
            ->whereIn('key', [
                PlatformService::KEY_MENU,
                PlatformService::KEY_RETAIL,
                PlatformService::KEY_BOOKING,
                PlatformService::KEY_DELIVERY,
            ])
            ->pluck('id');

        return DB::table('category_children_master as c')
            ->whereExists(fn ($q) => $q->from('category_parent_child as pc')->whereColumn('pc.child_id', 'c.id'))
            ->whereNotExists(function ($q) use ($selling) {
                $q->from('category_platform_services as l')
                    ->whereColumn('l.child_id', 'c.id')
                    ->where('l.is_active', 1)
                    ->whereIn('l.platform_service_id', $selling);
            })
            ->pluck('c.id');
    }

    /** Every child a customer can reach must be able to sell something. */
    public function test_no_reachable_child_is_left_unable_to_sell(): void
    {
        $stuck = $this->silentAndUnsellable();

        $names = DB::table('category_children_master')->whereIn('id', $stuck)->pluck('name_ar');

        $this->assertEmpty(
            $stuck->all(),
            'these children can be delivered from but can sell nothing: ' . $names->implode('، ')
        );
    }

    /**
     * And the stronger claim for the declared eighteen: each now has a list or
     * a booking of its own, not merely a delivery van.
     */
    public function test_every_declared_child_can_now_list_or_be_booked(): void
    {
        $map = require database_path('seeders/data/unsellable_children.php');
        $names = array_merge($map['goods'] ?? [], $map['service'] ?? []);

        $ids = DB::table('category_children_master')->whereIn('name_ar', $names)->pluck('id');

        $this->assertCount(count($names), $ids, 'a declared child is missing from the taxonomy');

        $sellable = DB::table('category_platform_services as l')
            ->join('platform_services as s', 's.id', '=', 'l.platform_service_id')
            ->whereIn('l.child_id', $ids)
            ->where('l.is_active', 1)
            ->whereIn('s.key', [
                PlatformService::KEY_MENU,
                PlatformService::KEY_RETAIL,
                PlatformService::KEY_BOOKING,
            ])
            ->pluck('l.child_id')
            ->unique();

        $missing = $ids->diff($sellable);

        $this->assertEmpty(
            $missing->all(),
            'still cannot list or be booked: ' . DB::table('category_children_master')
                ->whereIn('id', $missing)->pluck('name_ar')->implode('، ')
        );
    }

    /**
     * Goods got MENU rather than retail on purpose: retail lists from the
     * central catalog, which holds no seeds, feed or fertiliser, and an empty
     * `allowed_item_types` means EVERY type — so retail would have handed a
     * fertiliser merchant all 75 buckets and let him list none of them.
     */
    public function test_a_goods_child_writes_its_own_list(): void
    {
        $map = require database_path('seeders/data/unsellable_children.php');

        $menu = (int) PlatformService::query()->where('key', PlatformService::KEY_MENU)->value('id');
        $childId = (int) DB::table('category_children_master')->where('name_ar', $map['goods'][0])->value('id');

        $config = json_decode((string) DB::table('category_service_configs')
            ->where('child_id', $childId)->where('platform_service_id', $menu)
            ->where('is_active', 1)->value('config'), true) ?: [];

        $this->assertSame(['menu_market'], $config['allowed_item_types'] ?? []);
    }

    /**
     * A security company sells an appointment, not a unit out of an inventory.
     *
     * What is held is the SHAPE, not the exact list. This used to assert
     * «exactly booking_appointment», which was the list the seeder wrote into a
     * silence — and the test below this one is called «the seeder fills a
     * silence; it never overrides a later choice». The owner named three more
     * kinds from the bulk screen on 2026-08-14 (استشارة، استشارة أونلاين،
     * حجز زيارة, all recorded in `service_kinds.php`), and freezing the seeded
     * list here would make his answer look like a regression.
     *
     * The claim that matters survives every one of them: it books DIRECTLY —
     * no bookable item, and the plain appointment still among what it offers.
     */
    public function test_a_service_child_books_directly(): void
    {
        $map = require database_path('seeders/data/unsellable_children.php');

        $booking = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');
        $childId = (int) DB::table('category_children_master')->where('name_ar', $map['service'][0])->value('id');

        $config = json_decode((string) DB::table('category_service_configs')
            ->where('child_id', $childId)->where('platform_service_id', $booking)
            ->where('is_active', 1)->value('config'), true) ?: [];

        $this->assertContains('booking_appointment', $config['allowed_item_types'] ?? []);
        $this->assertNotContains('booking_stay', $config['allowed_item_types'] ?? [],
            'a security firm does not hold a unit for a period');
        $this->assertFalse((bool) ($config['requires_bookable_item'] ?? false));
    }

    /** The seeder fills a silence; it never overrides a later choice. */
    public function test_it_leaves_a_child_that_was_given_a_service_since(): void
    {
        $map = require database_path('seeders/data/unsellable_children.php');
        $childId = (int) DB::table('category_children_master')->where('name_ar', $map['goods'][0])->value('id');
        $retail = (int) PlatformService::query()->where('key', PlatformService::KEY_RETAIL)->value('id');
        $rootId = (int) DB::table('category_parent_child')->where('child_id', $childId)->value('parent_id');

        // Somebody switches it to retail by hand, and switches menu off.
        DB::table('category_platform_services')->where('child_id', $childId)->update(['is_active' => 0]);
        DB::table('category_platform_services')->insert([
            'category_id' => $rootId, 'child_id' => $childId,
            'platform_service_id' => $retail, 'is_active' => 1,
        ]);

        (new UnsellableChildrenSeeder)->run();

        $this->assertSame(
            0,
            DB::table('category_platform_services as l')
                ->join('platform_services as s', 's.id', '=', 'l.platform_service_id')
                ->where('l.child_id', $childId)->where('l.is_active', 1)
                ->where('s.key', PlatformService::KEY_MENU)->count(),
            'the seeder overrode a choice made after its list was written'
        );
    }

    /** Re-running changes nothing. */
    public function test_the_seeder_is_idempotent(): void
    {
        $before = [
            DB::table('category_platform_services')->count(),
            DB::table('category_service_configs')->count(),
        ];

        (new UnsellableChildrenSeeder)->run();

        $this->assertSame($before, [
            DB::table('category_platform_services')->count(),
            DB::table('category_service_configs')->count(),
        ]);
    }
}
