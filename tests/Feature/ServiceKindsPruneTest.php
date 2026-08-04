<?php

namespace Tests\Feature;

use App\Models\BusinessServicePrice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Guards what `ServiceKindsCollapseSeeder::prune()` swept away, and — more
 * importantly — what it must never sweep.
 *
 * The collapse retired 365 item types and left 20 branches with nothing live in
 * them. Deactivating was the safe first move; deleting is what stops an admin
 * branch board listing twenty categories a merchant has simply not filled in.
 * But a type is referenced by a free-text string with no foreign key anywhere,
 * so nothing in the schema would object to deleting one that is still in use.
 * These tests are that objection.
 *
 * Asserts the END STATE, not a delta. Rolls back.
 */
class ServiceKindsPruneTest extends TestCase
{
    use DatabaseTransactions;

    /** Columns holding a type as a plain string — mirrors the seeder's list. */
    private const TYPE_KEY_COLUMNS = [
        ['business_service_prices', 'bookable_item_type'],
        ['bookable_items', 'item_type'],
        ['menu_items', 'item_type'],
    ];

    /**
     * Scoped to ACTIVE branches on purpose. The 20 the collapse emptied are
     * kept as rows and switched off rather than deleted — eight pre-collapse
     * seeders still resolve a branch by key and file types into it, and one of
     * them died on a foreign key when the row went missing. Off is what the
     * merchant sees; the row is what the seeders need.
     */
    public function test_no_active_item_branch_is_empty(): void
    {
        $empty = DB::table('platform_service_item_groups as g')
            ->where('g.is_active', 1)
            ->whereNotExists(fn ($q) => $q->from('platform_service_item_group_type as gt')
                ->join('platform_service_item_types as t', 't.id', '=', 'gt.item_type_id')
                ->whereColumn('gt.group_id', 'g.id')
                ->where('t.is_active', 1))
            ->pluck('name_ar');

        $this->assertEmpty(
            $empty->all(),
            'a branch with no live type reads as a category nobody filled in: ' . $empty->implode('، ')
        );
    }

    /**
     * The one row that outlived the prune, and the reason the prune needed a
     * hand-written exception at all.
     *
     * `BusinessServicePrice::DEFAULT_ITEM_TYPE` is the string a price falls back
     * to when it names no type. It is named in PHP, not by a foreign key, so
     * deleting the row is a change no migration and no constraint would catch —
     * it would surface as a price with an unresolvable type, much later.
     */
    public function test_the_price_resolver_fallback_type_survived(): void
    {
        $this->assertDatabaseHas('platform_service_item_types', [
            'key' => BusinessServicePrice::DEFAULT_ITEM_TYPE,
        ]);
    }

    public function test_nothing_that_was_deleted_was_still_referenced(): void
    {
        $known = DB::table('platform_service_item_types')->pluck('key')->flip();
        $dangling = [];

        foreach (self::TYPE_KEY_COLUMNS as [$table, $column]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (
                DB::table($table)->whereNotNull($column)->where($column, '!=', '')
                    ->pluck($column, 'id') as $id => $key
            ) {
                if (! $known->has((string) $key)) {
                    $dangling[] = "{$table}#{$id}:{$key}";
                }
            }
        }

        $this->assertEmpty($dangling, 'the prune deleted a type something still points at: ' . implode('، ', $dangling));
    }

    /**
     * `platform_service_item_types` is unique on (platform_service_id, key), so
     * the same key names a dead row in one service and a live one in another —
     * `frozen` is dead menu junk and the live «مجمدات» under retail at once.
     * Matching keys without their service let four retail keys shield four dead
     * menu rows from the prune; this pins the scoping that fixed it.
     */
    public function test_a_retired_type_is_offered_by_no_config_of_its_own_service(): void
    {
        $deadByService = DB::table('platform_service_item_types')
            ->where('is_active', 0)
            ->get(['platform_service_id', 'key'])
            ->groupBy('platform_service_id')
            ->map(fn ($rows) => $rows->pluck('key')->flip());

        $offenders = [];

        foreach (DB::table('category_service_configs')->get(['id', 'platform_service_id', 'config']) as $row) {
            $config = json_decode((string) $row->config, true);
            $allowed = is_array($config) ? ($config['allowed_item_types'] ?? []) : [];
            $dead = $deadByService->get((int) $row->platform_service_id);

            if (! is_array($allowed) || ! $dead) {
                continue;
            }

            foreach ($allowed as $key) {
                if ($dead->has((string) $key)) {
                    $offenders[] = "config#{$row->id}:{$key}";
                }
            }
        }

        $this->assertEmpty($offenders, 'a config offers a retired type: ' . implode('، ', $offenders));
    }

