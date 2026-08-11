<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A unit is reserved by pointing at a WORD, and the word has to exist.
 *
 * `bookable_items.line_option_id` is how a merchant says «مكتب منفصل / الدور
 * الثاني» is the thing being held — it points at an option in a group whose
 * `price_role` is `line`. So `requires_bookable_item = true` on a child with no
 * line group is a demand with no vocabulary to answer it, and that is exactly
 * what «منطقة عمل مشتركة» carried: classified `units` since the modes file was
 * written, and unable to name a single desk.
 *
 * @see \Database\Seeders\CoworkingWorkspaceOptionsSeeder
 */
class CoworkingWorkspaceTest extends TestCase
{
    // Every seeder these run writes to the LIVE dev database.
    use DatabaseTransactions;

    private const COWORKING = 'منطقة عمل مشتركة';

    /**
     * Children that demand a unit list and have no line group to build it from.
     *
     * All but one are the «شركات» suppliers, which demand a unit list because
     * one bulk save switched booking on for the whole root — a brick
     * wholesaler reserves nothing. They leave this list when the flag is
     * corrected, not when a vocabulary is invented for them.
     *
     * Three left it on 2026-08-11 by the other door, having gained a line
     * group: «أمن» #253, «سيفتى ومقاومة حرائق» #250 (the fire half of «أنظمة
     * الأمن والسلامة») and «ألمونتال» #17 (the aluminium rows of «أنواع
     * الأبواب والشبابيك»). Each can now name what it holds.
     *
     * «قاعات تدريب» #282 WAS the one genuine gap — units, and no word for a
     * room — and it closed on 2026-08-11 by borrowing the three room rows of
     * «مساحات العمل». A course room is a course room whether it is rented by
     * the hour from a coworking space or by the day from a training centre.
     *
     * «تبريد وتكييف» #240 closed the same day and the same way: it held the
     * supplier's `modifier` where the workshop's JOB was the priced row, and
     * borrowed the cooling rows of «تخصصات ورش الأجهزة».
     *
     * What is left is entirely the «شركات» suppliers: the flag, not the words.
     *
     * The list may only SHRINK. A new entry means a child was told to register
     * units it cannot name.
     *
     * @var array<int,int>
     */
    private const NAMELESS_UNITS = [
        9, 21, 23, 24, 34, 44, 51, 52, 55, 66, 69, 73, 88, 110, 126, 138,
        145, 146, 159, 173, 180, 182, 207, 214, 228, 232, 247,
        262, 263, 266, 270, 280, 301, 303,
    ];

    private function childId(string $name): int
    {
        $id = DB::table('category_children_master')->where('name_ar', $name)->value('id');

        if (! $id) {
            $this->markTestSkipped("The «{$name}» child is absent.");
        }

        return (int) $id;
    }

    /** @return array<string,array<int,string>> group name => option names */
    private function vocabulary(int $childId, string $role): array
    {
        $rows = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $childId)
            ->where('g.price_role', $role)
            ->get(['g.name_ar as grp', 'o.name_ar as opt']);

        $out = [];

        foreach ($rows as $row) {
            $out[$row->grp][] = $row->opt;
        }

