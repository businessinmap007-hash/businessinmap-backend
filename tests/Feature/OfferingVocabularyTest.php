<?php

namespace Tests\Feature;

use App\Models\BusinessServicePrice;
use App\Models\MenuItem;
use App\Models\OptionGroup;
use App\Models\User;
use App\Services\MerchantOfferingVocabulary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A priced row said «كشف» and stopped there. The option the customer searched
 * by — «عظام» — appeared nowhere on it, so a hospital charging 300 for عظام and
 * 250 for باطنة had nowhere to say the second, and the booking that came out
 * had no name worth showing.
 *
 * An offering now carries one LINE option (what is sold) and any number of
 * MODIFIER options (what qualifies it).
 *
 * @see \App\Models\Concerns\HasOfferingOptions
 * @see \App\Services\MerchantOfferingVocabulary
 */
class OfferingVocabularyTest extends TestCase
{
    use DatabaseTransactions;

    private function optionInRole(string $role): object
    {
        $row = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.price_role', $role)
            ->where('g.is_active', 1)
            ->first(['o.id', 'o.name_ar', 'g.name_ar as group_name']);

        if (! $row) {
            $this->markTestSkipped("No option plays the «{$role}» part.");
        }

        return $row;
    }

    private function business(): User
    {
        $user = User::query()->where('type', 'business')->whereNotNull('category_child_id')->first();

        if (! $user) {
            $this->markTestSkipped('No business account with a specialty.');
        }

        return $user;
    }

    /** A business whose specialty actually has something priceable to say. */
    private function businessThatSellsALine(): array
    {
        $scope = app(\App\Services\CategoryChildOptionScope::class);

        foreach (User::query()->where('type', 'business')->whereNotNull('category_child_id')->cursor() as $user) {
            $line = DB::table('options as o')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->whereIn('o.id', $scope->idsFor((int) $user->category_child_id, (int) $user->category_id))
                ->where('g.price_role', OptionGroup::ROLE_LINE)
                ->where('g.is_active', 1)
                ->value('o.id');

            if ($line) {
                return [$user, (int) $line];
            }
        }

        $this->markTestSkipped('No business sells anything priceable.');
    }

    /** «كشف — عظام»: the row can finally say what it sells. */
    public function test_an_offering_names_its_line_and_modifiers(): void
    {
        $business = $this->business();
        $line = $this->optionInRole(OptionGroup::ROLE_LINE);
        $modifier = $this->optionInRole(OptionGroup::ROLE_MODIFIER);

        $item = MenuItem::create([
            'business_id' => $business->id,
            'name_ar' => 'صنف اختبار',
            'base_price' => 100,
            'is_active' => 1,
            'sort_order' => 0,
        ]);

        $item->syncOfferingOptions($line->id, [$modifier->id]);

        $this->assertSame((int) $line->id, (int) $item->lineOption()->id);
        $this->assertSame([(int) $modifier->id], $item->modifierOptions()->pluck('id')->map(fn ($i) => (int) $i)->all());

        // the label speaks the reader's language, so read it the same way
        $named = fn (int $id) => \App\Models\Option::query()->find($id)->displayName;

        $this->assertSame(
            $named((int) $line->id) . ' — ' . $named((int) $modifier->id),
            $item->offeringLabel()
        );
    }

    /** Two lines would mean two different things sold at one price. */
    public function test_an_offering_holds_at_most_one_line(): void
    {
        $business = $this->business();
        $lines = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.price_role', OptionGroup::ROLE_LINE)
            ->limit(2)->pluck('o.id');

        $item = MenuItem::create([
            'business_id' => $business->id,
            'name_ar' => 'صنف اختبار',
            'base_price' => 100,
            'is_active' => 1,
            'sort_order' => 0,
        ]);

        $item->syncOfferingOptions($lines[0]);
        $item->syncOfferingOptions($lines[1]);

        $this->assertSame(
            1,
            DB::table('offering_options')
                ->where('offering_type', $item->getMorphClass())
                ->where('offering_id', $item->id)
                ->where('role', 'line')
                ->count()
        );

        $this->assertSame((int) $lines[1], (int) $item->lineOption()->id);
    }

