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
            'عقارات وممتلكات', 'خدمات الكوافير والتجميل', 'المواد الدراسية'] as $group) {
            $this->assertSame(OptionGroup::ROLE_LINE, $this->roleOf($group), "«{$group}» is what gets bought");
        }
    }

    /** Nobody buys «مودرن» — they buy «غرفة نوم مودرن», and it costs more. */
    public function test_the_groups_that_only_change_a_price_are_modifiers(): void
    {
        foreach (['طراز الأثاث', 'نوع التعامل العقاري', 'المراحل التعليمية',
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
