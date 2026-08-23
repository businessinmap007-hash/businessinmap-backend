<?php

namespace Tests\Feature;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The primary market, and the chalet its owner lets for six weeks a year.
 *
 *   «اضافة العقارات والاراضي: وحدات سكنية وادارية وتجارية والمولات التجارية
 *    والمدن الجديدة والمشروعات … الخيارات المتاحة تكون تم التشطيب وتحت
 *    الإنشاء … وخيارات الدفع كاش وتقسيط ٣ و٥ و٧ و١٠ سنوات وتقسيط بدون فوائد»
 *   «المدن السياحية والمصايف التي بها شقق مصيفية، اين نضعها؟»
 *   «هل نغير القسم الرئيسي فنادق سياحية الى سياحة وفنادق؟»
 *
 * What this pins is mostly the answer to the second question, because it is
 * the one that could have gone another way: the deal decides the root, the
 * season does not, and a tourist city is a place and not a trade.
 */
class PropertyAndTourismExpansionTest extends TestCase
{
    use DatabaseTransactions;

    private const BROKER = 517;      // مكتب عقاري

    private const DEVELOPER = 518;   // مطور عقاري

    private const OWNER = 522;       // مالك عقار

    private const HOTELS_ROOT = 24;

    /** @return array<int,string> */
    private function optionsOf(int $childId, string $group): array
    {
        return DB::table('category_child_option as l')
            ->join('options as o', 'o.id', '=', 'l.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('l.child_id', $childId)->where('g.name_ar', $group)
            ->distinct()->pluck('o.name_ar')->all();
    }

    /**
     * A broker, an owner and a developer list the same kinds of property —
     * which is this file's oldest argument, and the reason every addition goes
     * to all three or to none.
     */
    public function test_the_primary_market_has_words_for_what_it_sells(): void
    {
        $added = ['وحدة إدارية', 'وحدة تجارية', 'مول تجاري', 'كمبوند', 'دوبلكس', 'بنتهاوس'];

        foreach ([self::BROKER, self::DEVELOPER, self::OWNER] as $child) {
            $held = $this->optionsOf($child, 'عقارات وممتلكات');

            foreach ($added as $row) {
                $this->assertContains($row, $held, "#{$child} cannot list «{$row}»");
            }

            // …and the fifteen it already had are untouched.
            $this->assertContains('شقة', $held);
            $this->assertContains('مخازن', $held);
        }
    }

    /**
     * «تحت الإنشاء» is not a finish level.
     *
     * «مستوى التشطيب» describes a building that EXISTS and was handed over
     * bare. A unit under construction has no finish level yet — it has a
     * delivery date. Reading them as one axis is how a buyer filtering
     * «تشطيب كامل» loses every off-plan unit that will be handed over
     * finished.
     */
    public function test_construction_status_is_its_own_axis(): void
    {
        foreach ([self::BROKER, self::DEVELOPER, self::OWNER] as $child) {
            $status = $this->optionsOf($child, 'حالة العقار');
            sort($status);

            $this->assertSame(['تحت الإنشاء', 'جاهز للتسليم'], $status, "#{$child}");
        }

        $this->assertSame(
            'modifier',
            DB::table('option_groups')->where('name_ar', 'حالة العقار')->value('price_role'),
            'the status is what a flat costs less for, not a thing anybody buys'
        );

        // The finish level stays where it was and keeps all six of its rows.
        $this->assertCount(6, $this->optionsOf(self::BROKER, 'مستوى التشطيب'));
    }

    /**
     * The instalment TERM is the loudest number on an Egyptian hoarding and
     * had no home. It gets a group of its own rather than five more rows in
     * «الدفع والسداد», which hundreds of children share — «تقسيط ٧ سنوات» on a
     * grocer is nonsense.
     */
    public function test_the_instalment_term_is_scoped_to_property(): void
    {
        foreach ([self::BROKER, self::DEVELOPER, self::OWNER] as $child) {
            $terms = $this->optionsOf($child, 'مدة التقسيط');

            foreach (['تقسيط 3 سنوات', 'تقسيط 5 سنوات', 'تقسيط 7 سنوات', 'تقسيط 10 سنوات'] as $row) {
                $this->assertContains($row, $terms, "#{$child} cannot offer «{$row}»");
            }
        }

        // Nobody outside the property root is asked it.
        $elsewhere = DB::table('category_child_option as l')
            ->join('options as o', 'o.id', '=', 'l.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'مدة التقسيط')
            ->whereNotIn('l.child_id', [self::BROKER, self::DEVELOPER, self::OWNER])
            ->distinct()->pluck('l.child_id')->all();

        $this->assertSame([], $elsewhere, 'the instalment term leaked out of the property root');
    }

    /**
     * «تقسيط بدون فوائد» arrives as a PIN and not as a link.
     *
     * The option has been hand-set-only since 2026-08-10 — it was the one row
     * of «الدفع والسداد» granted per root, which is how it reached 297
     * children while كاش reached 95. PaymentTermsScopeTest enforces that by
     * demanding a pin or a merchant's own tick, and a vocabulary file writing
     * the link satisfies neither: ChildOptionDecisionsSeeder runs last and
     * takes it back off.
     *
     * Real estate is the trade where the claim is the headline, so the owner
     * asked for it — and the pin is that sentence said where the platform
     * reads it.
     */
    public function test_interest_free_instalments_are_pinned_and_not_granted(): void
    {
        $optionId = (int) DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'الدفع والسداد')->where('o.name_ar', 'تقسيط بدون فوائد')
            ->value('o.id');

        $this->assertGreaterThan(0, $optionId);

        foreach ([self::BROKER, self::DEVELOPER, self::OWNER] as $child) {
            $this->assertContains('تقسيط بدون فوائد', $this->optionsOf($child, 'الدفع والسداد'), "#{$child}");

            $this->assertTrue(
                DB::table(ChildOptionDecisions::TABLE)
                    ->where('child_id', $child)->where('option_id', $optionId)
                    ->where('kind', ChildOptionDecisions::PINNED)->exists(),
                "#{$child} holds it without a pin, which the payment policy forbids"
            );
        }

        // …and it is declared in a file, so a database rebuilt from the
        // seeders comes up with the ruling rather than without it.
        $pins = require database_path('seeders/data/child_option_pins.php');

        $this->assertNotEmpty(array_filter(
            $pins,
            fn ($e) => in_array('تقسيط بدون فوائد', $e['options'], true)
        ));
    }

    /** The root says what it has actually held since it took a hostel. */
    public function test_the_root_is_named_for_tourism_and_keeps_its_slug(): void
    {
        $root = DB::table('categories')->where('id', self::HOTELS_ROOT)->first(['name_ar', 'slug']);

        $this->assertSame('سياحة وفنادق', $root->name_ar);

        /*
         * The slug is an address, not a label. Twenty-one places resolve this
         * root by it — the booking branch map, the capability guard, the
         * vocabulary files — and moving it would be a silent unwiring dressed
         * as tidying.
         */
        $this->assertSame('tourist-hotels', $root->slug);
    }

    /**
     * A tourist city is a PLACE, so it is not a child.
     *
     * Every root here answers «what kind of business is this». A child
     * answering «where is it» would put الساحل الشمالي in the same list as
     * فندق — so a hotel in الجونة has to choose which of the two it is, and a
     * guest searching «فندق فى الجونة» has to know which one the merchant
     * picked.
     *
     * The axis already exists and is populated, which is the whole of the
     * answer.
     */
    public function test_the_resort_towns_are_cities_and_not_children(): void
    {
        foreach (['الغردقة', 'شرم الشيخ', 'العلمين', 'الجونة', 'دهب', 'رأس سدر'] as $town) {
            $this->assertTrue(
                DB::table('cities')->where('name_ar', $town)->exists(),
                "«{$town}» is not in the city list, which is where a resort town belongs"
            );
        }

        $children = DB::table('category_parent_child as p')
            ->join('category_children_master as m', 'm.id', '=', 'p.child_id')
            ->where('p.parent_id', self::HOTELS_ROOT)->pluck('m.name_ar')->all();

        foreach (['مدن سياحية', 'مصايف', 'الساحل الشمالي'] as $place) {
            $this->assertNotContains($place, $children, "«{$place}» is a place, and this root lists trades");
        }
    }

    /**
     * What the root was actually missing: a person.
     *
     * All six children were operators. The man with one chalet had nowhere to
     * stand, and he is «اصحاب الشقق» in the question — the same gap «مالك
     * عقار» was created to fill one root over.
     */
    public function test_the_root_gained_the_owner_who_lets_one_unit(): void
    {
        $childId = (int) DB::table('category_children_master')
            ->where('name_ar', 'مالك وحدة مصيفية')->value('id');

        $this->assertGreaterThan(0, $childId, 'the vacation-rental owner has no child');

        $this->assertTrue(
            DB::table('category_parent_child')
                ->where('parent_id', self::HOTELS_ROOT)->where('child_id', $childId)->exists()
        );

        // It takes bookings on the same machinery as a serviced apartment —
        // link AND config, because a link with no config offers a service
        // bounded by nothing.
        $booking = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        $config = DB::table('category_service_configs')
            ->where(['category_id' => self::HOTELS_ROOT, 'child_id' => $childId, 'platform_service_id' => $booking])
            ->first(['is_active', 'config']);

        $this->assertNotNull($config, 'it can be offered a booking it cannot configure');
        $this->assertSame(1, (int) $config->is_active);
        $this->assertNotEmpty(json_decode((string) $config->config, true)['allowed_item_types'] ?? []);

        $this->assertTrue(
            DB::table('category_platform_services')
                ->where(['category_id' => self::HOTELS_ROOT, 'child_id' => $childId, 'platform_service_id' => $booking])
                ->where('is_active', 1)->exists(),
            'the booking config is unreachable'
        );
    }

    /**
     * …and it is offered the unit it lets, not a presidential suite.
     *
     * «الغرف» holds twenty-eight rows including جناح رئاسي and كابينة ديلوكس.
     * A private chalet is also given no «نظام الوجبات» — it feeds nobody, and
     * offering it would put a breakfast price on a screen with no kitchen
     * behind it — and no star rating, which nobody awards one.
     */
    public function test_the_vacation_owner_is_offered_only_what_he_lets(): void
    {
        $childId = (int) DB::table('category_children_master')
            ->where('name_ar', 'مالك وحدة مصيفية')->value('id');

        $units = $this->optionsOf($childId, 'الغرف');

        $this->assertContains('شاليه', $units);
        $this->assertContains('استوديو', $units);
        $this->assertNotContains('جناح رئاسي', $units);
        $this->assertNotContains('سرير في غرفة مشتركة', $units);

        $this->assertSame([], $this->optionsOf($childId, 'نظام الوجبات'), 'a private let serves breakfast to nobody');
        $this->assertSame([], $this->optionsOf($childId, 'تصنيف الإقامة'), 'nobody awards a chalet four stars');

        // The axis the whole request is about: D117 on the pool, D118 on the
        // sea, both his.
        $this->assertContains('إطلالة بحرية', $this->optionsOf($childId, 'إطلالة الوحدة'));
    }

    /**
     * A chalet is sold on one root and let on the other, and the two lists say
     * so.
     */
    public function test_the_deal_decides_the_root_and_not_the_season(): void
    {
        // For sale: a listing, with an area and a finish and an instalment plan.
        $this->assertContains('شاليه', $this->optionsOf(self::OWNER, 'عقارات وممتلكات'));
        $this->assertContains('شقة مصيفية', $this->optionsOf(self::OWNER, 'عقارات وممتلكات'));

        // For the night: a stay, with a calendar and a nightly price.
        $childId = (int) DB::table('category_children_master')
            ->where('name_ar', 'مالك وحدة مصيفية')->value('id');

        $this->assertSame([], $this->optionsOf($childId, 'عقارات وممتلكات'), 'the holiday let is listing property');
        $this->assertNotEmpty($this->optionsOf($childId, 'الغرف'), 'the holiday let cannot name the unit');
    }
}
