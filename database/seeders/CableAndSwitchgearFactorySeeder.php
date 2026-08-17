<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «مفاتيح» under «مصانع» becomes «كابلات وقواطع كهرباء».
 *
 *     php artisan db:seed --class=CableAndSwitchgearFactorySeeder
 *
 * «الابن مفاتيح تحت الاب مصانع قم بتعديله الى لمصنع كابلات وقواطع كهرباء مثل
 * مصنع السويدى للكابلات وعدل خيارته الى ما يناسب» — owner, 2026-08-16.
 *
 * #159 was one child row meaning two trades: the man on the corner who cuts a
 * key, and a factory making switches and distribution boards. That was already
 * on record — TWO_TRADES_BY_DESIGN names it, and both roots were scoped so
 * neither could see the other's list. A name cannot be scoped, though, so the
 * factory was still called «مفاتيح», which is the shop's word.
 *
 * So the two are separated properly: the factory becomes a child of its own and
 * #159 keeps «المحلات» alone, as the locksmith it is. It is the reverse of a
 * fold and the same care — the wiring moves BEFORE the root is let go, because
 * a detachment clears what names the root it is leaving.
 *
 * ## The vocabulary it was given
 *
 * The list it inherits has one cable row — «كابلات وأسلاك» — for a factory
 * named after cables. El Sewedy's product line is copper and aluminium, low
 * and medium voltage, control and signal; six rows are added for that, and the
 * seven switchgear rows stay, because the same factories make both.
 *
 * ## What it does NOT get, and why
 *
 * Retail. #159 carried a `retail` shelf under «مصانع» whose only item type was
 * `keys_locks` — the locksmith's, and the plainest evidence that one row was
 * doing two jobs. The catalog has no shelf for cable: the nearest is
 * `power_hand_tools`, which is «عدد وأدوات كهربائية», and inventing an item
 * type is the catalog axis and the owner's. It sells through delivery and the
 * offers surface until he says otherwise.
 *
 * Idempotent.
 */
class CableAndSwitchgearFactorySeeder extends Seeder
{
    private const LOCKSMITH_ID = 159;

    private const NAME_AR = 'كابلات وقواطع كهرباء';

    private const NAME_EN = 'Cables & Switchgear';

    private const GROUP = 'المفاتيح والتوزيع الكهربائي';

    private const ROOT = 'factories';

    /** Carried over from #159; a factory sells by the load and through offers. */
    private const SERVICES = ['delivery', 'business_offers'];

