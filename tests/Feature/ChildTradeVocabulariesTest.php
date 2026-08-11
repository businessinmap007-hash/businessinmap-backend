<?php

namespace Tests\Feature;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every trade must be able to say what it sells.
 *
 * Six of «مكاتب»'s thirteen children could not, and ALL THREE of
 * «تكنولوجيا»'s: they carried «نمط تقديم الخدمة», a payment group, and no
 * `line` vocabulary at all. A customer searching for a water-tank cleaner, a
 * cash-in-transit guard or a fire-alarm installer found businesses that could
 * describe how they work and never what they do.
 *
 * @see \Database\Seeders\ChildTradeVocabulariesSeeder
 */
class ChildTradeVocabulariesTest extends TestCase
{
    use DatabaseTransactions;

    /** Every root this seeder speaks for. */
    private const ROOTS = ['offices', 'technology'];

    /**
     * A child whose emptiness is a decision, not a gap.
     *
     * «مأذون شرعى» was here until 2026-08-11. What the owner had withdrawn from
     * it on 08-10 were its six GENERIC options — payment and service-mode — and
     * he then asked for the trade itself: «هل هناك مهام للمأذون الشرعى تضاف الى
     * مكتبه». The six stay off; the register entries went on.
     *
     * @var array<int,string>
     */
    private const DELIBERATELY_BARE = [];

