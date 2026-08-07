<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «هل كل الخدمات مربوطة بشكل صحيح» — is a service that is switched ON for a
 * child actually usable by a business under it?
 *
 * A service reaches a merchant through two rows that must agree:
 * `category_platform_services` says WHETHER, `category_service_configs` says
 * HOW. They are written together by ChildServiceWriter, but rows predating it
 * — and rows left behind by children that were later retired — can disagree,
 * and the disagreement is silent in both directions:
 *
 *   link on, config off/missing  → the panel offers the service and there is
 *       nothing to bound it; an empty `allowed_item_types` reads as «every
 *       type», so the owner is shown kinds the child was never meant to sell.
 *   config on, link off          → the configuration is there and unreachable.
 *
 * Found by this test on 2026-08-08: two live links pointed at child #298, a
 * child no longer in `category_children_master` and referenced by nothing else
 * (see the 298 note in the taxonomy-retirement work), and «مكتب» carried a live
 * booking link under «شحن وتوصيل» while its booking is declared only under
 * «عقارات وأراضي» — a shipping office switched on for stays.
 */
class ServiceWiringIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    /** Only live services matter: a link to a disabled service is inert. */
    private function liveLinks()
    {
        return DB::table('category_platform_services as l')
            ->join('platform_services as s', 's.id', '=', 'l.platform_service_id')
            ->where('l.is_active', 1)
            ->where('s.is_active', 1)
            ->whereNotNull('l.child_id');
    }

    /** A service a merchant is offered must have something saying how it works. */
    public function test_every_live_link_has_a_live_config(): void
    {
        $orphans = (clone $this->liveLinks())
            ->leftJoin('category_service_configs as c', function ($join) {
                $join->on('c.category_id', '=', 'l.category_id')
                    ->on('c.child_id', '=', 'l.child_id')
                    ->on('c.platform_service_id', '=', 'l.platform_service_id');
            })
            ->leftJoin('category_children_master as ch', 'ch.id', '=', 'l.child_id')
            ->where(fn ($q) => $q->whereNull('c.id')->orWhere('c.is_active', 0))
            ->get(['ch.name_ar', 's.key', 'l.category_id', 'l.child_id'])
            ->map(fn ($r) => ($r->name_ar ?: "#{$r->child_id}") . "/{$r->key}@{$r->category_id}");

        $this->assertEmpty(
            $orphans->all(),
            'a service is switched on with nothing configuring it: ' . $orphans->implode('، ')
        );
    }

    /** And a configuration nothing can reach is a configuration nobody applied. */
    public function test_every_live_config_has_a_live_link(): void
    {
        $stranded = DB::table('category_service_configs as c')
            ->join('platform_services as s', 's.id', '=', 'c.platform_service_id')
            ->leftJoin('category_children_master as ch', 'ch.id', '=', 'c.child_id')
            ->leftJoin('category_platform_services as l', function ($join) {
                $join->on('c.category_id', '=', 'l.category_id')
                    ->on('c.child_id', '=', 'l.child_id')
                    ->on('c.platform_service_id', '=', 'l.platform_service_id');
            })
            ->where('c.is_active', 1)
            ->where('s.is_active', 1)
            ->whereNotNull('c.child_id')
            ->where(fn ($q) => $q->whereNull('l.id')->orWhere('l.is_active', 0))
            ->get(['ch.name_ar', 's.key', 'c.category_id', 'c.child_id'])
            ->map(fn ($r) => ($r->name_ar ?: "#{$r->child_id}") . "/{$r->key}@{$r->category_id}");

        $this->assertEmpty(
            $stranded->all(),
            'a service is configured but unreachable: ' . $stranded->implode('، ')
        );
    }

    /**
     * A link to a child that no longer exists survives every child-level
     * cleanup, because every child-level query joins the child away.
     */
    public function test_no_live_link_points_at_a_child_that_is_gone(): void
    {
        $ghosts = (clone $this->liveLinks())
            ->leftJoin('category_children_master as ch', 'ch.id', '=', 'l.child_id')
            ->whereNull('ch.id')
            ->get(['l.child_id', 's.key', 'l.category_id'])
            ->map(fn ($r) => "#{$r->child_id}/{$r->key}@{$r->category_id}");

        $this->assertEmpty(
            $ghosts->all(),
            'these links point at a child that no longer exists: ' . $ghosts->implode('، ')
        );
    }

    /**
     * The same agreement, for a service that is switched off platform-wide.
     *
     * Nothing reads these rows while the service is off, which is exactly why
     * they rot: `business_offers` is derived every run from «does this child
     * have any typed service», and the seeder that derives it wrote the link
     * table alone. Re-deriving on 2026-08-08 moved 117 links — 78 children had
     * lost their last typed service and 39 had gained one — and left as many
     * configs disagreeing behind them. If the service is ever switched on, that
     * is what it switches on.
     */
    public function test_a_switched_off_service_is_still_wired_consistently(): void
    {
        $disagreeing = DB::table('category_platform_services as l')
            ->join('platform_services as s', 's.id', '=', 'l.platform_service_id')
            ->leftJoin('category_service_configs as c', function ($join) {
                $join->on('c.category_id', '=', 'l.category_id')
                    ->on('c.child_id', '=', 'l.child_id')
                    ->on('c.platform_service_id', '=', 'l.platform_service_id');
            })
            ->where('s.is_active', 0)
            ->whereNotNull('l.child_id')
            ->whereColumn('l.is_active', '!=', DB::raw('COALESCE(c.is_active, 0)'))
            ->count();

        $this->assertSame(0, $disagreeing, 'a dormant service would wake up half-wired');
    }

    /**
     * Every type a child is allowed to list must be a live type OF THAT
     * SERVICE — a key that is gone, retired, or borrowed from another service
     * silently narrows the owner panel to nothing.
     */
    public function test_every_allowed_type_is_a_live_type_of_its_own_service(): void
    {
        $index = DB::table('platform_service_item_types')
            ->get(['key', 'platform_service_id', 'is_active'])
            ->groupBy('platform_service_id')
            ->map(fn ($rows) => $rows->keyBy('key'));

        $bad = [];

        $rows = DB::table('category_service_configs as c')
            ->join('platform_services as s', 's.id', '=', 'c.platform_service_id')
            ->leftJoin('category_children_master as ch', 'ch.id', '=', 'c.child_id')
            ->where('c.is_active', 1)
            ->where('s.is_active', 1)
            ->get(['ch.name_ar', 's.key as service_key', 's.id as service_id', 'c.config']);

        foreach ($rows as $row) {
            $config = json_decode((string) $row->config, true) ?: [];

            foreach (($config['allowed_item_types'] ?? []) as $key) {
                $key = trim((string) $key);

                if ($key === '') {
                    continue;
                }

                $type = $index[$row->service_id][$key] ?? null;

                if ($type === null || (int) $type->is_active === 0) {
                    $bad[] = "{$row->name_ar}/{$row->service_key}: {$key}";
                }
            }
        }

        $this->assertEmpty(
            array_unique($bad),
            'these children allow a type that is not a live type of their service: '
                . implode('، ', array_unique($bad))
        );
    }
}
