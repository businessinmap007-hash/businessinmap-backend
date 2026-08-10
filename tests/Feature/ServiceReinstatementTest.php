<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every row in data/service_reinstatements.php is a named finding: a child that
 * could not sell, beside siblings that could, with the donor whose config it
 * takes. Never a heuristic — an admin who switched something off on purpose is
 * entitled to have that survive.
 *
 * The class of defect this guards is the worst kind the taxonomy produces,
 * because nothing surfaces it: the merchant registers, appears in search, can be
 * delivered from, and has no way to say what he sells.
 */
class ServiceReinstatementTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<int,array<string,mixed>> */
    private function entries(): array
    {
        return require database_path('seeders/data/service_reinstatements.php');
    }

    private function rootId(string $slug): int
    {
        return (int) DB::table('categories')->where('slug', $slug)->value('id');
    }

    private function childId(string $nameAr, int $rootId): int
    {
        return (int) DB::table('category_parent_child as p')
            ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
            ->where('p.parent_id', $rootId)->where('c.name_ar', $nameAr)
            ->value('c.id');
    }

    /** Every declared reinstatement is live, wired on both rows, and bounded. */
    public function test_every_declared_service_is_actually_reachable(): void
    {
        foreach ($this->entries() as $entry) {
            $rootId = $this->rootId($entry['root_slug']);
            $childId = $this->childId($entry['child_name_ar'], $rootId);
            $serviceId = (int) DB::table('platform_services')->where('key', $entry['service_key'])->value('id');

            $label = "«{$entry['child_name_ar']}» × {$entry['root_slug']} · {$entry['service_key']}";

            $this->assertGreaterThan(0, $childId, "{$label}: the child does not stand under that root");

            $this->assertTrue(
                DB::table('category_platform_services')->where('category_id', $rootId)
                    ->where('child_id', $childId)->where('platform_service_id', $serviceId)
                    ->where('is_active', 1)->exists(),
                "{$label}: the link is off"
            );

            // A service reaches a merchant through TWO rows that must agree.
            $this->assertTrue(
                DB::table('category_service_configs')->where('category_id', $rootId)
                    ->where('child_id', $childId)->where('platform_service_id', $serviceId)
                    ->where('is_active', 1)->exists(),
                "{$label}: the config is off, so the link leads nowhere"
            );
        }
    }

    /**
     * The donor is what makes this safe: the child arrives offering a shape
     * somebody under that root already chose, not one this file invented.
     */
    public function test_each_child_took_its_donor_shape(): void
    {
        foreach ($this->entries() as $entry) {
            $rootId = $this->rootId($entry['root_slug']);
            $serviceId = (int) DB::table('platform_services')->where('key', $entry['service_key'])->value('id');

            $config = fn (int $childId) => json_decode((string) DB::table('category_service_configs')
                ->where('category_id', $rootId)->where('child_id', $childId)
                ->where('platform_service_id', $serviceId)->where('is_active', 1)->value('config'), true) ?: [];

            $sorted = function (array $c) {
                $types = $c['allowed_item_types'] ?? [];
                sort($types);

                return $types;
            };

            $mine = $sorted($config($this->childId($entry['child_name_ar'], $rootId)));
            $donor = $sorted($config($this->childId($entry['copy_from_child_ar'], $rootId)));

            $this->assertNotSame(
                [],
                $mine,
                "«{$entry['child_name_ar']}» got «{$entry['service_key']}» with an unbounded picker"
            );

            $this->assertSame(
                $donor,
                $mine,
                "«{$entry['child_name_ar']}» does not match its donor «{$entry['copy_from_child_ar']}»"
            );
        }
    }

    /** The specific finding worth naming: a shipping office can publish a trip. */
    public function test_the_shipping_office_can_publish_a_trip(): void
    {
        $rootId = $this->rootId('shipping-delivery');
        $schedules = (int) DB::table('platform_services')->where('key', 'schedules')->value('id');

        foreach (['شركة', 'مكتب', 'مندوب'] as $name) {
            $this->assertTrue(
                DB::table('category_platform_services')->where('category_id', $rootId)
                    ->where('child_id', $this->childId($name, $rootId))
                    ->where('platform_service_id', $schedules)->where('is_active', 1)->exists(),
                "«{$name}» cannot publish a trip leg"
            );
        }
    }

    /** Re-running writes nothing. */
    public function test_the_seeder_is_idempotent(): void
    {
        $count = fn () => [
            DB::table('category_platform_services')->where('is_active', 1)->count(),
            DB::table('category_service_configs')->where('is_active', 1)->count(),
        ];

        $before = $count();

        $this->artisan('db:seed', ['--class' => 'ServiceReinstatementSeeder', '--no-interaction' => true])->run();

        $this->assertSame($before, $count());
    }
}