    /**
     * …and a surface to sell FROM, which delivery alone is not.
     *
     * «حجز بدون توصيل» has a mirror: a lorry and no cargo. A child carrying
     * delivery and no goods surface can be reached and can list nothing, and
     * BookingWithoutDeliveryTest calls it out by name.
     *
     * `menu_market`, which is the answer UnsellableChildrenSeeder gave the
     * eighteen children in this position on 2026-08-09 — «كرڤان», «معدات
     * سوبرماركت», «مصاعد وسلم كهرباء», all factory goods with no catalog shelf.
     * Its own thirteen-row line group IS the list it sells from.
     */
    private const MENU_CONFIG = [
        'has_variants' => false,
        'has_addons' => false,
        'supports_notes' => false,
        'supports_stock' => false,
        'allowed_item_types' => ['menu_market'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $rootId = (int) DB::table('categories')->where('slug', self::ROOT)->value('id');

            if ($rootId <= 0) {
                $this->command?->warn('  ! «' . self::ROOT . '» غير موجود — تُخطّى.');

                return;
            }

            $this->command?->info('Cable & switchgear factory:');

            $childId = $this->child();

            if (! DB::table('category_parent_child')->where('parent_id', $rootId)->where('child_id', $childId)->exists()) {
                DB::table('category_parent_child')->insert([
                    'parent_id' => $rootId,
                    'child_id' => $childId,
                    'updated_at' => now(),
                ]);
            }

            $moved = $this->takeOverOptions($childId, $rootId);
            $wired = $this->takeOverServices($childId, $rootId);
            $wired += $this->giveItSomethingToSellFrom($childId, $rootId);
            $left = $this->releaseTheLocksmith($rootId);

            $this->command?->line("  - «" . self::NAME_AR . "» #{$childId} : خيارات نُقلت {$moved} · خدمات {$wired}");
            $this->command?->line('  - «مفاتيح» #' . self::LOCKSMITH_ID . ' : ' . ($left ? 'تُرك جذر «مصانع»' : 'ليس تحت «مصانع» أصلًا'));
        });
    }

    private function child(): int
    {
        $id = (int) DB::table('category_children_master')->where('name_ar', self::NAME_AR)->value('id');

        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('category_children_master')->insertGetId([
            'name_ar' => self::NAME_AR,
            'name_en' => self::NAME_EN,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Every option row #159 held AGAINST this root moves to the new child.
     *
     * Root-scoped only. The locksmith's shared rows are his trade's and stay
     * with him; what named «مصانع» was always the factory's.
     */
    private function takeOverOptions(int $childId, int $rootId): int
    {
        $rows = DB::table('category_child_option')
            ->where('child_id', self::LOCKSMITH_ID)->where('category_id', $rootId)
            ->get(['option_id', 'reorder']);

        $moved = 0;

        foreach ($rows as $row) {
            $moved += DB::table('category_child_option')->insertOrIgnore([
                'child_id' => $childId,
                'category_id' => $rootId,
                'option_id' => (int) $row->option_id,
                'reorder' => (int) $row->reorder,
            ]);
        }

        return $moved;
    }

    /** …and the services, with the config the factory was already running. */
    private function takeOverServices(int $childId, int $rootId): int
    {
        $writer = app(ChildServiceWriter::class);
        $wired = 0;

        foreach (self::SERVICES as $key) {
            $serviceId = (int) DB::table('platform_services')->where('key', $key)->value('id');

            if ($serviceId <= 0) {
                continue;
            }

            $live = DB::table('category_platform_services')
                ->where('category_id', $rootId)->where('child_id', $childId)
                ->where('platform_service_id', $serviceId)->where('is_active', 1)->exists();

            if ($live) {
                continue;
            }

            $config = json_decode((string) DB::table('category_service_configs')
                ->where('category_id', $rootId)->where('child_id', self::LOCKSMITH_ID)
                ->where('platform_service_id', $serviceId)->value('config'), true) ?: [];

            $writer->enable($rootId, $childId, $serviceId, $config, null, null, 'cable-switchgear-split');
            $wired++;
        }

        return $wired;
    }

    /** The market surface, with the branch its siblings in this position use. */
    private function giveItSomethingToSellFrom(int $childId, int $rootId): int
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'menu')->value('id');

        if ($serviceId <= 0) {
            return 0;
        }

        $live = DB::table('category_platform_services')
            ->where('category_id', $rootId)->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)->where('is_active', 1)->exists();

        if ($live) {
            return 0;
        }

        $config = self::MENU_CONFIG;

        // The branch is resolved rather than hard-coded: the id moves when the
        // menu branches are rebuilt, and a stale one is a config pointing at
        // nothing.
        $branchId = (int) DB::table('platform_service_item_groups')
            ->where('platform_service_id', $serviceId)->where('key', 'market_goods')->value('id');

        if ($branchId <= 0) {
            $branchId = (int) DB::table('platform_service_item_groups as g')
                ->join('platform_service_item_types as t', 't.platform_service_id', '=', 'g.platform_service_id')
                ->join('platform_service_item_group_type as p', 'p.group_id', '=', 'g.id')
                ->where('g.platform_service_id', $serviceId)->where('t.key', 'menu_market')
                ->whereColumn('p.item_type_id', 't.id')->value('g.id');
        }

        if ($branchId > 0) {
            $config['item_groups'] = [$branchId];
        }

        app(ChildServiceWriter::class)->enable($rootId, $childId, $serviceId, $config, null, null, 'cable-switchgear-split');

        return 1;
    }

    /**
     * The locksmith lets go of «مصانع», and everything that named it goes too.
     *
     * The order is the whole safety of this: the options and services were
     * copied above, so what is deleted here is a duplicate rather than the only
     * copy. The decisions ledger is NOT touched — `byChild()` reads a withdrawal
     * without looking at its root, so deleting those rows would hand back, under
     * «المحلات», every word the owner has taken off the locksmith anywhere.
     */
    private function releaseTheLocksmith(int $rootId): bool
    {
        $stood = DB::table('category_parent_child')
            ->where('parent_id', $rootId)->where('child_id', self::LOCKSMITH_ID)->exists();

        if (! $stood) {
            return false;
        }

        foreach (['category_child_option', 'category_platform_services', 'category_service_configs', 'category_child_service_fees'] as $table) {
            DB::table($table)->where('child_id', self::LOCKSMITH_ID)->where('category_id', $rootId)->delete();
        }

        DB::table('category_parent_child')
            ->where('parent_id', $rootId)->where('child_id', self::LOCKSMITH_ID)->delete();

        return true;
    }
}
