<?php

namespace Tests\Feature;

use App\Models\BusinessServicePrice;
use App\Models\OptionGroup;
use App\Models\User;
use App\Services\CategoryChildOptionScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Filtering on «عظام» used to answer with a list of hospitals and stop there.
 * The customer opened one and met a price list that never mentioned عظام again,
 * because a price row could not say what it sold.
 *
 * Now the search reaches the priced row itself, and a business counts as doing
 * عظام if it PRICED it, not only if it ticked the box.
 *
 * @see \App\Services\OfferingDiscovery
 */
class OfferingDiscoveryTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0:User,1:int} a business and a line option its child may sell */
    private function sellerAndLine(): array
    {
        $scope = app(CategoryChildOptionScope::class);

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

    private function priceFor(User $business, int $lineId, float $price = 300): BusinessServicePrice
    {
        $row = BusinessServicePrice::create([
            'business_id' => $business->id,
            'child_id' => $business->category_child_id,
            'service_id' => (int) DB::table('platform_services')->where('is_active', 1)->value('id'),
            'bookable_item_type' => 'category',
            'price' => $price,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);

        $row->syncOfferingOptions($lineId);

        return $row;
    }

    /** The search reaches the priced row, and the row says what it is. */
    public function test_an_option_search_returns_the_offering_not_only_the_shop(): void
    {
        [$business, $line] = $this->sellerAndLine();
        $this->priceFor($business, $line, 300);

        $response = $this->getJson('/api/v2/discovery/offerings?' . http_build_query([
            'child_id' => $business->category_child_id,
            'option_ids' => [$line],
        ]));

        $response->assertOk();

        $offerings = collect($response->json('data.offerings.data'));

        $this->assertNotEmpty($offerings, 'the priced line was not found by its own option');

        $mine = $offerings->firstWhere('business.id', (int) $business->id);

        $this->assertNotNull($mine);
        // json_encode drops a whole float's decimal point, so compare loosely
        $this->assertEqualsWithDelta(300.0, $mine['price'], 0.001);
        $this->assertNotSame('', $mine['label'], 'the row must be able to name itself');
        $this->assertSame((int) $line, $mine['line']['id']);
    }

    /** A filter narrows: an offering that does not carry the option is absent. */
    public function test_an_offering_without_the_option_is_not_returned(): void
    {
        [$business, $line] = $this->sellerAndLine();
        $this->priceFor($business, $line);

        $other = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.price_role', OptionGroup::ROLE_LINE)
            ->where('o.id', '!=', $line)
            ->value('o.id');

        $offerings = collect($this->getJson('/api/v2/discovery/offerings?' . http_build_query([
            'child_id' => $business->category_child_id,
            'option_ids' => [$other],
        ]))->json('data.offerings.data'));

        $this->assertNull($offerings->firstWhere('business.id', (int) $business->id));
    }

    /** Every option must be carried, not any of them. */
    public function test_two_options_must_both_be_carried(): void
    {
        [$business, $line] = $this->sellerAndLine();

        $modifier = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.price_role', OptionGroup::ROLE_MODIFIER)
            ->value('o.id');

        if (! $modifier) {
            $this->markTestSkipped('No modifier option exists.');
        }

        $row = $this->priceFor($business, $line);

        $found = fn (array $ids) => collect($this->getJson('/api/v2/discovery/offerings?' . http_build_query([
            'child_id' => $business->category_child_id,
            'option_ids' => $ids,
        ]))->json('data.offerings.data'))->firstWhere('business.id', (int) $business->id);

        $this->assertNull($found([$line, (int) $modifier]), 'the modifier is not on this offering yet');

        $row->syncOfferingOptions($line, [(int) $modifier]);

        $this->assertNotNull($found([$line, (int) $modifier]));
    }

    /**
     * A hospital that PRICED «كشف عظام» does عظام, whether or not it also
     * ticked the box on its profile.
     */
    public function test_pricing_an_option_is_enough_to_be_found_by_it(): void
    {
        [$business, $line] = $this->sellerAndLine();

        DB::table('option_user')->where('user_id', $business->id)->where('option_id', $line)->delete();

        $this->priceFor($business, $line);

        $businesses = collect($this->getJson('/api/v2/discovery/businesses?' . http_build_query([
            'child_id' => $business->category_child_id,
            'option_ids' => [$line],
        ]))->json('data.businesses.data'));

        $this->assertNotNull(
            $businesses->firstWhere('id', (int) $business->id),
            'a business that sells the thing must be findable by it'
        );
    }

    /** An offering nobody switched on is not for sale and must not surface. */
    public function test_an_inactive_offering_is_hidden(): void
    {
        [$business, $line] = $this->sellerAndLine();

        $row = $this->priceFor($business, $line);
        $row->update(['is_active' => 0]);

        $offerings = collect($this->getJson('/api/v2/discovery/offerings?' . http_build_query([
            'child_id' => $business->category_child_id,
            'option_ids' => [$line],
        ]))->json('data.offerings.data'));

        $this->assertNull($offerings->firstWhere('business.id', (int) $business->id));
    }

    /** One offering, however many modifiers it carries, appears once. */
    public function test_an_offering_appears_once_however_many_modifiers_it_has(): void
    {
        [$business, $line] = $this->sellerAndLine();

        $modifiers = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.price_role', OptionGroup::ROLE_MODIFIER)
            ->limit(3)->pluck('o.id')->map(fn ($id) => (int) $id)->all();

        $this->priceFor($business, $line)->syncOfferingOptions($line, $modifiers);

        $offerings = collect($this->getJson('/api/v2/discovery/offerings?' . http_build_query([
            'child_id' => $business->category_child_id,
            'option_ids' => [$line],
        ]))->json('data.offerings.data'));

        $this->assertSame(1, $offerings->where('business.id', (int) $business->id)->count());
    }
}
