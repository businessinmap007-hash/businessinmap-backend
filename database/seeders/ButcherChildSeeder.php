<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «انشئ فى محلات محل جزارة كإبن جديد وضيف له منتجاته» — المالك، 2026-08-24.
 *
 *     php artisan db:seed --class=ButcherChildSeeder
 *
 * The gap this closes was found the day before, writing «أنواع اللحوم»: the
 * platform had a word for the meat FRIDGE inside a supermarket's list and no
 * trade that sells meat. «لحوم ودواجن» reached one child — «مجمدات» — so a
 * butcher signing up had to call himself a frozen-food shop.
 *
 * ── What it sells, and what it does not ─────────────────────────────────────
 *
 * `menu` is the counter: «كندوز — ٣٥٠ / كجم» is a priced row with a sale unit,
 * which is exactly what `menu_items` is. `delivery` because meat is delivered,
 * and `business_offers` like every shop beside it.
 *
 * **No `retail`.** That service lists catalog products — barcoded SKUs on a
 * shelf — and a butcher weighs what he cuts. Giving it to him would put an
 * empty product catalogue on his panel and a shelf he can never fill.
 *
 * ── The vocabulary is borrowed, not cloned ──────────────────────────────────
 *
 * «أنواع اللحوم» (15) was written for the meat counter and «أنواع الدواجن
 * والطيور» (11) for the poultry trade. A butcher sells both off one counter,
 * so he takes both lists whole — a third list saying كندوز again is this
 * taxonomy's oldest disease.
 *
 * Idempotent.
 */
class ButcherChildSeeder extends Seeder
{
    private const NAME_AR = 'جزارة';

    private const NAME_EN = 'Butcher';

    private const ROOT = 'المحلات أو أونلاين';

    /** The two lists a butcher's counter is made of. */
    private const GROUPS = ['أنواع اللحوم', 'أنواع الدواجن والطيور'];

    /**
     * The one modifier: what the price is PER.
     *
     * Two rows and not thirteen, on the same reading «مواشي وأرانب» was given
     * in August — a butcher weighs the cut and sells the lamb whole, and
     * nobody buys meat «بالأردب». Without it he has no price axis at all,
     * which `ChildTradeVocabulariesTest` refuses for any trade nobody decided
     * it for.
     */
    private const UNITS = ['بالكيلو', 'بالرأس'];

    /**
     * The shop beside him, whose answers he inherits.
     *
     * A brand-new child inherits nothing — every platform-wide grant ran
     * before it existed — and the first cut of this seeder granted whole
     * groups instead. That broke six tests in one run: «الدفع والسداد» holds
     * «دفع مسبق», which is scoped to carriers, and «تقسيط بدون فوائد», which
     * is scoped by a pin. Handing a butcher a whole group hands him every
     * ruling the platform has ever made INSIDE it, backwards.
     *
     * So the axes are copied from «مجمدات» — a food shop under this root and
     * nothing else — where every one of those rulings is already recorded.
     * The line groups above are still granted whole: they were written for
     * this counter.
     */
    private const MIRRORS = 'مجمدات';