    /** @return array<int,string> */
    private function lines(int $childId): array
    {
        return DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $childId)
            ->where('g.price_role', 'line')
            ->distinct()->pluck('o.name_ar')->all();
    }

    private function childId(string $name): int
    {
        $id = DB::table('category_children_master')->where('name_ar', $name)->value('id');

        if (! $id) {
            $this->markTestSkipped("The «{$name}» child is absent.");
        }

        return (int) $id;
    }

    /** The rule, over every root this seeder speaks for. */
    public function test_every_trade_can_name_what_it_sells(): void
    {
        $mute = [];

        foreach (self::ROOTS as $slug) {
            $rootId = (int) DB::table('categories')->where('slug', $slug)->value('id');

            foreach (
                DB::table('category_parent_child as pc')
                    ->join('category_children_master as c', 'c.id', '=', 'pc.child_id')
                    ->where('pc.parent_id', $rootId)->get(['c.id', 'c.name_ar']) as $child
            ) {
                if (in_array($child->name_ar, self::DELIBERATELY_BARE, true)) {
                    continue;
                }

                if ($this->lines((int) $child->id) === []) {
                    $mute[] = "{$child->name_ar}#{$child->id}@{$slug}";
                }
            }
        }

        $this->assertSame([], $mute, 'these can say how they work and never what they do: ' . implode('، ', $mute));
    }

    /**
     * «مكاتب» is finished: every child answers all three questions.
     *
     * What it sells (`line`), what changes the price (`modifier`) and what
     * describes it (`descriptive`). Twelve of the thirteen had no descriptive
     * axis at all once the owner started removing the payment group, and the
     * platform's shared descriptives are all goods-shaped — «الاستبدال
     * والإرجاع», «التسليم والاستلام», «حالة المنتج» say nothing about an
     * accountant.
     */
    public function test_the_offices_root_answers_all_three_questions(): void
    {
        $rootId = (int) DB::table('categories')->where('slug', 'offices')->value('id');

        $incomplete = [];

        foreach (
            DB::table('category_parent_child as pc')
                ->join('category_children_master as c', 'c.id', '=', 'pc.child_id')
                ->where('pc.parent_id', $rootId)->get(['c.id', 'c.name_ar']) as $child
        ) {
            $roles = DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('co.child_id', (int) $child->id)
                ->distinct()->pluck('g.price_role')->all();

            $missing = array_diff(['line', 'modifier', 'descriptive'], $roles);

            if ($missing !== []) {
                $incomplete[] = "{$child->name_ar}#{$child->id} (" . implode('+', $missing) . ')';
            }
        }

        $this->assertSame([], $incomplete, 'these answer only part of themselves: ' . implode('، ', $incomplete));
    }

    /**
     * The engagement basis is an axis only where there are two answers.
     *
     * A مأذون is paid per act and a printing house per job. A modifier with one
     * possible value asks a question that has no second answer — noise on the
     * pricing screen, not an axis — so neither carries «نظام التعاقد».
     */
    public function test_the_engagement_basis_reaches_only_the_trades_with_two_answers(): void
    {
        $carriers = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'نظام التعاقد')
            ->distinct()->pluck('co.child_id')->map(fn ($id) => (int) $id)->all();

        foreach (['محاسبة', 'محاماه', 'أمن', 'إدارة صفحات'] as $name) {
            $this->assertContains($this->childId($name), $carriers, "«{$name}» is engaged two ways");
        }

        foreach (['مأذون شرعى', 'طباعة'] as $name) {
            $this->assertNotContains($this->childId($name), $carriers, "«{$name}» is paid one way only");
        }

        // A lawyer is not on a subscription. The group was renamed the day it
        // outgrew the desks it was born for.
        $this->assertFalse(
            DB::table('option_groups')->where('name_ar', 'نظام الاشتراك')->exists(),
            'the old name is back, so there are two groups asking one question'
        );
    }

    /**
     * The registrar's office — the owner's second question.
     *
     * Six acts of DOCUMENTATION, which is what separates a مأذون from a lawyer:
     * «تخصصات المحاماة» has «أحوال شخصية وأسرة», and that is litigating a family
     * matter, not entering it in the register.
     */
    public function test_the_registrar_can_name_his_acts(): void
    {
        $childId = $this->childId('مأذون شرعى');

        $lines = $this->lines($childId);

        foreach (['عقد قران', 'توثيق طلاق', 'قائمة منقولات زوجية'] as $act) {
            $this->assertContains($act, $lines, "a registrar cannot record «{$act}»");
        }

        // Coming to the house is a price on the same act, not a fourth act.
        $where = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $childId)->where('g.name_ar', 'مكان العقد')
            ->pluck('o.name_ar')->all();

        $this->assertContains('بالمنزل', $where);

        foreach ($lines as $line) {
            $this->assertStringNotContainsString('بالمنزل', $line, 'the location was enumerated as an act');
        }

        // And the six he took off on 2026-08-10 are still off.
        foreach (['كاش', 'تقسيط', 'فردي', 'فريق عمل', 'أونلاين', 'خاص'] as $withdrawn) {
            $this->assertNotContains(
                $withdrawn,
                DB::table('category_child_option as co')
                    ->join('options as o', 'o.id', '=', 'co.option_id')
                    ->where('co.child_id', $childId)->pluck('o.name_ar')->all(),
                "«{$withdrawn}» came back to a child the owner had stripped"
            );
        }
    }

    /**
     * The systems half and the manpower half never repeat each other.
     *
     * «أمن وسلامة» #254 under تكنولوجيا installs the cameras; «أمن» #253 under
     * مكاتب sends the guards. The market sells them together and the platform
     * keeps them apart, so a business doing both stands as two.
     */
    public function test_security_systems_and_security_guards_stay_apart(): void
    {
        $systems = $this->lines($this->childId('أمن وسلامة'));
        $guards = $this->lines($this->childId('أمن'));

        $this->assertContains('كاميرات مراقبة', $systems);
        $this->assertContains('أنظمة إنذار الحريق', $systems);
        $this->assertContains('حراسة مقرات ومنشآت', $guards);
        $this->assertContains('نقل أموال', $guards);

        $this->assertSame(
            [],
            array_values(array_intersect($systems, $guards)),
            'the two halves of security are selling the same row'
        );
    }

    /** A firewall is written, not bolted to a wall. */
    public function test_the_technology_trades_do_not_overlap(): void
    {
        $software = $this->lines($this->childId('برمجة'));
        $telecom = $this->lines($this->childId('إتصالات'));

        $this->assertContains('أمن معلومات وحماية سيبرانية', $software);
        $this->assertNotContains('أمن معلومات وحماية سيبرانية', $this->lines($this->childId('أمن وسلامة')));

        $this->assertContains('شبكات وواي فاي', $telecom);

        // «دش وأقمار صناعية» is a trade of its own under «مهن وحرفيين».
        foreach ($telecom as $line) {
            $this->assertStringNotContainsString('دش', $line, 'satellite work belongs to child #251');
        }

        $this->assertSame([], array_values(array_intersect($software, $telecom)));
    }

    /** The owner's question: a home-services office is an agency. */
    public function test_a_home_services_office_sells_household_work(): void
    {
        $lines = $this->lines($this->childId('خدمات منزلية'));

        foreach (['تنظيف منازل', 'تنظيف خزانات', 'مكافحة حشرات', 'جليسة أطفال', 'رعاية مسنين'] as $service) {
            $this->assertContains($service, $lines, "a home-services office cannot offer «{$service}»");
        }

        /*
         * And it is not a craftsman. نقاش، سباك and كهربائي are children of
         * «مهن وحرفيين» in their own right; repeating their work here would
         * sell one job under two trades and split every search for it.
         */
        foreach (['نقاش', 'سباكة', 'كهرباء'] as $craft) {
            $this->assertNotContains($craft, $lines, "«{$craft}» is a trade of its own, not a line here");
        }

        // A maid by the visit and a maid living in are two prices for one line.
        $modifiers = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $this->childId('خدمات منزلية'))
            ->where('g.name_ar', 'نظام التعاقد')->pluck('o.name_ar')->all();

        $this->assertContains('بالزيارة', $modifiers);
        $this->assertContains('بالإقامة', $modifiers);

        // The desk's plans are not the maid's.
        $this->assertNotContains('بالساعة', $modifiers);
    }

    /** «إدارة صفحات» borrows the advertising list rather than cloning it. */
    public function test_page_management_borrows_the_digital_half(): void
    {
        $lines = $this->lines($this->childId('إدارة صفحات'));

        $this->assertContains('تسويق رقمي وسوشيال ميديا', $lines);
        $this->assertContains('إعلانات ممولة', $lines);

        // The physical half stays with «دعاية وإعلان» — that IS the difference.
        $this->assertNotContains('لافتات وإعلانات طرق', $lines);
        $this->assertNotContains('مطبوعات دعائية', $lines);

        // And the advertising child keeps all seven.
        $this->assertCount(7, $this->lines($this->childId('دعاية وإعلان')));
    }

    /** One trade, one vocabulary, whichever root the customer came through. */
    public function test_a_trade_under_two_roots_answers_the_same(): void
    {
        foreach (['طباعة', 'أمن', 'تنسيق حفلات'] as $name) {
            $childId = $this->childId($name);

            $roots = DB::table('category_parent_child')->where('child_id', $childId)->count();

            $this->assertGreaterThan(1, $roots, "«{$name}» is expected under more than one root");

            $shared = DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('co.child_id', $childId)->where('g.price_role', 'line')
                ->where('co.category_id', 0)->exists();

            $this->assertTrue($shared, "«{$name}»'s trade vocabulary is tied to one root");
        }
    }

    /** A withdrawn option is never handed back. */
    public function test_the_seeder_refuses_what_the_owner_withdrew(): void
    {
        $childId = $this->childId('خدمات منزلية');

        $optionId = (int) DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'الخدمات المنزلية')->where('o.name_ar', 'تنظيف منازل')->value('o.id');

        DB::table('category_child_option')
            ->where('child_id', $childId)->where('option_id', $optionId)->delete();

        DB::table(ChildOptionDecisions::TABLE)->insert([
            'child_id' => $childId,
            'option_id' => $optionId,
            'kind' => ChildOptionDecisions::WITHDRAWN,
            'source' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new \Database\Seeders\ChildTradeVocabulariesSeeder)->run();

        $this->assertFalse(
            DB::table('category_child_option')
                ->where('child_id', $childId)->where('option_id', $optionId)->exists(),
            'the seeder handed back an option the owner had taken off'
        );
    }

    /** Registered in option_price_roles.php, or reset on the next run. */
    public function test_the_new_groups_keep_their_role(): void
    {
        $declared = require database_path('seeders/data/option_price_roles.php');

        // group name => the role the seeder writes, across every data file it
        // reads. A group that prices and is not declared is reset the next time
        // OptionPriceRolesSeeder runs, silently — six times so far.
        $roles = [];

        foreach (['office_child_vocabularies.php', 'technology_child_vocabularies.php'] as $file) {
            foreach ((require database_path("seeders/data/{$file}"))['groups'] ?? [] as $group => $spec) {
                $roles[$group] = $spec['price_role'];
            }
        }

        foreach ($roles as $group => $role) {
            if ($role === 'descriptive') {
                // The file's own default, and the only role that needs no
                // entry. What it must NOT be is claimed by a priced tier.
                $this->assertNotContains($group, $declared['line'], "«{$group}» is declared as a line");
                $this->assertNotContains($group, $declared['modifier'], "«{$group}» is declared as a modifier");

                continue;
            }

            $this->assertContains(
                $group,
                $declared[$role] ?? [],
                "«{$group}» is not declared as `{$role}` and will be reset"
            );
        }

        DB::beginTransaction();

        try {
            (new \Database\Seeders\OptionPriceRolesSeeder)->run();

            foreach ($roles as $group => $role) {
                $this->assertSame(
                    $role,
                    DB::table('option_groups')->where('name_ar', $group)->value('price_role'),
                    "«{$group}» lost its role"
                );
            }
        } finally {
            DB::rollBack();
        }
    }

    /** Re-running it changes nothing. */
    public function test_the_seeder_is_idempotent(): void
    {
        $before = DB::table('category_child_option')->count();

        (new \Database\Seeders\ChildTradeVocabulariesSeeder)->run();

        $this->assertSame($before, DB::table('category_child_option')->count());
    }
}
