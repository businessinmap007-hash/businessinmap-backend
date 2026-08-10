<?php

namespace Tests\Feature;

use App\Models\PlatformService;
use App\Services\Catalog\ChildServiceWriter;
use Database\Seeders\BoundUnboundedConfigsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * An empty `allowed_item_types` is the quietest way to say «everything».
 *
 * Both readers treat a missing list as «no restriction» — ResolvesOwnerCatalog
 * filters only `->when(! empty($restricted))`, and
 * CategoryBServiceSupport::allowsItemType() returns true outright. So a child
 * saved with nothing ticked does not lose its service; it gains every type the
 * service owns. «صيدلية» came out of such a save able to take a hotel stay, and
 * «خدمة ليموزين» able to run every delivery mechanism on the platform.
 */
class BoundUnboundedConfigsTest extends TestCase
{
    use DatabaseTransactions;

    private function bound(): void
    {
        (new BoundUnboundedConfigsSeeder)->run();
    }

    private function typesOf(int $rootId, int $childId, int $serviceId): array
    {
        return app(ChildServiceWriter::class)
            ->storedConfig($rootId, $childId, $serviceId)['allowed_item_types'] ?? [];
    }

    /** The live answer to «هل كل الخدمات مربوطة بشكل صحيح». */
    public function test_no_live_config_is_left_unbounded(): void
    {
        $loose = DB::table('category_service_configs as c')
            ->join('platform_services as s', 's.id', '=', 'c.platform_service_id')
            ->leftJoin('category_children_master as ch', 'ch.id', '=', 'c.child_id')
            ->where('c.is_active', 1)
            ->where('s.is_active', 1)
            ->whereNotNull('c.child_id')
            // A service with no item types at all cannot bound anything, and
            // does not need to — business_offers wraps another service's item.
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('platform_service_item_types as t')
                ->whereColumn('t.platform_service_id', 'c.platform_service_id')
                ->where('t.is_active', 1))
            ->get(['ch.name_ar', 's.key', 'c.config'])
            ->filter(fn ($r) => empty(json_decode((string) $r->config, true)['allowed_item_types'] ?? []))
            ->map(fn ($r) => "{$r->name_ar}/{$r->key}");

        $this->assertEmpty(
            $loose->all(),
            'these offer every type their service has: ' . $loose->implode('، ')
        );
    }

    /** The seeder fills an empty list from what the child declares. */
    public function test_an_empty_list_is_bounded_from_the_declared_branches(): void
    {
        $delivery = (int) PlatformService::query()
            ->where('key', PlatformService::KEY_DELIVERY)->value('id');

        if ($delivery <= 0) {
            $this->markTestSkipped('Needs the delivery service.');
        }

        // Any child with a LIVE, bounded delivery config will do, and asking for
        // one beats naming one: this used to name «خدمة ليموزين», whose delivery
        // was switched off on 2026-08-10 by the owner's «حجز بدون توصيل» rule —
        // a limousine is booked by the hour and delivers nothing. The test was
        // then measuring a dormant config and failing for the right reason in
        // the wrong place.
        $row = DB::table('category_service_configs')
            ->where('platform_service_id', $delivery)
            ->where('is_active', 1)
            ->whereNotNull('child_id')
            ->whereRaw("JSON_LENGTH(JSON_EXTRACT(config, '$.allowed_item_types')) > 0")
            ->first(['category_id', 'child_id']);

        if ($row === null) {
            $this->markTestSkipped('No live bounded delivery config to test with.');
        }

        $rootId = (int) $row->category_id;
        $childId = (int) $row->child_id;

        $before = $this->typesOf($rootId, $childId, $delivery);

        app(ChildServiceWriter::class)->enable($rootId, $childId, $delivery, ['allowed_item_types' => []]);
        $this->bound();

        $after = $this->typesOf($rootId, $childId, $delivery);

        $this->assertNotEmpty($after, 'the unbounded config was left unbounded');
        $this->assertSame($before, $after, 'bounding must land on the same declared set');
    }

    /**
     * The rule that makes it safe to re-run: it may only ever fill a blank.
     * The branch seeders are dangerous precisely because they replace, which is
     * how a merchant's curation gets undone by a routine seed.
     */
    public function test_a_config_that_already_names_its_types_is_never_touched(): void
    {
        $service = (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');

        $row = DB::table('category_service_configs')
            ->where('platform_service_id', $service)
            ->where('is_active', 1)
            ->whereNotNull('child_id')
            ->first(['category_id', 'child_id']);

        if (! $row || $service <= 0) {
            $this->markTestSkipped('Needs a live booking config.');
        }

        $writer = app(ChildServiceWriter::class);
        $odd = ['booking_time'];

        $writer->enable((int) $row->category_id, (int) $row->child_id, $service, ['allowed_item_types' => $odd]);
        $this->bound();

        $this->assertSame(
            $odd,
            $this->typesOf((int) $row->category_id, (int) $row->child_id, $service),
            'the seeder overwrote a list that was already named'
        );
    }

    /**
     * Booking is bounded through the branch→KIND map, never a branch expansion.
     * The collapse put all eleven kinds in the single «أنواع الحجز» branch, so
     * expanding it would hand every child all eleven — the flattening this line
     * of work exists to stop.
     */
    public function test_booking_is_bounded_by_the_kind_map_not_the_whole_branch(): void
    {
        $service = (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');

        $pharmacy = (int) DB::table('category_children_master')
            ->where('name_ar', 'صيدلية')->value('id');

        if ($service <= 0 || $pharmacy <= 0) {
            $this->markTestSkipped('Needs the pharmacy child and the booking service.');
        }

        $rootId = (int) DB::table('category_service_configs')
            ->where('child_id', $pharmacy)->where('platform_service_id', $service)
            ->value('category_id');

        if ($rootId <= 0) {
            $this->markTestSkipped('The pharmacy has no booking config.');
        }

        app(ChildServiceWriter::class)->enable($rootId, $pharmacy, $service, ['allowed_item_types' => []]);
        $this->bound();

        $kinds = $this->typesOf($rootId, $pharmacy, $service);

        $this->assertNotEmpty($kinds);
        $this->assertLessThan(
            DB::table('platform_service_item_types')
                ->where('platform_service_id', $service)->where('is_active', 1)->count(),
            count($kinds),
            'the whole branch was expanded instead of the declared kinds'
        );
        $this->assertNotContains('booking_stay', $kinds, 'a pharmacy cannot take a hotel stay');
    }
}