    /** The same option cannot be both what is sold and what qualifies it. */
    public function test_the_line_is_never_repeated_as_a_modifier(): void
    {
        $business = $this->business();
        $line = $this->optionInRole(OptionGroup::ROLE_LINE);

        $item = MenuItem::create([
            'business_id' => $business->id,
            'name_ar' => 'صنف اختبار',
            'base_price' => 100,
            'is_active' => 1,
            'sort_order' => 0,
        ]);

        $item->syncOfferingOptions($line->id, [$line->id]);

        $this->assertTrue($item->modifierOptions()->isEmpty());
    }

    /**
     * The whole point of the vocabulary: a hospital that practises four
     * specialties must not be shown forty-one.
     */
    public function test_the_merchant_sees_only_what_he_declared(): void
    {
        [$business, $line] = $this->businessThatSellsALine();
        $vocabulary = app(MerchantOfferingVocabulary::class);

        DB::table('option_user')->where('user_id', $business->id)->delete();
        DB::table('option_user')->insert(['user_id' => $business->id, 'option_id' => $line]);

        $result = $vocabulary->for((int) $business->id, (int) $business->category_child_id, (int) $business->category_id);

        $this->assertTrue($result['narrowed']);
        $this->assertSame(
            [(int) $line],
            $result['lines']->flatten()->pluck('id')->map(fn ($i) => (int) $i)->all()
        );
    }

    /** A merchant who ticked nothing still has to be able to price something. */
    public function test_a_silent_merchant_gets_the_whole_priceable_list(): void
    {
        $business = $this->business();

        DB::table('option_user')->where('user_id', $business->id)->delete();

        $result = app(MerchantOfferingVocabulary::class)
            ->for((int) $business->id, (int) $business->category_child_id, (int) $business->category_id);

        $this->assertFalse($result['narrowed']);
    }

    /**
     * The same rule, for the case that actually bit: a merchant who ticked only
     * DESCRIPTIVE things about himself.
     *
     * «واي فاي» is not an answer about what you sell, but it made `narrowed`
     * true and the priceable list empty — so a hotel that had ticked one
     * facility could not name a single room kind on its pricing screen or on
     * its units. Two live businesses were in exactly that state.
     */
    public function test_a_merchant_who_ticked_only_descriptive_things_is_not_silenced(): void
    {
        $business = $this->business();

        $descriptive = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->join('category_child_option as co', 'co.option_id', '=', 'o.id')
            ->where('g.price_role', OptionGroup::ROLE_DESCRIPTIVE)
            ->where('co.child_id', (int) $business->category_child_id)
            ->value('o.id');

        if (! $descriptive) {
            $this->markTestSkipped('This business carries no descriptive option to tick.');
        }

        $vocabulary = app(MerchantOfferingVocabulary::class);

        DB::table('option_user')->where('user_id', $business->id)->delete();

        $silent = $vocabulary->for((int) $business->id, (int) $business->category_child_id, (int) $business->category_id);

        DB::table('option_user')->insert(['user_id' => $business->id, 'option_id' => $descriptive]);

        $result = $vocabulary->for((int) $business->id, (int) $business->category_child_id, (int) $business->category_id);

        $this->assertFalse($result['narrowed'], 'a descriptive tick is not a declaration of what is sold');

        // The invariant, whatever this particular child happens to carry: the
        // descriptive tick changed nothing about what may be priced.
        $this->assertSame(
            $silent['lines']->flatten()->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all(),
            $result['lines']->flatten()->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all()
        );
    }

    /**
     * The live case, named: business 212 is a hotel and had ticked exactly one
     * option about itself. Before the fix it could not name «غرفة مزدوجة».
     */
    public function test_the_hotel_can_name_its_room_kinds(): void
    {
        $hotel = User::query()->where('type', 'business')->find(212);

        if (! $hotel || ! $hotel->category_child_id) {
            $this->markTestSkipped('The reference hotel is gone.');
        }

        $lines = app(MerchantOfferingVocabulary::class)
            ->for((int) $hotel->id, (int) $hotel->category_child_id, (int) $hotel->category_id)['lines'];

        $this->assertArrayHasKey('الغرف', $lines->all(), 'a hotel that cannot say «غرفة» cannot price a room');
    }

