<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Options and priced item types looked like two names for one thing, so the
 * obvious move was to merge them. They are two COORDINATES of one line: «عظام»
 * costs nothing alone, «كشف» means nothing alone, «كشف عظام» has a price.
 *
 * Sorting every group by "does the customer pay for this exact thing?" gives
 * three answers, and the middle one — modifier — is why a single is_priceable
 * boolean would not have done.
 *
 * @see \Database\Seeders\OptionPriceRolesSeeder
 */
class OptionPriceRoleTest extends TestCase
{
    private function roleOf(string $groupName): ?string
    {
        return DB::table('option_groups')->where('name_ar', $groupName)->value('price_role');
    }

    /** The option IS what the customer buys. */
    public function test_the_groups_that_carry_a_price_are_lines(): void
    {
        foreach (['تخصصات طبية', 'التحاليل الطبية', 'الأنشطة الرياضية', 'أثاث وتشطيب منزلي',
            'عقارات وممتلكات', 'خدمات الكوافير والتجميل', 'المواد الدراسية',
            // «الغرف» absorbed the hotel room kinds and «عدد الغرف» (2026-08-05):
            // once جناح and ثلاث غرف share a list, that list is the thing bought.
            'الغرف',
            // Split back out of the descriptive «أقسام الصيدلية» the same day.
            'خدمات الصيدلية'] as $group) {
            $this->assertSame(OptionGroup::ROLE_LINE, $this->roleOf($group), "«{$group}» is what gets bought");
        }
    }

    /**
     * The pharmacy is the clearest case of the price test doing real work, and
     * of what it costs to get wrong.
     *
     * The two lists were briefly merged into one descriptive group, which meant
     * a pharmacist could no longer put a price on a blood-pressure check or an
     * injection — a descriptive option can never be priced. What the shop
     * STOCKS and what the pharmacist DOES are two different questions, and only
     * the second is bought by the act.
     */
    public function test_the_pharmacy_stocks_and_services_are_told_apart(): void
    {
        $this->assertSame(OptionGroup::ROLE_DESCRIPTIVE, $this->roleOf('أقسام الصيدلية'));
        $this->assertSame(OptionGroup::ROLE_LINE, $this->roleOf('خدمات الصيدلية'));

        $stock = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'أقسام الصيدلية')->pluck('o.name_ar');