    public function run(): void
    {
        DB::transaction(function () {
            $rootId = (int) DB::table('categories')->where('name_ar', self::ROOT)->where('parent_id', 0)->value('id');

            if ($rootId <= 0) {
                $this->command?->warn('  ! جذر «' . self::ROOT . '» غير موجود.');

                return;
            }

            $childId = $this->child();

            DB::table('category_parent_child')->insertOrIgnore([
                'parent_id' => $rootId,
                'child_id' => $childId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $linked = $this->linkOptions($childId);
            $wired = $this->wireServices($childId, $rootId);

            $this->command?->info('Butcher child:');
            $this->command?->line("  - «" . self::NAME_AR . "» #{$childId}");
            $this->command?->line("  - خيارات رُبطت : {$linked}");
            $this->command?->line("  - خدمات وُصلت : {$wired}");
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
            'reorder' => (int) DB::table('category_children_master')->max('reorder') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Shared links (`category_id = 0`): a butcher is a butcher under whatever
     * root he is later attached to.
     *
     * The withdrawal ledger is consulted first, like every other option
     * seeder — the owner may take a cut off this counter tomorrow, and a
     * seeder that hands it back is the failure five others had to be taught
     * out of.
     */
    private function linkOptions(int $childId): int
    {
        $lines = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->whereIn('g.name_ar', self::GROUPS)
            ->pluck('o.id');

        $units = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'وحدة البيع')
            ->whereIn('o.name_ar', self::UNITS)
            ->pluck('o.id');

        // Everything the mirror child answers that is NOT one of its own
        // priced lists: a fishmonger's fish are his, his delivery terms are
        // every food shop's.
        $mirrorId = (int) DB::table('category_children_master')->where('name_ar', self::MIRRORS)->value('id');

        $axes = $mirrorId <= 0 ? collect() : DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $mirrorId)
            ->where('g.price_role', '!=', 'line')
            ->pluck('o.id');

        $wanted = $lines->merge($units)->merge($axes)->unique()->map(fn ($id) => (int) $id)->values()->all();

        $allowed = app(ChildOptionDecisions::class)->filter($childId, 0, $wanted);

        $n = 0;

        foreach ($allowed as $optionId) {
            $exists = DB::table('category_child_option')
                ->where('child_id', $childId)
                ->where('category_id', 0)
                ->where('option_id', $optionId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('category_child_option')->insert([
                'child_id' => $childId,
                'category_id' => 0,
                'option_id' => $optionId,
                'reorder' => 0,
            ]);

            $n++;
        }

        return $n;
    }

    /**
     * Link AND config, through the one writer that keeps them in step.
     *
     * `allowed_item_types` is bound rather than left empty: an empty array
     * means «every type this service has», which is how a butcher ends up
     * offered a hotel stay. `menu_market` is the shop counter type the
     * fishmonger next door already uses.
     */
    private function wireServices(int $childId, int $rootId): int
    {
        $writer = app(ChildServiceWriter::class);

        $plan = [
            'menu' => [
                'has_variants' => true,          // «ضاني» يُباع قطعًا مختلفة
                'has_addons' => false,
                'supports_notes' => true,        // «مفروم ناعم», «بدون دهن»
                'supports_stock' => false,
                'item_groups' => [85],
                'allowed_item_types' => ['menu_market'],
            ],
            'delivery' => [
                'has_delivery' => true,
                'delivery_type' => 'distance',
                'max_radius_km' => 0,
                'supports_scheduled_delivery' => false,
                'item_groups' => [8, 19],
                /*
                 * Exactly the five the `delivery` + `delivery_coldchain`
                 * branches resolve to — read from the map, not copied from a
                 * neighbour. `DeliveryChildBranchesSeeder` OWNS this field and
                 * rewrites it on every run, so a list of my own is a config
                 * that changes under me («changed on a re-run»).
                 *
                 * The sixth «أسماك» carries is `grocery_delivery`, and that is
                 * a trade's own mechanism rather than a shared one: a butcher
                 * offered it is a butcher offered somebody else's van, which
                 * `ServiceKindsPruneTest` refuses by name.
                 */
                'allowed_item_types' => [
                    'delivery', 'express_delivery', 'scheduled_delivery',
                    'frozen_transport', 'refrigerated_delivery',
                ],
            ],
            'business_offers' => [],
        ];

        $n = 0;

        foreach ($plan as $key => $config) {
            $serviceId = (int) DB::table('platform_services')->where('key', $key)->value('id');

            if ($serviceId <= 0) {
                $this->command?->warn("  ! خدمة «{$key}» غير موجودة.");

                continue;
            }

            $writer->enable($rootId, $childId, $serviceId, $config, null, null, 'butcher-child');
            $n++;
        }

        return $n;
    }
}