    public function test_every_config_points_at_a_branch_that_exists(): void
    {
        $branches = DB::table('platform_service_item_groups')->pluck('id')->flip();
        $dangling = [];

        foreach (DB::table('category_service_configs')->get(['id', 'config']) as $row) {
            $config = json_decode((string) $row->config, true);
            $groups = is_array($config) ? ($config['item_groups'] ?? []) : [];

            if (! is_array($groups)) {
                continue;
            }

            foreach ($groups as $id) {
                if (! $branches->has((int) $id)) {
                    $dangling[] = "config#{$row->id}:{$id}";
                }
            }
        }

        $this->assertEmpty($dangling, 'a config names a branch the prune removed: ' . implode('، ', $dangling));
    }

    /**
     * Retail is the reason the prune is scoped to booking and menu at all: its
     * `allowed_item_types` is not a vocabulary but the 1:1 mirror onto
     * `product_category_children.slug` that scopes the shared catalog.
     */
    public function test_the_retail_mirror_is_untouched(): void
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'retail')->value('id');

        $slugs = DB::table('product_category_children')->pluck('slug')->flip();

        $unmatched = DB::table('platform_service_item_types')
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->pluck('key')
            ->reject(fn ($k) => $slugs->has((string) $k));

        $this->assertEmpty($unmatched->all(), 'a retail type lost its catalog scope: ' . $unmatched->implode('، '));
    }

    /**
     * The collapse must survive being run twice.
     *
     * It derived each config's kind from that config's `item_groups`, then
     * overwrote `item_groups` with the single new branch — so the second run
     * read its own output, found nothing it recognised, and fell through to
     * `default`. All 307 booking configs became «حجز موعد» and all 123 menu
     * configs «منيو»: four kinds and five selling surfaces flattened to one
     * each, in a seeder whose docblock promised idempotence.
     *
     * Nothing failed while it happened. This asserts the spread that proves it
     * did not — a seeder that erases the distinction it exists to draw leaves a
     * database that still looks perfectly consistent.
     */
    public function test_the_booking_kinds_did_not_flatten_onto_one(): void
    {
        $spread = [];

        foreach (
            DB::table('category_service_configs')
                ->where('platform_service_id', 1)
                ->pluck('config') as $json
        ) {
            foreach (json_decode((string) $json, true)['allowed_item_types'] ?? [] as $kind) {
                $spread[(string) $kind] = ($spread[(string) $kind] ?? 0) + 1;
            }
        }

        foreach (['booking_appointment', 'booking_time', 'booking_stay', 'booking_table'] as $kind) {
            $this->assertArrayHasKey(
                $kind,
                $spread,
                "no child books by «{$kind}» — the kinds collapsed onto one: " . json_encode($spread)
            );
        }

        // A hotel is the clearest case: it lets a room by the night, and there
        // is no reading of «حجز موعد» that covers it.
        $hotel = DB::table('category_service_configs as c')
            ->join('categories as r', 'r.id', '=', 'c.category_id')
            ->join('category_children_master as ch', 'ch.id', '=', 'c.child_id')
            ->where('r.slug', 'tourist-hotels')
            ->where('ch.name_ar', 'فندق')
            ->where('c.platform_service_id', 1)
            ->value('c.config');

        $this->assertNotNull($hotel, '«فندق» must still have a booking config');
        $this->assertContains('booking_stay', json_decode((string) $hotel, true)['allowed_item_types'] ?? []);
    }

    /**
     * The four specialised appointment kinds (owner call 2026-08-04).
     *
     * They are the sanctioned exception to «the type says HOW»: all four are
     * appointments, and they name which kind of appointment — a dentist takes a
     * كشف, an engineer an استشارة, and the price differs by that alone. They do
     * not multiply per trade the way the old 294 did, because one kind serves
     * many specialties.
     *
     * The risk they carry is the prune itself: anything not named in
     * `service_kinds.php` is deactivated and then deleted, so a kind added
     * through the admin panel or a one-off seeder would vanish on the next run
     * with nothing to say it had. This asserts they are in the map's care.
     */
    public function test_the_specialised_booking_kinds_survive_the_prune(): void
    {
        $branchId = (int) DB::table('platform_service_item_groups')
            ->where('platform_service_id', 1)
            ->where('key', 'booking_kinds')
            ->value('id');

        $this->assertNotSame(0, $branchId, 'the booking kinds branch must exist');

        $mapped = (require database_path('seeders/data/service_kinds.php'))['booking']['kinds'];

        foreach ([
            'booking_consultation',
            'booking_examination',
            'booking_follow_up',
            'booking_procedure',
            'booking_online_consultation',
            'booking_home_sample',
            'booking_home_visit',
        ] as $key) {
            $this->assertArrayHasKey($key, $mapped, "«{$key}» must be in service_kinds.php or the prune deletes it");

            $type = DB::table('platform_service_item_types')
                ->where('platform_service_id', 1)
                ->where('key', $key)
                ->first(['id', 'is_active']);

            $this->assertNotNull($type, "«{$key}» must exist");
            $this->assertSame(1, (int) $type->is_active, "«{$key}» must be offerable");

            $this->assertDatabaseHas('platform_service_item_group_type', [
                'group_id' => $branchId,
                'item_type_id' => (int) $type->id,
            ]);
        }
    }

    /**
     * The owner-approved assignment of the specialised kinds, exactly.
     *
     * Asserted as an EQUALITY rather than a contains: the whole point of the
     * assignment is that «حجز موعد» goes away for these children, and a bare
     * appointment sitting beside كشف and متابعة says nothing the other two do
     * not. That is also the failure this would actually see — the branch and
     * root sources answer «appointment» for all thirteen, so a resolution
     * chain that merged instead of replacing would quietly put it back.
     *
     * Covers every root a child sits under: «دعاية وإعلان» has a config under
     * both companies and offices, and the map is keyed by child so both get it.
     */
    public function test_each_child_carries_exactly_the_kinds_it_was_given(): void
    {
        $assigned = (require database_path('seeders/data/service_kinds.php'))['booking']['children'];

        $this->assertNotEmpty($assigned, 'the per-child assignment must not be empty');

        foreach ($assigned as $childId => $expected) {
            $configs = DB::table('category_service_configs')
                ->where('child_id', (int) $childId)
                ->where('platform_service_id', 1)
                ->pluck('config');

            $name = DB::table('category_children_master')->where('id', (int) $childId)->value('name_ar');

            $this->assertNotEmpty($configs, "«{$name}» must still have a booking config");

            foreach ($configs as $json) {
                $kinds = json_decode((string) $json, true)['allowed_item_types'] ?? [];

                $this->assertSame($expected, $kinds, "«{$name}» does not carry exactly what it was given");

                // Only where the assignment actually dropped it. «معمل تحاليل»
                // keeps the plain appointment on purpose — coming in to give a
                // sample is still the ordinary case, and the home visit sits
                // beside it rather than replacing it.
                if (! in_array('booking_appointment', $expected, true)) {
                    $this->assertNotContains(
                        'booking_appointment',
                        $kinds,
                        "«{$name}» kept the bare appointment the specialised kinds replaced"
                    );
                }
            }
        }
    }

    public function test_no_group_type_row_points_at_a_type_that_is_gone(): void
    {
        $orphans = DB::table('platform_service_item_group_type as gt')
            ->leftJoin('platform_service_item_types as t', 't.id', '=', 'gt.item_type_id')
            ->whereNull('t.id')
            ->count();

        $this->assertSame(0, $orphans, 'the pivot outlived its item type');
    }
}