        $services = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'خدمات الصيدلية')->pluck('o.name_ar');

        $this->assertContains('أدوية بشرية', $stock->all(), 'a shelf is not a priced line');
        $this->assertNotContains('حقن', $stock->all(), 'an injection is charged for by the act');

        foreach (['قياس ضغط', 'قياس سكر', 'حقن', 'استشارة دوائية', 'صرف روشتة تأمين'] as $name) {
            $this->assertContains($name, $services->all(), "«{$name}» must be priceable");
        }

        // Both lists must still REACH صيدلية — the split moved options between
        // groups, and a whole list lost in the move would silently halve the
        // picker.
        //
        // Reach, not a total. This used to assert that the pharmacy carried
        // every option of both groups, which is an assertion about the owner's
        // curation rather than about the split: he unticked «حقن» through the
        // admin on 2026-08-10 and the test called his decision a lost link. The
        // withdrawal record has it stamped `admin`, so nothing will hand it
        // back — see ChildOptionDecisionTest.
        foreach (['أقسام الصيدلية', 'خدمات الصيدلية'] as $group) {
            $reached = DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('co.child_id', 215)
                ->where('g.name_ar', $group)
                ->distinct()
                ->count('co.option_id');

            $this->assertGreaterThan(0, $reached, "صيدلية lost «{$group}» entirely in the split");
        }
    }

    /** Nobody buys «مودرن» — they buy «غرفة نوم مودرن», and it costs more. */
    public function test_the_groups_that_only_change_a_price_are_modifiers(): void
    {
        foreach (['طراز الأثاث', 'نوع التعامل', 'المراحل التعليمية',
            'ماركات السيارات', 'نظام الوجبات', 'حالة المنتج'] as $group) {
            $this->assertSame(OptionGroup::ROLE_MODIFIER, $this->roleOf($group), "«{$group}» qualifies a line, it is not one");
        }
    }

    /**
     * The widest groups on the platform are the ones that must never be priced:
     * الدفع والسداد alone reaches 240 children.
     */
    public function test_the_widest_groups_stay_out_of_pricing(): void
    {
        foreach (['الدفع والسداد', 'التسليم والاستلام', 'نطاق التعامل', 'الاستبدال والإرجاع',
            'ملاءمة المكان', 'مرافق الإقامة', 'تصنيف الإقامة', 'أقسام الصيدلية'] as $group) {
            $this->assertSame(OptionGroup::ROLE_DESCRIPTIVE, $this->roleOf($group), "«{$group}» is never bought");
        }

        $widest = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->select('g.name_ar', 'g.price_role', DB::raw('COUNT(DISTINCT co.child_id) AS children'))
            ->groupBy('g.name_ar', 'g.price_role')
            ->orderByDesc('children')
            ->first();

        $this->assertSame(
            OptionGroup::ROLE_DESCRIPTIVE,
            $widest->price_role,
            "the group reaching the most children («{$widest->name_ar}») would drown every pricing screen"
        );
    }

    /** Unclassified means descriptive — a new group never leaks into pricing. */
    public function test_the_default_keeps_a_new_group_out_of_pricing(): void
    {
        $id = DB::table('option_groups')->insertGetId([
            'name_ar' => 'مجموعة اختبار الدور',
            'name_en' => 'Price Role Probe',
            'reorder' => 999,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->assertSame(
                OptionGroup::ROLE_DESCRIPTIVE,
                DB::table('option_groups')->where('id', $id)->value('price_role')
            );
        } finally {
            DB::table('option_groups')->where('id', $id)->delete();
        }
    }

    /**
     * «موضة وعناية شخصية» asked WHO it is for and WHAT is sold at once. The
     * audience qualifies a line; the product IS the line.
     */
    public function test_the_audience_was_split_out_of_the_fashion_group(): void
    {
        $fashion = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'موضة وعناية شخصية')
            ->pluck('o.name_en');

        foreach (['Female', 'Male', 'Kids'] as $audience) {
            $this->assertNotContains($audience, $fashion->all(), 'the audience is not a product');
        }

        $this->assertContains('Clothes', $fashion->all());
        $this->assertContains('Weeding Dresses', $fashion->all());

        $this->assertSame(OptionGroup::ROLE_LINE, $this->roleOf('موضة وعناية شخصية'));
        $this->assertSame(OptionGroup::ROLE_MODIFIER, $this->roleOf('الجمهور المستهدف'));
    }

    /** Splitting a group must not disturb a single child link. */
    public function test_the_split_kept_every_link(): void
    {
        foreach (['Female', 'Male', 'Kids', 'Clothes'] as $name) {
            $id = DB::table('options')->where('name_en', $name)->value('id');

            $this->assertGreaterThan(
                0,
                DB::table('category_child_option')->where('option_id', $id)->count(),
                "«{$name}» lost its children when it changed group"
            );
        }
    }

    /** Re-running must not create a second audience group or move anything back. */
    /**
     * A full seed has to end with the roles this file declares.
     *
     * It did not, until 2026-08-16: `OptionPriceRolesSeeder` was in no seeder
     * list at all, while several of the option seeders write a role of their
     * own when they touch a group. Running `ChildTradeVocabulariesSeeder`
     * alone is enough to turn «أنواع الزجاج» and «أنواع الأجهزة الرياضية» from
     * modifier into line — and the role decides where a group SURFACES, so the
     * flip puts «سيكوريت» on the merchant's pricing screen as a thing to sell
     * rather than a property of the pane he is selling.
     *
     * The database only ever looked right because the roles were restored by
     * hand after each of those runs. Asserted structurally rather than by
     * running a full seed: the ordering is the fix, and it must come after
     * everything that writes a role.
     */
    public function test_the_authority_runs_in_a_full_seed_and_runs_late(): void
    {
        $source = (string) file_get_contents(base_path('database/seeders/DatabaseSeeder.php'));

        $at = fn (string $class) => strpos($source, $class . '::class');

        $roles = $at('OptionPriceRolesSeeder');

        $this->assertNotFalse($roles, 'OptionPriceRolesSeeder is not in the full seed — the roles file is advisory again');

        foreach (['ChildTradeVocabulariesSeeder', 'ChildVocabularyBorrowSeeder', 'CoworkingWorkspaceOptionsSeeder'] as $writer) {
            $this->assertLessThan(
                $roles,
                $at($writer),
                "{$writer} runs after the roles authority and can leave a group with the wrong role"
            );
        }
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $before = [
            DB::table('option_groups')->count(),
            DB::table('category_child_option')->count(),
            DB::table('option_groups')->where('price_role', OptionGroup::ROLE_LINE)->count(),
        ];

        DB::beginTransaction();

        try {
            (new \Database\Seeders\OptionPriceRolesSeeder)->run();

            $this->assertSame($before, [
                DB::table('option_groups')->count(),
                DB::table('category_child_option')->count(),
                DB::table('option_groups')->where('price_role', OptionGroup::ROLE_LINE)->count(),
            ]);
        } finally {
            DB::rollBack();
        }
    }

    /** The admin can see and change the role. */
    public function test_the_admin_screen_shows_and_saves_the_role(): void
    {
        $admin = User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin account to act as.');
        }

        $this->actingAs($admin)
            ->get(route('admin.option-groups.index', ['price_role' => 'modifier'], false))
            ->assertOk()
            ->assertSee('طراز الأثاث')
            ->assertDontSee('الدفع والسداد');
    }
}
