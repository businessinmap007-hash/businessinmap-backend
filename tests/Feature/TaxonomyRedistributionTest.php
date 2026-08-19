<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the line between an item type and an option.
 *
 * The rule, and the only thing this file really asserts:
 *
 *   Can the merchant put a price on it on its own?
 *     yes → item type  ("قاعة أفراح: 5000")
 *     no  → option     ("من 200 إلى 300 فرد")
 *
 * Before `TaxonomyRedistributionSeeder`, «قاعات ومناسبات» held 39 entries of
 * which 9 were bookable — the other 30 were a hall's capacity, class and a
 * meaningless «مقاس» scale. That is what made the platform feel crowded, and
 * this test exists so it cannot creep back one well-meaning row at a time.
 *
 * Asserts the END STATE, not a delta, because the seeder is idempotent and has
 * already run. Rolls back.
 */
class TaxonomyRedistributionTest extends TestCase
{
    use DatabaseTransactions;

    /** Keys shaped like a dimension rather than a thing you buy. */
    private const DIMENSION_PATTERN = '/^(size_\d+|from_\d+_to_\d+_person|monitorfrom_|\dst_class|\dnd_class|\dth_class|\drd_class)/';

    public function test_no_active_item_type_is_really_a_dimension(): void
    {
        $offenders = DB::table('platform_service_item_types')
            ->where('is_active', 1)
            ->get(['key', 'name_ar'])
            ->filter(fn ($t) => (bool) preg_match(self::DIMENSION_PATTERN, (string) $t->key))
            ->map(fn ($t) => $t->key . ' (' . $t->name_ar . ')')
            ->values();

        $this->assertEmpty(
            $offenders->all(),
            'a capacity/class/size is not something a merchant prices — it belongs in an option group: ' . $offenders->implode(', ')
        );
    }

    /**
     * The halls, at the end of the road this file has been tracking.
     *
     * «قاعات ومناسبات» started as 39 item types of which 30 were a capacity, a
     * class or a meaningless «مقاس». The redistribution moved the dimensions
     * out; ServicesReformSeeder (2026-08-02) moved the EVENT into the «أنواع
     * المناسبات» option group; and the kinds collapse left the branch holding
     * hall classes that said nothing an option did not say better, so the prune
     * removed the branch itself on 2026-08-04.
     *
     * Which is the answer this test always wanted: a hall RENTS A PERIOD OF
     * TIME, and what happens in that period is an option. Nothing about it
     * belongs in an item type — so the assertion is no longer «the branch holds
     * only bookables», it is that the branch is gone and its vocabulary landed
     * where a merchant can price against it.
     */
    public function test_the_halls_vocabulary_lives_in_options_and_the_hall_books_time(): void
    {
        $events = DB::table('option_groups')->where('name_ar', 'أنواع المناسبات')->first();

        $this->assertNotNull($events, 'the events option group must exist');
        $this->assertSame('line', $events->price_role, 'an event is what the customer books — it prices');

        $options = DB::table('options')->where('group_id', $events->id)->pluck('name_ar');

        $this->assertGreaterThanOrEqual(10, $options->count());
        $this->assertContains('أفراح', $options->all());
        $this->assertContains('مؤتمرات', $options->all());

        $config = DB::table('category_service_configs as c')
            ->join('categories as r', 'r.id', '=', 'c.category_id')
            ->join('category_children_master as ch', 'ch.id', '=', 'c.child_id')
            ->where('r.slug', 'halls')
            ->where('ch.name_ar', 'قاعة مناسبات')
            ->where('c.platform_service_id', 1)
            ->value('c.config');

        $this->assertNotNull($config, '«قاعة مناسبات» must still have a booking config');
        $this->assertContains('booking_time', json_decode((string) $config, true)['allowed_item_types'] ?? []);
    }

    public function test_amenities_are_an_option_but_capacity_and_class_are_not(): void
    {
        // Amenities are a business-level yes/no → axis 2 → an option.
        $amenities = DB::table('option_groups')->where('name_ar', 'مرافق ومعدات')->first();
        $this->assertNotNull($amenities, 'the amenities option group must exist');

        // Non-empty, not a fixed count. The point of this test is the AXIS —
        // amenities are business-level and belong in options, capacity and
        // class are per-unit and do not — and that does not change when the
        // owner adds one («شاشة عرض», 2026-08-14). CoworkingWorkspaceTest is
        // where the group's membership is bounded.
        $this->assertGreaterThan(0, DB::table('options')->where('group_id', $amenities->id)->count());

        // Capacity and class describe one bookable UNIT → axis 3 → they must NOT
        // be options. An earlier pass wrongly made them option groups; that is
        // the mistake this asserts stays fixed.
        foreach (['سعة القاعة', 'فئة القاعة'] as $wrong) {
            $this->assertNull(
                DB::table('option_groups')->where('name_ar', $wrong)->first(),
                "«{$wrong}» is a per-unit dimension — it belongs on bookable_items, not in options"
            );
        }
    }

