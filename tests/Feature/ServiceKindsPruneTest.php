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

    /**
     * Every active item type must still sit in a branch, and every branch a
     * seeder resolves by key must still exist.
     *
     * On 2026-08-05 seventeen branch rows were deleted from the database by
     * hand, five of them live delivery branches. The cascade took their
     * membership rows with them, all 21 delivery types collapsed onto the one
     * surviving branch, and 315 delivery configs were left naming ids that no
     * longer existed. Nothing failed loudly — the pickers simply went wrong.
     *
     * `test_every_config_points_at_a_branch_that_exists` catches the dangling
     * half of that; this catches the other two halves.
     */
    public function test_every_active_type_still_sits_in_a_branch(): void
    {
        $loose = DB::table('platform_service_item_types as t')
            ->join('platform_services as s', 's.id', '=', 't.platform_service_id')
            ->where('t.is_active', 1)
            ->where('s.is_active', 1)
            // The price resolver's fallback is a sentinel and has no branch.
            ->where('t.key', '!=', BusinessServicePrice::DEFAULT_ITEM_TYPE)
            ->whereNotExists(fn ($q) => $q->from('platform_service_item_group_type as gt')
                ->whereColumn('gt.item_type_id', 't.id'))
            ->pluck('t.key');

        $this->assertEmpty($loose->all(), 'item types that no branch can reach: ' . $loose->implode('، '));
    }

    /**
     * The eight pre-collapse seeders resolve a branch by key and file types
     * into it; LegacyOptionGapsSeeder died on a foreign key the day one went
     * missing, which is why prune() switches a branch off instead of deleting
     * it. The row has to be there whether or not it is active.
     */
    public function test_the_branch_keys_the_seeders_rely_on_all_exist(): void
    {
        $keys = DB::table('platform_service_item_groups')->pluck('key')->flip();

        foreach ([
            'delivery', 'delivery_freight', 'delivery_international',
            'delivery_coldchain', 'delivery_courier_ondemand', 'delivery_documents',
            'clinic', 'hotel', 'restaurant_table', 'sports', 'training',
            'services_tasks', 'halls_events', 'tourism_travel', 'real_estate',
            'beauty_care', 'business_consulting', 'coworking',
            'booking_kinds', 'menu_kinds',
        ] as $key) {
            $this->assertTrue($keys->has($key), "the «{$key}» branch row is gone — a seeder resolves it by key");
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

    /**
     * The branch seeders name the branches the collapse retired.
     *
     * All twelve booking branches — clinic, hotel, restaurant_table, sports,
     * halls_events … — and the food branches under menu were switched off and
     * emptied when their types moved onto the kinds. The inherited expansion in
     * DeliveryChildBranchesSeeder reads a branch's types, so run standalone
     * these two wrote an EMPTY allowed_item_types onto 64 booking configs and
     * all 19 menu ones — and empty does not read as «nothing», it reads as
     * «everything». A clinic that could take a hotel stay.
     *
     * A full seed hid both: the collapse runs a few lines later and re-derives
     * the kind from the branch. Nothing hid it from anyone running one seeder.
     *
     * @dataProvider branchSeeders
     */
    public function test_a_branch_seeder_does_not_blank_its_configs(string $class, string $serviceKey): void
    {
        $serviceId = (int) DB::table('platform_services')->where('key', $serviceKey)->value('id');

        $counts = fn () => DB::table('category_service_configs')
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->get(['id', 'config'])
            ->mapWithKeys(fn ($r) => [(int) $r->id => count(json_decode((string) $r->config, true)['allowed_item_types'] ?? [])])
            ->all();

        $before = $counts();
        $this->assertNotEmpty($before);

        DB::beginTransaction();

        try {
            (new $class)->run();

            foreach ($counts() as $id => $after) {
                $this->assertGreaterThan(0, $after, "{$class} emptied config #{$id} — which means EVERY kind");
                $this->assertSame($before[$id] ?? $after, $after, "{$class} changed config #{$id} on a re-run");
            }
        } finally {
            DB::rollBack();
        }
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function branchSeeders(): array
    {
        return [
            'booking' => [\Database\Seeders\BookingChildBranchesSeeder::class, 'booking'],
            'menu' => [\Database\Seeders\MenuChildBranchesSeeder::class, 'menu'],
            'retail' => [\Database\Seeders\RetailChildBranchesSeeder::class, 'retail'],
            'delivery' => [\Database\Seeders\DeliveryChildBranchesSeeder::class, 'delivery'],
        ];
    }

    /**
     * The clinic's four kinds must survive the branch seeder as well as the
     * collapse. «clinic» maps to «حجز موعد», so translating the branch alone
     * would flatten كشف، متابعة، استشارة أونلاين and زيارة منزلية back onto one.
     */
    public function test_the_branch_seeder_does_not_flatten_the_clinic(): void
    {
        $clinic = 514;

        DB::beginTransaction();

        try {
            (new \Database\Seeders\BookingChildBranchesSeeder)->run();

            $kinds = DB::table('category_service_configs')
                ->where('child_id', $clinic)
                ->where('platform_service_id', DB::table('platform_services')->where('key', 'booking')->value('id'))
                ->where('is_active', 1)
                ->pluck('config')
                ->flatMap(fn ($c) => json_decode((string) $c, true)['allowed_item_types'] ?? [])
                ->unique();

            foreach (['booking_examination', 'booking_follow_up', 'booking_online_consultation', 'booking_home_visit'] as $kind) {
                $this->assertContains($kind, $kinds->all(), "«عيادة» lost «{$kind}» to its branch");
            }

            $this->assertNotContains('booking_appointment', $kinds->all(), '«عيادة» was flattened back onto حجز موعد');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * A branch named in the child→branch file but absent from the collapse's
     * map is silently answered by the DEFAULT, which is «حجز موعد» — and a
     * default is an absence of knowledge, not knowledge.
     *
     * `entertainment_leisure` was missing, so all ten children of «فنون و
     * ترفية» were handed appointments. You do not make an appointment at a
     * billiards hall; you take a table for an hour, which the platform already
     * knew — «الرياضة» is the same shape and all six of its children are on
     * «حجز وقت».
     */
    public function test_every_branch_the_map_names_has_a_kind(): void
    {
        $spec = (require database_path('seeders/data/service_kinds.php'))['booking'];

        $used = collect(require database_path('seeders/data/booking_child_branches.php'))
            ->flatMap(fn ($children) => collect($children)->flatten())
            ->unique();

        $this->assertNotEmpty($used);

        foreach ($used as $branch) {
            $this->assertArrayHasKey(
                $branch,
                $spec['map'],
                "«{$branch}» is named by children but has no kind — they all fall to «{$spec['default']}»"
            );
        }
    }

    /**
     * A booked hour and a booked appointment are different products, and the
     * roots that sell an hour must agree with each other.
     */
    public function test_a_place_you_occupy_is_booked_by_time(): void
    {
        $byTime = DB::table('category_service_configs as c')
            ->join('categories as r', 'r.id', '=', 'c.category_id')
            ->join('category_children_master as m', 'm.id', '=', 'c.child_id')
            ->whereIn('r.slug', ['arts-entertainment', 'sports', 'halls'])
            ->where('c.platform_service_id', DB::table('platform_services')->where('key', 'booking')->value('id'))
            ->where('c.is_active', 1)
            ->get(['m.name_ar', 'c.config']);

        $this->assertNotEmpty($byTime);

        // The two that sell a person or a departure, not a duration.
        $notPlaces = ['فوتوجرافر', 'رحلات ومراكب'];

        foreach ($byTime as $row) {
            if (in_array($row->name_ar, $notPlaces, true)) {
                continue;
            }

            $this->assertContains(
                'booking_time',
                json_decode((string) $row->config, true)['allowed_item_types'] ?? [],
                "«{$row->name_ar}» is a place you occupy for a while — it cannot be booked by appointment alone"
            );
        }
    }

    /**
     * A map that still names a folded child hands it a branch the moment it is
     * linked to a root again — and reports it missing on every run until then.
     * Forty-one health specialties, twelve sports, eleven property types, six
     * hotel star ratings and six folded clothing children were doing exactly
     * that across the four maps, which is what let ten real misses hide in
     * booking: two thirds of that file was noise.
     *
     * @dataProvider branchMaps
     */
    public function test_the_branch_map_names_no_child_that_is_gone(string $file): void
    {
        $missing = [];

        foreach (require database_path("seeders/data/{$file}") as $rootSlug => $children) {
            if (! is_array($children)) {
                continue;
            }

            $rootId = (int) DB::table('categories')->where('parent_id', 0)->where('slug', $rootSlug)->value('id');

            $this->assertNotSame(0, $rootId, "no root «{$rootSlug}»");

            foreach (array_keys($children) as $name) {
                $exists = DB::table('category_parent_child as pc')
                    ->join('category_children_master as m', 'm.id', '=', 'pc.child_id')
                    ->where('pc.parent_id', $rootId)
                    ->where('m.name_ar', $name)
                    ->exists();

                if (! $exists) {
                    $missing[] = "{$rootSlug} → {$name}";
                }
            }
        }

        $this->assertSame([], $missing, "{$file} names children that no longer stand there: " . implode('، ', $missing));
    }

    /** @return array<string,array{0:string}> */
    public static function branchMaps(): array
    {
        return [
            'booking' => ['booking_child_branches.php'],
            'menu' => ['menu_child_branches.php'],
            'retail' => ['retail_child_branches.php'],
            'delivery' => ['delivery_child_branches.php'],
        ];
    }

    /**
     * «توصيل مطعم»، «توصيل سوبر ماركت»، «توصيل صيدلية» and «نقل عينات طبية»
     * are named after the trade that uses them, and they went out with their
     * branch to everybody on it: a gold shop and a bookshop were each offered
     * pharmacy and restaurant delivery, and twenty-seven food trades — a
     * vegetable seller, a poultry farm, a plant nursery — were offered medical
     * sample transport.
     *
     * @see \Database\Seeders\data\delivery_child_types
     */
    public function test_a_trade_named_delivery_mechanism_stays_with_its_trade(): void
    {
        $onlyFor = (require database_path('seeders/data/delivery_child_types.php'))['__only_for'] ?? [];

        $this->assertNotEmpty($onlyFor);

        foreach (
            DB::table('category_service_configs as c')
                ->join('category_children_master as m', 'm.id', '=', 'c.child_id')
                ->where('c.platform_service_id', DB::table('platform_services')->where('key', 'delivery')->value('id'))
                ->where('c.is_active', 1)
                ->get(['m.name_ar', 'c.config']) as $row
        ) {
            $held = json_decode((string) $row->config, true)['allowed_item_types'] ?? [];

            $this->assertNotEmpty($held, "«{$row->name_ar}» has no delivery mechanism, which reads as ALL of them");

            foreach ($held as $key) {
                if (! isset($onlyFor[$key])) {
                    continue;
                }

                $this->assertContains(
                    $row->name_ar,
                    $onlyFor[$key],
                    "«{$row->name_ar}» is offered «{$key}», which belongs to another trade"
                );
            }
        }
    }

    /**
     * A merchant's (root, child) must be a pairing that exists.
     *
     * `users.category_id` and `users.category_child_id` are read together, so a
     * child that moves roots leaves anyone still filed under the old one
     * matching no config and no link at all — the platform offers them nothing.
     * ChildRootMovesSeeder carries the accounts across on the moves IT makes
     * («Nobody may be left pointing at a root the child no longer hangs from»);
     * nothing applied that to the remodels and detachments, which move a child
     * by a different door.
     *
     * Fourteen merchants were stranded: twelve on «سيارات» under شركات — every
     * merchant that child had — and two on «قاعات تدريب» under دورات و تدريب,
     * which went to قاعات in the 2026-08-02 halls remodel without them.
     */
    public function test_no_merchant_is_filed_under_a_root_their_trade_left(): void
    {
        $pairs = DB::table('category_parent_child')
            ->get(['parent_id', 'child_id'])
            ->mapWithKeys(fn ($r) => ["{$r->parent_id}:{$r->child_id}" => true]);

        $stranded = [];

        foreach (
            DB::table('users as u')
                ->join('categories as r', 'r.id', '=', 'u.category_id')
                ->join('category_children_master as m', 'm.id', '=', 'u.category_child_id')
                ->whereNotNull('u.category_child_id')
                ->groupBy('u.category_id', 'u.category_child_id', 'r.slug', 'm.name_ar')
                ->select('u.category_id as rid', 'u.category_child_id as cid', 'r.slug', 'm.name_ar', DB::raw('COUNT(*) as n'))
                ->get() as $row
        ) {
            if ($pairs->has("{$row->rid}:{$row->cid}")) {
                continue;
            }

            $stranded[] = "{$row->slug} → {$row->name_ar} ({$row->n})";
        }

        $this->assertSame([], $stranded, 'merchants filed under a pairing that does not exist: ' . implode('، ', $stranded));
    }

    /**
     * Both halves or neither. A live link with no live config offers a service
     * that nothing bounds, and an unbounded allowed_item_types is not «nothing»
     * but EVERY type; a live config with no link is work nobody can reach.
     *
     * @see \Database\Seeders\ChildServiceScopeSeeder
     */
    public function test_a_service_is_never_wired_by_one_half(): void
    {
        $rows = DB::table('category_platform_services as l')
            ->join('platform_services as s', 's.id', '=', 'l.platform_service_id')
            ->join('categories as r', 'r.id', '=', 'l.category_id')
            ->join('category_children_master as m', 'm.id', '=', 'l.child_id')
            ->leftJoin('category_service_configs as c', function ($j) {
                $j->on('c.category_id', '=', 'l.category_id')
                    ->on('c.child_id', '=', 'l.child_id')
                    ->on('c.platform_service_id', '=', 'l.platform_service_id')
                    ->where('c.is_active', '=', 1);
            })
            ->where('l.is_active', 1)
            ->where('s.is_active', 1)
            ->whereNull('c.id')
            ->get(['s.key', 'r.slug', 'm.name_ar']);

        $this->assertSame(
            [],
            $rows->map(fn ($r) => "{$r->key}: {$r->slug} → {$r->name_ar}")->all(),
            'offered with nothing bounding it — which reads as EVERY item type'
        );
    }

    /**
     * The collapse is in DatabaseSeeder, so anything it would change on a
     * re-run is a change a full seed makes without anyone asking for it.
     *
     * It had two, both of the same shape — a weak source overwriting a real
     * answer:
     *
     *  - «حلويات» × شركات, «منيو» for «ماركت». kindsFromDataFile() read a
     *    per-ROOT file and returned a per-CHILD array, so the sweets shop's
     *    answer under المحلات was applied to the sweets wholesaler under شركات,
     *    which the file never names. Its own docblock promised the opposite.
     *  - «طباعة» × شركات, losing استشارة and استشارة أونلاين. The root fallback
     *    — «every child nobody named answers حجز موعد» — is a guess, and it was
     *    overwriting kinds already stored.
     *
     * A no-op is the whole assertion. If this ever fails, read the diff before
     * changing the expectation: it is telling you a full seed would rewrite
     * somebody's live configuration.
     */
    public function test_the_collapse_changes_nothing_on_a_re_run(): void
    {
        $snapshot = fn () => DB::table('category_service_configs')
            ->where('is_active', 1)
            ->get(['id', 'config'])
            ->mapWithKeys(fn ($r) => [(int) $r->id => implode(',', json_decode((string) $r->config, true)['allowed_item_types'] ?? [])])
            ->all();

        $before = $snapshot();

        DB::beginTransaction();

        try {
            (new \Database\Seeders\ServiceKindsCollapseSeeder)->run();

            $changed = [];

            foreach ($snapshot() as $id => $after) {
                if (($before[$id] ?? null) !== $after) {
                    $changed[] = "#{$id}: «" . ($before[$id] ?? '?') . "» → «{$after}»";
                }
            }

            $this->assertSame([], $changed, "a full seed would rewrite:\n  " . implode("\n  ", $changed));
        } finally {
            DB::rollBack();
        }
    }
}
