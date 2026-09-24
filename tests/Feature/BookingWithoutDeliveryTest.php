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
     * «نلغى خدمة التوصيل ونحن لدينا مجموعة خيارات استلام وتسليم» — المالك،
     * 2026-09-24. This test used to guard the three carpenters and the goods
     * showrooms KEEPING delivery; the service is now retired for every
     * business, so what is guarded is the opposite: nothing outside the
     * carriers («شحن وتوصيل») carries it, and RetireDeliveryServiceSeeder
     * is what keeps a full re-seed from bringing it back.
     */
    public function test_delivery_is_retired_everywhere_but_the_carriers(): void
    {
        $s = $this->services();
        $carriers = (int) DB::table('categories')->where('slug', 'shipping-delivery')->value('id');

        $live = DB::table('category_platform_services as l')
            ->join('category_children_master as c', 'c.id', '=', 'l.child_id')
            ->where('l.platform_service_id', $s['delivery'])->where('l.is_active', 1)
            ->where('l.category_id', '!=', $carriers)
            ->pluck('c.name_ar', 'l.id');

        $this->assertSame([], $live->values()->all(), 'delivery is a service of the carriers only');
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