    /** Descriptive groups are the widest on the platform and must never appear. */
    public function test_a_descriptive_option_can_never_be_priced(): void
    {
        $vocabulary = app(MerchantOfferingVocabulary::class);

        $cash = DB::table('options')->where('name_en', 'Cash')->value('id');

        if ($cash) {
            $this->assertNull($vocabulary->roleOf((int) $cash), '«كاش» is not something anyone buys');
        }

        $business = $this->business();

        $result = $vocabulary->for((int) $business->id, (int) $business->category_child_id, (int) $business->category_id);

        $names = $result['lines']->keys()->merge($result['modifiers']->keys());

        foreach (['الدفع والسداد', 'التسليم والاستلام', 'ملاءمة المكان'] as $group) {
            $this->assertNotContains($group, $names->all(), "«{$group}» would drown every pricing screen");
        }
    }

    /**
     * «شقة — غرفتين — سوبر لوكس» had nowhere to be written before.
     *
     * «عدد الغرف» was merged into «الغرف» on 2026-08-05, together with the
     * hotel room kinds, and changed role in the process: once استوديو and
     * غرفتين sit in the same list as جناح, that list is what the customer
     * books and pays for, so it is a LINE and no longer a modifier on one.
     * «مستوى التشطيب» is untouched and stays the modifier beside it — which is
     * why the two are asserted separately now instead of in one loop.
     */
    public function test_a_property_listing_can_say_its_rooms_and_finish(): void
    {
        $office = DB::table('category_children_master')->where('name_ar', 'مكتب عقاري')->value('id');

        foreach (['الغرف' => OptionGroup::ROLE_LINE, 'مستوى التشطيب' => OptionGroup::ROLE_MODIFIER] as $group => $role) {
            $row = DB::table('option_groups')->where('name_ar', $group)->first(['id', 'price_role']);

            $this->assertNotNull($row, "«{$group}» does not exist");
            $this->assertSame($role, $row->price_role, "«{$group}» must stay a {$role}");

            $this->assertGreaterThan(
                0,
                DB::table('category_child_option as co')
                    ->join('options as o', 'o.id', '=', 'co.option_id')
                    ->where('o.group_id', $row->id)
                    ->where('co.child_id', $office)
                    ->count(),
                "«مكتب عقاري» cannot say «{$group}»"
            );
        }
    }

    /** Both merchant screens must actually render the picker. */
    public function test_the_merchant_screens_show_the_vocabulary(): void
    {
        [$business] = $this->businessThatSellsALine();

        // read the same list the screen is given, not one picked independently:
        // the controller narrows again to what this merchant ticked
        $vocabulary = app(MerchantOfferingVocabulary::class)
            ->for((int) $business->id, (int) $business->category_child_id, (int) $business->category_id);

        $first = $vocabulary['lines']->flatten()->first();

        $this->assertNotNull($first, 'the screen would have nothing to offer');

        // the owner panel renders in Arabic by default (SetPanelLocale), whatever
        // locale the test process itself is in
        $name = $first->name_ar;

        $this->actingAs($business)
            ->get(route('business.menu.create', [], false))
            ->assertOk()
            ->assertSee('ما الذي تبيعه هنا؟')
            ->assertSee($name);

        $this->actingAs($business)
            ->get(route('business.prices.create', [], false))
            ->assertOk()
            ->assertSee('ما الذي تبيعه هنا؟');
    }

    /**
     * A hospital charges 300 for كشف عظام and 250 for كشف باطنة. Before the
     * line option there was room for exactly one «كشف».
     */
    public function test_two_prices_may_share_an_item_type_when_they_sell_different_lines(): void
    {
        $business = $this->business();

        $lines = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.price_role', OptionGroup::ROLE_LINE)
            ->limit(2)->pluck('o.id');

        $serviceId = (int) DB::table('platform_services')->where('is_active', 1)->value('id');

        $rows = collect($lines)->map(function ($lineId, $i) use ($business, $serviceId) {
            $row = BusinessServicePrice::create([
                'business_id' => $business->id,
                'child_id' => $business->category_child_id,
                'service_id' => $serviceId,
                'bookable_item_type' => 'category',
                'price' => 100 + $i,
                'currency' => 'EGP',
                'is_active' => 1,
            ]);

            $row->syncOfferingOptions((int) $lineId);

            return $row;
        });

        $this->assertNotSame(
            (int) $rows[0]->lineOption()->id,
            (int) $rows[1]->lineOption()->id,
            'the same item type now carries two different priced lines'
        );

        $this->assertNotSame('', $rows[0]->offeringLabel());
    }
}