    public function test_capacity_lives_on_the_bookable_unit(): void
    {
        // The home for capacity is bookable_items.capacity — an existing column,
        // where a filter can be exact rather than a bucket.
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn('bookable_items', 'capacity'),
            'capacity has nowhere to live if this column is gone'
        );

        // No option, anywhere, should be a capacity bucket.
        $buckets = DB::table('options')
            ->where('name_ar', 'like', '%فرد%')
            ->where('name_ar', 'like', 'من %')
            ->count();
        $this->assertSame(0, $buckets, 'a "من X إلى Y فرد" option is a capacity bucket that should be a number on the unit');
    }

    public function test_installment_survives_because_it_is_why_options_exist(): void
    {
        // The payment-mode concept is the canonical attribute: a shop HAS it,
        // nobody buys it. If a cleanup ever deletes this, the cleanup went too
        // far. Pinned to «تقسيط بدون فوائد» rather than the bare «تقسيط» —
        // that plain name was legitimately recategorized into option group 9
        // «عقارات وممتلكات» by an admin using the bulk-editor this session
        // fixed (a real-estate installment term, not the commercial-mode one).
        // Group 12 is no longer the answer: the grab-bag was split into eight
        // single-question groups, so what matters is that each of these still
        // sits in a LIVE group rather than which id that group happens to be.
        foreach ([
            'تقسيط بدون فوائد' => 'الدفع والسداد',
            'دفع مسبق' => 'الدفع والسداد',
            'جملة' => 'نطاق التعامل',
        ] as $option => $group) {
            $groupId = DB::table('option_groups')->where('name_ar', $group)->value('id');

            $this->assertNotNull($groupId, "the «{$group}» group must exist");
            $this->assertDatabaseHas('options', ['name_ar' => $option, 'group_id' => $groupId]);
        }
    }

    public function test_no_specialty_sits_in_the_attributes_group(): void
    {
        $strays = DB::table('options')
            ->where('group_id', 12)
            ->whereIn('name_ar', ['حجز طيران', 'حجز فنادق', 'شغالة', 'دادة أطفال', 'بترول', 'أخشاب', 'spear 1'])
            ->pluck('name_ar');

        $this->assertEmpty(
            $strays->all(),
            'group 12 is attributes only — a bookable service belongs in item types: ' . $strays->implode(', ')
        );
    }

    public function test_no_two_active_item_types_share_a_name_inside_one_service(): void
    {
        $dupes = DB::table('platform_service_item_types')
            ->where('is_active', 1)
            ->get(['platform_service_id', 'name_ar', 'key'])
            ->groupBy(fn ($t) => $t->platform_service_id . '|' . trim((string) $t->name_ar))
            ->filter(fn ($g) => $g->count() > 1)
            ->map(fn ($g) => $g->first()->name_ar . ' → ' . $g->pluck('key')->implode('/'))
            ->values();

        $this->assertEmpty(
            $dupes->all(),
            'the same thing named twice in one service is how the import junk got in: ' . $dupes->implode(', ')
        );
    }

    public function test_no_config_offers_a_retired_item_type(): void
    {
        // A key is only retired WITHIN its own service: `platform_service_item_types`
        // is unique on (platform_service_id, key), so the same key can name a
        // retired type in one service and a live one in another — e.g. `frozen`
        // is dead menu junk but is the live «مجمدات» type under retail. Scoping
        // by service is what keeps this from flagging healthy configs.
        $deadByService = DB::table('platform_service_item_types')
            ->where('is_active', 0)
            ->get(['platform_service_id', 'key'])
            ->groupBy('platform_service_id')
            ->map(fn ($rows) => $rows->pluck('key')->flip());

        $offenders = [];

        foreach (DB::table('category_service_configs')->get() as $row) {
            $config = json_decode((string) $row->config, true);
            $allowed = is_array($config) ? ($config['allowed_item_types'] ?? []) : [];

            if (! is_array($allowed)) {
                continue;
            }

            $dead = $deadByService->get((int) $row->platform_service_id);

            if (! $dead) {
                continue;
            }

            foreach ($allowed as $key) {
                if ($dead->has($key)) {
                    $offenders[] = "config#{$row->id}:{$key}";
                }
            }
        }

        // Nothing errors when a config names a retired key — the merchant is
        // simply still offered it. That silence is why this is asserted.
        $this->assertEmpty($offenders, 'configs still offer retired item types: ' . implode(', ', array_slice($offenders, 0, 10)));
    }

    /**
     * A collapse retires KEYS, never prices.
     *
     * ServiceKindsCollapseSeeder retired the room-kind keys on 2026-08-04 — a
     * room kind is vocabulary, and vocabulary belongs in `offering_options` —
     * but the prices are the one thing a merchant notices missing.
     *
     * This used to read business 212's six rows specifically. The owner cleared
     * that account's prices on 2026-08-20 by intent, and a guard that dies when
     * one merchant tidies up was measuring the wrong thing. What the collapse
     * must never do holds for every row there is: no price zeroed, and no row
     * left pointing at an item-type key that no longer exists.
     */
    public function test_no_priced_row_was_zeroed_or_stranded_by_a_collapse(): void
    {
        $rows = DB::table('business_service_prices')
            ->where('is_active', 1)
            ->get(['id', 'business_id', 'bookable_item_type', 'price', 'charge_mode']);

        if ($rows->isEmpty()) {
            $this->markTestSkipped('No active priced rows to check.');
        }

        $keys = DB::table('platform_service_item_types')->pluck('key')->flip();

        foreach ($rows as $row) {
            $type = trim((string) ($row->bookable_item_type ?? ''));

            // صفٌّ لا يسمّى نوعًا أصلًا ليس تائهًا: السُّلَّمُ له درجةٌ تقرؤه،
            // وهو سعرُ التاجر العامّ. التائهُ من يسمّى مفتاحًا لم يعد موجودًا.
            if ($type !== '') {
                $this->assertTrue(
                    isset($keys[$type]),
                    "row #{$row->id} points at a retired item-type key «{$type}»"
                );
            }

            // «مجّانًا» و«رسم حجز» و«حدّ أدنى» تتجاهل `price` عمدًا، فصفرُها
            // قرارٌ لا فقدان. الصفرُ لا يكون خسارةً إلا فى الوضع العادىّ.
            if ((string) ($row->charge_mode ?? 'standard') === 'standard') {
                $this->assertGreaterThan(
                    0,
                    (float) $row->price,
                    "row #{$row->id} (business {$row->business_id}) was zeroed"
                );
            }
        }
    }

    /**
     * An option group with nothing in it is a dead entry in every picker and
     * every admin screen, and it reads as a category the merchant simply has
     * not filled in yet.
     *
     * Two were dropped on 2026-08-04 — «مركبات ونقل», split into car marques /
     * motorcycle marques / transport vehicles, and «أنماط خدمة وتجارية», the
     * grab-bag broken into eight per-child groups. Both handed everything over
     * and kept nothing. Deleting them was safe precisely because they were
     * empty: `options.group_id` is ON DELETE SET NULL, so a group holding even
     * one option would have orphaned it instead.
     */
    public function test_no_option_group_is_empty(): void
    {
        // Scoped to ACTIVE groups since 2026-08-10. A third way to arrive at an
        // empty group appeared that day: a group split into several and left
        // standing as the record of where they came from. «أقسام السوبر ماركت»
        // handed all 27 of its aisles to five counters and kept none.
        //
        // Deleting it would lose that history and nothing in this taxonomy is
        // deleted, so GroceryAisleSplitSeeder switches it off instead. What the
        // rule is really about is a merchant opening a live heading and finding
        // it blank, and an inactive group is not offered to anybody — it is a
        // tombstone, and active-and-empty is still a bug.
        $empty = DB::table('option_groups as g')
            ->where('g.is_active', 1)
            ->whereNotExists(fn ($q) => $q->from('options')->whereColumn('options.group_id', 'g.id'))
            ->pluck('name_ar');

        $this->assertEmpty($empty, 'empty ACTIVE option groups: ' . $empty->implode('، '));
    }

    /**
     * An option outside every group is unreachable, not merely hidden: `options`
     * has no is_active column, so the group is the only retirement boundary the
     * schema offers, and a groupless row can never be shown, edited or restored
     * through any screen. It also keeps a name_en, which is UNIQUE
     * platform-wide, so a dead row silently costs a live one its English name.
     *
     * Eleven were swept on 2026-08-04 by OrphanOptionsCleanupSeeder. They get
     * this way by accident — deleting a non-empty group from the admin panel
     * sets its options' group_id NULL — which is why this asserts rather than
     * trusting the sweep to have been the last word.
     */
    public function test_no_option_is_groupless(): void
    {
        $orphans = DB::table('options as o')
            ->leftJoin('option_groups as g', 'g.id', '=', 'o.group_id')
            ->whereNull('g.id')
            ->pluck('o.name_ar');

        $this->assertEmpty(
            $orphans->all(),
            'options belonging to no group: ' . $orphans->implode('، ')
        );
    }

    public function test_every_priced_offering_still_points_at_a_real_item_type(): void
    {
        $known = DB::table('platform_service_item_types')->pluck('key')->flip();

        $dangling = DB::table('business_service_prices')
            ->whereNotNull('bookable_item_type')
            ->where('bookable_item_type', '!=', '')
            ->get(['id', 'business_id', 'bookable_item_type'])
            ->filter(fn ($p) => ! $known->has($p->bookable_item_type))
            ->map(fn ($p) => "price#{$p->id}(biz {$p->business_id}):{$p->bookable_item_type}")
            ->values();

        $this->assertEmpty(
            $dangling->all(),
            'a merge orphaned a real merchant offering: ' . $dangling->implode(', ')
        );
    }
}
