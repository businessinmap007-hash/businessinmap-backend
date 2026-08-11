<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «حجز بدون توصيل هو حجز وقت او مدة فلا نستخدم خدمة التوصيل» — owner,
 * 2026-08-10, with «واستثنِ الثلاثة النجارين من قاعدة التوصيل».
 *
 * A rule, evaluated per (root, child): booking active, no menu and no retail →
 * no delivery. A نقاش is booked to come and paint your wall, and the delivery
 * service on him never meant anything.
 */
class BookingWithoutDeliveryTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<string,int> */
    private function services(): array
    {
        return DB::table('platform_services')->whereIn('key', ['booking', 'delivery', 'menu', 'retail'])
            ->pluck('id', 'key')->map(fn ($id) => (int) $id)->all();
    }

    /** Nothing that only sells time is left carrying delivery. */
    public function test_no_time_only_child_still_delivers(): void
    {
        $s = $this->services();
        $keep = (require database_path('seeders/data/booking_without_delivery.php'))['keep_delivery'];

        $offenders = [];

        foreach (DB::table('category_platform_services as b')
            ->join('category_parent_child as p', function ($join) {
                $join->on('p.parent_id', '=', 'b.category_id')->on('p.child_id', '=', 'b.child_id');
            })
            ->join('category_children_master as c', 'c.id', '=', 'b.child_id')
            ->join('categories as r', 'r.id', '=', 'b.category_id')
            ->where('b.platform_service_id', $s['booking'])->where('b.is_active', 1)
            ->get(['b.category_id', 'b.child_id', 'c.name_ar', 'r.slug']) as $row) {
            if (in_array($row->name_ar, $keep, true)) {
                continue;
            }

            $delivers = DB::table('category_platform_services')
                ->where('category_id', $row->category_id)->where('child_id', $row->child_id)
                ->where('platform_service_id', $s['delivery'])->where('is_active', 1)->exists();

            if (! $delivers) {
                continue;
            }

            $sellsGoods = DB::table('category_platform_services')
                ->where('category_id', $row->category_id)->where('child_id', $row->child_id)
                ->whereIn('platform_service_id', [$s['menu'], $s['retail']])->where('is_active', 1)->exists();

            if (! $sellsGoods) {
                $offenders[] = "{$row->name_ar}@{$row->slug}";
            }
        }

        $this->assertSame([], $offenders, 'these book time and still carry delivery: ' . implode('، ', $offenders));
    }

    /**
     * Delivery with nothing to deliver is a carrier or a named exception.
     *
     * The mirror of the rule above. That one asks «this books time, why does it
     * deliver?»; this asks «this delivers, what exactly?» — a child with
     * `delivery` active and neither `menu` nor `retail` has a lorry and no
     * cargo, which is either its whole trade or a mistake.
     *
     * Every case on the platform today is the former, and each has a record:
     * the three children of «شحن وتوصيل», for whom delivery IS the product,
     * and the seven in `keep_delivery` — the owner's three carpenters
     * («واستثنِ الثلاثة النجارين»), «طباعة», and the three freight carriers.
     * Nothing else may join them silently.
     */
    public function test_delivery_without_goods_is_a_carrier_or_a_named_exception(): void
    {
        $s = $this->services();

        $allowed = collect((require database_path('seeders/data/booking_without_delivery.php'))['keep_delivery'])
            ->merge(
                DB::table('category_parent_child as pc')
                    ->join('category_children_master as c', 'c.id', '=', 'pc.child_id')
                    ->join('categories as r', 'r.id', '=', 'pc.parent_id')
                    ->where('r.slug', 'shipping-delivery')->pluck('c.name_ar')
            )
            ->all();

        $orphans = [];

        foreach (
            DB::table('category_platform_services as b')
                ->join('category_children_master as c', 'c.id', '=', 'b.child_id')
                ->join('categories as r', 'r.id', '=', 'b.category_id')
                ->where('b.platform_service_id', $s['delivery'])->where('b.is_active', 1)
                ->get(['b.category_id', 'b.child_id', 'c.name_ar', 'r.slug']) as $row
        ) {
            if (in_array($row->name_ar, $allowed, true)) {
                continue;
            }

            $sellsGoods = DB::table('category_platform_services')
                ->where('category_id', $row->category_id)->where('child_id', $row->child_id)
                ->whereIn('platform_service_id', [$s['menu'], $s['retail']])
                ->where('is_active', 1)->exists();

            if (! $sellsGoods) {
                $orphans[] = "{$row->name_ar}@{$row->slug}";
            }
        }

        $this->assertSame([], $orphans, 'a lorry and no cargo: ' . implode('، ', $orphans));
    }

    /**
     * The owner's exception. A commissioned wardrobe still leaves the workshop
     * on a lorry, and the service wiring cannot see that.
     *
     * @dataProvider carpenters
     */
    public function test_the_three_carpenters_keep_delivery(string $nameAr, string $rootSlug): void
    {
        $s = $this->services();
        $rootId = (int) DB::table('categories')->where('slug', $rootSlug)->value('id');
        $childId = (int) DB::table('category_parent_child as p')
            ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
            ->where('p.parent_id', $rootId)->where('c.name_ar', $nameAr)->value('c.id');

        $this->assertGreaterThan(0, $childId, "«{$nameAr}» is not under «{$rootSlug}»");

        $this->assertTrue(
            DB::table('category_platform_services')->where('category_id', $rootId)->where('child_id', $childId)
                ->where('platform_service_id', $s['delivery'])->where('is_active', 1)->exists(),
            "«{$nameAr}» lost delivery — it makes a thing and the thing has to travel"
        );
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function carpenters(): array
    {
        return [
            'نجار موبيليا' => ['نجار موبيليا', 'professions'],
            'منجد' => ['منجد', 'professions'],
            'نجار باب وشباك' => ['نجار باب وشباك', 'workshops'],
        ];
    }

    /** A shop that books AND sells goods is untouched. */
    public function test_a_showroom_that_sells_goods_still_delivers(): void
    {
        $s = $this->services();
        $exhibitions = (int) DB::table('categories')->where('slug', 'exhibitions')->value('id');

        $childId = (int) DB::table('category_platform_services as b')
            ->join('category_platform_services as g', function ($join) use ($s) {
                $join->on('g.category_id', '=', 'b.category_id')->on('g.child_id', '=', 'b.child_id')
                    ->where('g.platform_service_id', '=', $s['retail'])->where('g.is_active', '=', 1);
            })
            ->where('b.category_id', $exhibitions)
            ->where('b.platform_service_id', $s['booking'])->where('b.is_active', 1)
            ->value('b.child_id');

        $this->assertGreaterThan(0, $childId, 'no showroom books and sells at once');

        $this->assertTrue(
            DB::table('category_platform_services')->where('category_id', $exhibitions)->where('child_id', $childId)
                ->where('platform_service_id', $s['delivery'])->where('is_active', 1)->exists()
        );
    }

    /**
     * The branch map must not hand delivery back. This is the failure that has
     * bitten three times in one day: a root-keyed add-only seeder naming a child
     * the rule stripped re-wires it on its own run.
     */
    public function test_the_delivery_map_does_not_re_wire_them(): void
    {
        $this->artisan('db:seed', ['--class' => 'DeliveryChildBranchesSeeder', '--no-interaction' => true])->run();

        $this->test_no_time_only_child_still_delivers();
    }
}