        return array_map(fn ($o) => array_values(array_unique($o)), $out);
    }

    private function bookingConfig(int $childId): array
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        $stored = DB::table('category_service_configs')
            ->where('child_id', $childId)->where('platform_service_id', $serviceId)->value('config');

        return json_decode((string) $stored, true) ?: [];
    }

    /** You do not make an appointment for a desk. */
    public function test_a_desk_is_booked_by_time(): void
    {
        $config = $this->bookingConfig($this->childId(self::COWORKING));

        $this->assertSame(['booking_time'], $config['allowed_item_types'] ?? []);
        $this->assertTrue((bool) ($config['requires_bookable_item'] ?? false));
    }

    /** The demand and the vocabulary that answers it must arrive together. */
    public function test_a_coworking_space_can_name_the_unit_it_rents(): void
    {
        $lines = $this->vocabulary($this->childId(self::COWORKING), 'line');

        $this->assertArrayHasKey('مساحات العمل', $lines);

        foreach (['مكتب منفصل', 'مقعد مشترك', 'منطقة مذاكرة', 'قاعة كورسات', 'قاعة اجتماعات'] as $unit) {
            $this->assertContains($unit, $lines['مساحات العمل'], "a coworking space cannot offer «{$unit}»");
        }
    }

    /**
     * The owner's three offices are one line and a ladder.
     *
     * «مكتب منفصل / مكتب بسكرتارية / مكتب وسكرتارية وريسبشن» are not three kinds
     * of room — they are one room and two extras, and the heading is the
     * combination. Modelled as three lines it cannot express a reception
     * without a secretary, and «خط هاتف» would cost eight rows instead of one.
     */
    public function test_the_office_ladder_is_a_modifier_not_three_lines(): void
    {
        $childId = $this->childId(self::COWORKING);

        $lines = $this->vocabulary($childId, 'line')['مساحات العمل'] ?? [];

        foreach ($lines as $line) {
            $this->assertStringNotContainsString('سكرتارية', $line, 'the ladder was enumerated as a line');
            $this->assertStringNotContainsString('ريسبشن', $line, 'the ladder was enumerated as a line');
        }

        $modifiers = $this->vocabulary($childId, 'modifier');

        $this->assertContains('سكرتارية', $modifiers['خدمات المكتب'] ?? []);
        $this->assertContains('ريسبشن', $modifiers['خدمات المكتب'] ?? []);

        // The same desk at an hourly and a monthly price — «نظام الوجبات».
        $this->assertContains('شهري', $modifiers['نظام التعاقد'] ?? []);
    }

    /**
     * A trade's own facilities do not get bolted onto a group nine others carry.
     *
     * «مرافق ومعدات» #23 is granted WHOLE by `child_option_groups.php`, so a
     * locker added there for a coworking space reaches a wedding hall on the
     * next run. The hotel made the same call with «مرافق الإقامة».
     */
    public function test_a_shared_group_was_not_widened_for_one_trade(): void
    {
        $shared = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'مرافق ومعدات')->pluck('o.name_ar')->all();

        $this->assertSame(['واي فاي', 'وايت بورد'], array_values($shared));

        $own = $this->vocabulary($this->childId(self::COWORKING), 'descriptive');

        $this->assertContains('لوكرز', $own['تجهيزات مساحة العمل'] ?? []);
    }

    /** Registered in option_price_roles.php, or reset to descriptive next run. */
    public function test_the_priced_groups_keep_their_role(): void
    {
        $declared = require database_path('seeders/data/option_price_roles.php');

        $this->assertContains('مساحات العمل', $declared['line']);
        $this->assertContains('خدمات المكتب', $declared['modifier']);
        $this->assertContains('نظام التعاقد', $declared['modifier']);

        DB::beginTransaction();

        try {
            (new \Database\Seeders\OptionPriceRolesSeeder)->run();

            $this->assertSame(
                'line',
                DB::table('option_groups')->where('name_ar', 'مساحات العمل')->value('price_role'),
                'the line group was reset — the sixth time this has happened'
            );
        } finally {
            DB::rollBack();
        }
    }

    /** The branch map must name it, or the collapse default decides its kind. */
    public function test_the_branch_seeder_keeps_the_desk_on_time(): void
    {
        $childId = $this->childId(self::COWORKING);

        DB::beginTransaction();

        try {
            (new \Database\Seeders\BookingChildBranchesSeeder)->run();
            (new \Database\Seeders\ServiceKindsCollapseSeeder)->run();

            $this->assertSame(['booking_time'], $this->bookingConfig($childId)['allowed_item_types'] ?? []);
        } finally {
            DB::rollBack();
        }
    }

    /** Re-running it changes nothing. */
    public function test_the_seeder_is_idempotent(): void
    {
        $childId = $this->childId(self::COWORKING);

        $before = DB::table('category_child_option')->where('child_id', $childId)->count();

        (new \Database\Seeders\CoworkingWorkspaceOptionsSeeder)->run();

        $this->assertSame($before, DB::table('category_child_option')->where('child_id', $childId)->count());
    }

    /**
     * The rule this whole slice is an instance of, applied platform-wide.
     *
     * Every child told to register units must own a word for one. The debt list
     * is what the platform owes today, and it may only shrink.
     */
    public function test_a_child_that_must_register_units_can_name_one(): void
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        $nameless = [];

        foreach (
            DB::table('category_service_configs')
                ->where('platform_service_id', $serviceId)->get(['child_id', 'config']) as $row
        ) {
            $config = json_decode((string) $row->config, true) ?: [];

            if (empty($config['requires_bookable_item'])) {
                continue;
            }

            $hasLine = DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('co.child_id', (int) $row->child_id)
                ->where('g.price_role', 'line')->exists();

            if (! $hasLine) {
                $nameless[(int) $row->child_id] = true;
            }
        }

        $new = array_values(array_diff(array_keys($nameless), self::NAMELESS_UNITS));

        $this->assertEmpty(
            $new,
            'children #' . implode(', #', $new) . ' must register units they have no word for'
        );
    }

    /** An entry leaves the debt list once it is settled, and never comes back. */
    public function test_the_debt_list_holds_only_still_nameless_children(): void
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        $settled = [];

        foreach (self::NAMELESS_UNITS as $childId) {
            $stillDemanded = DB::table('category_service_configs')
                ->where('platform_service_id', $serviceId)->where('child_id', $childId)
                ->get(['config'])
                ->contains(fn ($r) => ! empty((json_decode((string) $r->config, true) ?: [])['requires_bookable_item']));

            $hasLine = DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('co.child_id', $childId)->where('g.price_role', 'line')->exists();

            if (! $stillDemanded || $hasLine) {
                $settled[] = $childId;
            }
        }

        $this->assertEmpty(
            $settled,
            'children #' . implode(', #', $settled) . ' are settled — take them off NAMELESS_UNITS'
        );
    }
}
