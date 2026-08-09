<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «استمر في تنظيف باقي التصنيفات» — owner, 2026-08-09.
 *
 * The sweep found root #443 «كوافير»: a TRADE that had made itself a top-level
 * domain, sitting in the customer's first row beside «شركات» (194 accounts) and
 * «مصانع» (104) holding three. It also split one trade over three children —
 * «كوافير رجالي» and «كوافير حريمى» under #443, and the real «كوافير» #136
 * under مهن وحرفيين, which held none of the accounts and, worse, none of the
 * salon's own priced services.
 *
 * See SalonRemodelSeeder. The tests below guard the fix AND the invariant the
 * sweep was built on, so the next stray root is caught by a red test rather than
 * by another audit a month later.
 */
class SalonRemodelTest extends TestCase
{
    use DatabaseTransactions;

    private const KEEPER = 136;      // «كوافير» under مهن وحرفيين
    private const RETIRED_ROOT = 443;
    private const PROFESSIONS = 6;

    /** The trade root is gone from the customer's top row. */
    public function test_the_salon_root_is_no_longer_offered(): void
    {
        $root = DB::table('categories')->where('id', self::RETIRED_ROOT)->first();

        $this->assertNotNull($root, 'the row was deleted — it is half the undo record');
        $this->assertSame(0, (int) $root->is_active, '«كوافير» is still a live root');

        $this->assertSame(
            0,
            DB::table('category_parent_child')->where('parent_id', self::RETIRED_ROOT)->count(),
            'the retired root still has children'
        );
    }

    /** Every root is a DOMAIN: no live root may be reachable by no one. */
    public function test_every_live_root_carries_children(): void
    {
        $childless = DB::select('
            SELECT c.id, c.name_ar FROM categories c
            WHERE c.parent_id = 0 AND c.is_active = 1
              AND NOT EXISTS (SELECT 1 FROM category_parent_child p WHERE p.parent_id = c.id)
        ');

        $this->assertSame([], $childless, 'a live root offers nothing: '
            . json_encode(array_map(fn ($r) => "#{$r->id} {$r->name_ar}", $childless), JSON_UNESCAPED_UNICODE));
    }

    /**
     * The legacy tree still lives in `categories` under parent_id > 0. It is
     * inert by design, and this pins the one thing that keeps it inert: nothing
     * outside the roots may be wired, priced or occupied.
     */
    public function test_the_legacy_rows_stay_out_of_the_live_wiring(): void
    {
        $legacy = DB::table('categories')->where('parent_id', '>', 0)->pluck('id');

        $this->assertGreaterThan(0, $legacy->count(), 'the legacy tree vanished — this test lost its subject');

        foreach ([
            'users' => 'category_id',
            'category_parent_child' => 'parent_id',
            'category_platform_services' => 'category_id',
            'category_service_configs' => 'category_id',
        ] as $table => $column) {
            $this->assertSame(
                0,
                DB::table($table)->whereIn($column, $legacy)->count(),
                "{$table}.{$column} points at a legacy category row"
            );
        }
    }

    /** «كوافير» can finally name what it sells — the group is `line`, i.e. priced. */
    public function test_the_surviving_child_carries_the_salon_services(): void
    {
        $groupId = (int) DB::table('option_groups')->where('name_ar', 'خدمات الكوافير والتجميل')->value('id');
        $this->assertGreaterThan(0, $groupId);
        $this->assertSame('line', (string) DB::table('option_groups')->where('id', $groupId)->value('price_role'));

        $offered = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->where('cco.child_id', self::KEEPER)
            ->where('o.group_id', $groupId)
            ->count();

        $this->assertSame(
            DB::table('options')->where('group_id', $groupId)->count(),
            $offered,
            '«كوافير» cannot name every service the retired children could'
        );
    }

    /** The whole point: one salon may serve men AND women. */
    public function test_a_salon_states_its_audience_as_an_option(): void
    {
        $audience = DB::table('option_groups')->where('name_ar', 'الجمهور المستهدف')->first();

        $this->assertNotNull($audience);
        $this->assertSame('modifier', (string) $audience->price_role, 'the audience must be multi-select, not the identity');

        $names = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->where('cco.child_id', self::KEEPER)
            ->where('o.group_id', $audience->id)
            ->pluck('o.name_ar')->all();

        foreach (['رجالي', 'حريمي'] as $expected) {
            $this->assertContains($expected, $names, "«{$expected}» is unsayable by a salon");
        }
    }

    /** No account was stranded, and each kept what its old child was saying. */
    public function test_the_moved_accounts_kept_their_audience(): void
    {
        foreach (['كوافير رجالي' => 'رجالي', 'كوافير حريمى' => 'حريمي'] as $childName => $audience) {
            $childId = (int) DB::table('category_children_master')->where('name_ar', $childName)->value('id');

            $this->assertGreaterThan(0, $childId, 'the master row was deleted, not detached');
            $this->assertSame(
                0,
                DB::table('users')->where('category_child_id', $childId)->count(),
                "«{$childName}» still holds an account"
            );
        }

        $optionId = (int) DB::table('options')->where('name_ar', 'رجالي')->value('id');

        $this->assertGreaterThan(
            0,
            DB::table('users as u')
                ->join('option_user as ou', 'ou.user_id', '=', 'u.id')
                ->where('u.category_child_id', self::KEEPER)
                ->where('ou.option_id', $optionId)
                ->count(),
            'no salon on «كوافير» says it serves men — the claim was lost in the move'
        );
    }

    /** Booking must still be offered, under the root the accounts now sit on. */
    public function test_the_salon_can_still_be_booked_under_professions(): void
    {
        $booking = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        $config = json_decode((string) DB::table('category_service_configs')
            ->where('category_id', self::PROFESSIONS)
            ->where('child_id', self::KEEPER)
            ->where('platform_service_id', $booking)
            ->where('is_active', 1)
            ->value('config'), true) ?: [];

        $this->assertContains('booking_appointment', $config['allowed_item_types'] ?? []);
        $this->assertFalse((bool) ($config['requires_bookable_item'] ?? false), 'a salon reserves a time, not a unit');

        $this->assertTrue(
            DB::table('category_platform_services')
                ->where('category_id', self::PROFESSIONS)
                ->where('child_id', self::KEEPER)
                ->where('platform_service_id', $booking)
                ->where('is_active', 1)
                ->exists(),
            'the config is unreachable without an active link'
        );
    }

    /** Re-running the seeder changes nothing. */
    public function test_the_seeder_is_idempotent(): void
    {
        $before = [
            DB::table('category_child_option')->where('child_id', self::KEEPER)->count(),
            DB::table('users')->where('category_id', self::RETIRED_ROOT)->count(),
            DB::table('category_parent_child')->where('parent_id', self::RETIRED_ROOT)->count(),
        ];

        $this->artisan('db:seed', ['--class' => 'SalonRemodelSeeder', '--no-interaction' => true])->run();

        $after = [
            DB::table('category_child_option')->where('child_id', self::KEEPER)->count(),
            DB::table('users')->where('category_id', self::RETIRED_ROOT)->count(),
            DB::table('category_parent_child')->where('parent_id', self::RETIRED_ROOT)->count(),
        ];

        $this->assertSame($before, $after);
    }
}
