<?php

namespace Tests\Feature;

use App\Models\BusinessServicePrice;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every list of what a business sells was ordered by something the business had
 * no say in — id descending on its own screens, price ascending in discovery —
 * so a restaurant could not put its signature dish at the top of its own menu.
 *
 * The ordering is deliberately local. It sequences a business's offerings among
 * themselves; in a cross-business list it is only a tie-break, because a
 * merchant who could outrank a competitor by ticking «مميّز» would tick it on
 * everything.
 *
 * @see \App\Http\Controllers\Business\OfferingController::reorder()
 */
class OfferingOrderTest extends TestCase
{
    use DatabaseTransactions;

    private function business(): User
    {
        $user = User::query()->where('type', 'business')->first();

        if (! $user) {
            $this->markTestSkipped('No business account.');
        }

        return $user;
    }

    private function items(User $business, int $count = 3): array
    {
        $made = [];

        for ($i = 0; $i < $count; $i++) {
            $made[] = MenuItem::create([
                'business_id' => $business->id,
                'name_ar' => 'صنف ' . $i,
                'base_price' => 10 + $i,
                'is_active' => 1,
                'sort_order' => $i,
            ]);
        }

        return $made;
    }

    /** Position in the posted list IS the order. */
    public function test_the_owner_sequence_is_saved(): void
    {
        $business = $this->business();
        [$a, $b, $c] = $this->items($business);

        $this->actingAs($business)
            ->post(route('business.offerings.reorder', [], false), [
                'order' => ['menu' => [$c->id, $a->id, $b->id]],
            ])
            ->assertRedirect();

        $this->assertSame(0, (int) $c->fresh()->sort_order);
        $this->assertSame(1, (int) $a->fresh()->sort_order);
        $this->assertSame(2, (int) $b->fresh()->sort_order);
    }

    /** «مميّز» lifts a row above the sequence, and unticking puts it back. */
    public function test_featuring_lifts_a_row_and_unfeaturing_releases_it(): void
    {
        $business = $this->business();
        [$a, $b] = $this->items($business, 2);

        $this->actingAs($business)
            ->post(route('business.offerings.reorder', [], false), [
                'order' => ['menu' => [$a->id, $b->id]],
                'featured' => ['menu' => [$b->id]],
            ])->assertRedirect();

        $this->assertTrue((bool) $b->fresh()->is_featured);
        $this->assertFalse((bool) $a->fresh()->is_featured);

        $this->actingAs($business)
            ->post(route('business.offerings.reorder', [], false), [
                'order' => ['menu' => [$a->id, $b->id]],
            ])->assertRedirect();

        $this->assertFalse((bool) $b->fresh()->is_featured);
    }

    /** A posted id is a claim, not a fact. */
    public function test_another_owners_offering_is_never_moved(): void
    {
        $business = $this->business();
        $stranger = User::query()->where('type', 'business')->where('id', '!=', $business->id)->first();

        if (! $stranger) {
            $this->markTestSkipped('Only one business exists.');
        }

        $theirs = MenuItem::create([
            'business_id' => $stranger->id,
            'name_ar' => 'صنف الغير',
            'base_price' => 50,
            'is_active' => 1,
            'sort_order' => 7,
        ]);

        $this->actingAs($business)
            ->post(route('business.offerings.reorder', [], false), [
                'order' => ['menu' => [$theirs->id]],
                'featured' => ['menu' => [$theirs->id]],
            ])->assertRedirect();

        $this->assertSame(7, (int) $theirs->fresh()->sort_order);
        $this->assertFalse((bool) $theirs->fresh()->is_featured);
    }

    /** The owner's own menu leads with what he chose. */
    public function test_the_owners_menu_follows_his_sequence(): void
    {
        $business = $this->business();
        [$a, $b, $c] = $this->items($business);

        $c->update(['is_featured' => 1]);

        $html = $this->actingAs($business)
            ->get(route('business.menu.index', [], false))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(
            strpos($html, 'صنف 0'),
            strpos($html, 'صنف 2'),
            'the featured item must lead the owner\'s own list'
        );
    }

    /**
     * The rule that keeps the flag meaningful: it may not lift a merchant above
     * a cheaper competitor in a cross-business list.
     */
    public function test_featuring_does_not_outrank_a_cheaper_competitor(): void
    {
        $business = $this->business();
        $rival = User::query()->where('type', 'business')
            ->where('id', '!=', $business->id)
            ->where('category_child_id', $business->category_child_id)
            ->first();

        if (! $rival) {
            $this->markTestSkipped('No second business in the same specialty.');
        }

        $line = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.price_role', 'line')->value('o.id');

        if (! $line) {
            $this->markTestSkipped('No line option exists.');
        }

        $serviceId = (int) DB::table('platform_services')->where('is_active', 1)->value('id');

        $mine = BusinessServicePrice::create([
            'business_id' => $business->id, 'child_id' => $business->category_child_id,
            'service_id' => $serviceId, 'bookable_item_type' => 'category',
            'price' => 900, 'currency' => 'EGP', 'is_active' => 1, 'is_featured' => 1,
        ]);
        $mine->syncOfferingOptions((int) $line);

        $theirs = BusinessServicePrice::create([
            'business_id' => $rival->id, 'child_id' => $rival->category_child_id,
            'service_id' => $serviceId, 'bookable_item_type' => 'category',
            'price' => 100, 'currency' => 'EGP', 'is_active' => 1, 'is_featured' => 0,
        ]);
        $theirs->syncOfferingOptions((int) $line);

        $rows = collect($this->getJson('/api/v2/discovery/offerings?' . http_build_query([
            'child_id' => $business->category_child_id,
            'option_ids' => [$line],
            'per_page' => 50,
        ]))->json('data.offerings.data'));

        $minePos = $rows->search(fn ($r) => (int) $r['business']['id'] === (int) $business->id);
        $theirsPos = $rows->search(fn ($r) => (int) $r['business']['id'] === (int) $rival->id);

        $this->assertNotFalse($minePos);
        $this->assertNotFalse($theirsPos);
        $this->assertLessThan($minePos, $theirsPos, 'the cheaper offering must still come first');
    }

    /** The screen shows the handle and the flag. */
    public function test_the_offerings_screen_offers_reordering(): void
    {
        $business = $this->business();
        $this->items($business, 1);

        $this->actingAs($business)
            ->get(route('business.offerings.index', [], false))
            ->assertOk()
            ->assertSee('ترتيب العروض')
            ->assertSee('order[menu][]');
    }
}
