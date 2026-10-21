<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMenu;
use Tests\TestCase;

/**
 * «العروض والخصومات»: a discount that reaches the price. The cart prices its lines by the business's running promotions — the best
 * rule per line, never two stacked — and the order remembers what it promised.
 */
class PromotionsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsMenu;

    private User $shop;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = $this->user('Shop', User::TYPE_BUSINESS);
        $this->buyer = $this->user('Buyer', User::TYPE_CLIENT);
    }

    private function user(string $tag, string $type): User
    {
        $u = new User();
        $u->forceFill([
            'name' => $tag . ' ' . Str::random(4), 'type' => $type, 'email' => strtolower($tag) . '-' . uniqid() . '@example.test',
            'phone' => '010' . random_int(10000000, 99999999), 'password' => 'secret-password', 'api_token' => Str::random(60),
        ])->save();

        return $u;
    }

    private function promo(string $kind, array $params, array $targets, array $extra = []): Promotion
    {
        $p = Promotion::create($extra + ['business_id' => $this->shop->id, 'kind' => $kind, 'params' => $params, 'audience' => 'all', 'ends_at' => now()->addDays(5), 'is_active' => true]);
        foreach ($targets as [$type, $id]) {
            $p->targets()->create(['target_type' => $type, 'target_id' => $id]);
        }

        return $p;
    }

    /** Add `qty` of an item and return the cart of the shop. */
    private function addToCart(MenuItem $item, int $qty = 1): array
    {
        Sanctum::actingAs($this->buyer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $item->id, 'qty' => $qty])->assertCreated();

        return $this->cart();
    }

    private function cart(): array
    {
        return collect($this->getJson('/api/v2/cart')->json('data.carts'))->firstWhere('business.id', $this->shop->id);
    }

    private function line(array $cart, MenuItem $item): array
    {
        return collect($cart['items'])->firstWhere('offering_id', $item->id);
    }

    /* ------------------------------------------------------------------ the price */

    public function test_a_percentage_on_a_section_reaches_the_price_of_its_items_only(): void
    {
        $section = $this->seedSection($this->shop->id);
        $inside = $this->seedMenuItem($this->shop->id, $section->id, 100.0, 'A');
        $outside = $this->seedMenuItem($this->shop->id, null, 60.0, 'B');
        $this->promo(Promotion::PERCENT, ['value' => 20], [[Promotion::TARGET_MENU_SECTION, $section->id]]);

        $this->addToCart($inside, 2);
        $cart = $this->addToCart($outside, 1);

        $a = $this->line($cart, $inside);
        $this->assertEquals(160.0, $a['total_price']);
        $this->assertEquals(40.0, $a['discount']);
        $this->assertEquals(200.0, $a['list_total']);
        $this->assertEquals(0.0, $this->line($cart, $outside)['discount']);
        $this->assertSame(220.0, (float) $cart['bill']['menu_subtotal']);
        $this->assertSame(40.0, (float) $cart['bill']['offers_discount']);
    }

    public function test_buy_three_pay_for_the_dearest_one(): void
    {
        $a = $this->seedMenuItem($this->shop->id, null, 100.0, 'A');
        $b = $this->seedMenuItem($this->shop->id, null, 60.0, 'B');
        $c = $this->seedMenuItem($this->shop->id, null, 40.0, 'C');
        $this->promo(Promotion::N_FOR_PRICE_OF, ['n' => 3, 'pay' => 1], [[Promotion::TARGET_ALL, null]]);

        $this->addToCart($a);
        $this->addToCart($b);
        $cart = $this->addToCart($c);

        // 100 + 60 + 40 → pays 100: the two cheaper pieces are free
        $this->assertSame(100.0, (float) $cart['bill']['menu_subtotal']);
        $this->assertSame(100.0, (float) $cart['bill']['offers_discount']);

        // a fourth piece does not complete another group
        $cart = $this->addToCart($a);
        $this->assertSame(100.0 + 100.0 + 60.0 + 40.0 - 160.0, (float) $cart['bill']['menu_subtotal']);
    }

    public function test_two_offers_never_stack_on_one_line_the_better_one_wins(): void
    {
        $section = $this->seedSection($this->shop->id);
        $item = $this->seedMenuItem($this->shop->id, $section->id, 100.0);
        $this->promo(Promotion::PERCENT, ['value' => 10], [[Promotion::TARGET_MENU_SECTION, $section->id]]);
        $best = $this->promo(Promotion::PERCENT, ['value' => 30], [[Promotion::TARGET_MENU_ITEM, $item->id]]);

        $line = $this->line($this->addToCart($item), $item);

        $this->assertEquals(70.0, $line['total_price']);
        $this->assertSame($best->id, $line['promotion_id']);
    }

    public function test_a_spend_threshold_discounts_only_once_the_cart_reaches_it(): void
    {
        $a = $this->seedMenuItem($this->shop->id, null, 100.0, 'A');
        $b = $this->seedMenuItem($this->shop->id, null, 60.0, 'B');
        $this->promo(Promotion::SPEND_PERCENT, ['min_total' => 150, 'value' => 10], [[Promotion::TARGET_ALL, null]]);

        $this->assertSame(0.0, (float) $this->addToCart($a)['bill']['offers_discount']);
        $this->assertSame(16.0, (float) $this->addToCart($b)['bill']['offers_discount']);
    }

    public function test_extras_are_not_discounted(): void
    {
        $item = $this->seedMenuItem($this->shop->id, null, 100.0);
        $extra = $this->seedExtra($item, 'Cheese', 20.0);
        $this->promo(Promotion::PERCENT, ['value' => 50], [[Promotion::TARGET_ALL, null]]);

        Sanctum::actingAs($this->buyer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $item->id, 'qty' => 1, 'extras' => [$extra->id]])->assertCreated();
        $line = $this->line($this->cart(), $item);

        // 120 on the line; only the item's own 100 is halved
        $this->assertEquals(50.0, $line['discount']);
        $this->assertEquals(70.0, $line['total_price']);
    }

    /* ------------------------------------------------------------------ who and when */

    public function test_a_paused_or_ended_promotion_prices_nothing(): void
    {
        $item = $this->seedMenuItem($this->shop->id, null, 100.0);
        $p = $this->promo(Promotion::PERCENT, ['value' => 20], [[Promotion::TARGET_ALL, null]], ['is_active' => false]);
        $this->assertSame(0.0, (float) $this->addToCart($item)['bill']['offers_discount']);

        $p->update(['is_active' => true, 'ends_at' => now()->subMinute()]);
        Sanctum::actingAs($this->buyer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $item->id, 'qty' => 1])->assertCreated();
        $this->assertSame(0.0, (float) $this->cart()['bill']['offers_discount']);

        $p->update(['ends_at' => now()->addDay()]);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $item->id, 'qty' => 1])->assertCreated();
        $this->assertGreaterThan(0, (float) $this->cart()['bill']['offers_discount']);
    }

    public function test_a_price_raised_this_month_is_not_discounted(): void
    {
        $item = $this->seedMenuItem($this->shop->id, null, 100.0);
        $item->update(['base_price' => 120.0]); // a rise, recorded now
        $this->promo(Promotion::PERCENT, ['value' => 20], [[Promotion::TARGET_ALL, null]]);

        $this->assertSame(0.0, (float) $this->addToCart($item)['bill']['offers_discount']);
    }

    public function test_a_first_order_offer_is_for_new_customers_only(): void
    {
        $item = $this->seedMenuItem($this->shop->id, null, 100.0);
        $this->promo(Promotion::PERCENT, ['value' => 20], [[Promotion::TARGET_ALL, null]], ['audience' => Promotion::AUDIENCE_NEW]);

        $this->assertSame(20.0, (float) $this->addToCart($item)['bill']['offers_discount']);

        // a customer who already ordered from this shop is no longer new
        Order::query()->create(['user_id' => $this->buyer->id, 'business_id' => $this->shop->id, 'status' => 'pending', 'fulfillment_type' => 'pickup', 'address' => '', 'total' => 10, 'final_total' => 10]);
        $this->assertSame(0.0, (float) $this->addToCart($item)['bill']['offers_discount']);
    }

    public function test_checkout_keeps_the_discount_and_counts_a_use_until_the_promotion_is_used_up(): void
    {
        $item = $this->seedMenuItem($this->shop->id, null, 100.0);
        $p = $this->promo(Promotion::PERCENT, ['value' => 20], [[Promotion::TARGET_ALL, null]], ['ends_at' => null, 'max_uses' => 1]);

        $this->addToCart($item);
        $this->postJson("/api/v2/cart/{$this->shop->id}/checkout", ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addHour()->toIso8601String()])->assertCreated();

        $order = Order::query()->where('user_id', $this->buyer->id)->where('business_id', $this->shop->id)->latest('id')->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertEquals(80.0, (float) $order->final_total);
        $this->assertEquals(20.0, (float) $order->items()->first()->discount);
        $this->assertSame(1, $p->fresh()->used_count);

        // used up: the next customer pays the list price
        $other = $this->user('Other', User::TYPE_CLIENT);
        Sanctum::actingAs($other);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $item->id, 'qty' => 1])->assertCreated();
        $cart = collect($this->getJson('/api/v2/cart')->json('data.carts'))->firstWhere('business.id', $this->shop->id);
        $this->assertSame(0.0, (float) $cart['bill']['offers_discount']);
    }

    /* ------------------------------------------------------------------ the business writes them */

    public function test_a_business_writes_its_promotions_from_a_catalog_of_its_own_sections(): void
    {
        $section = $this->seedSection($this->shop->id, 'Shirts');
        $a = $this->seedMenuItem($this->shop->id, $section->id, 100.0, 'Blue shirt');
        $this->seedMenuItem($this->shop->id, null, 50.0, 'Loose item');

        Sanctum::actingAs($this->shop);
        $catalog = $this->getJson('/api/v2/business/promotions/catalog')->assertOk()->json('data.menu');
        $this->assertSame('Shirts', collect($catalog)->firstWhere('id', $section->id)['name']);

        $this->postJson('/api/v2/business/promotions', [
            'kind' => 'percent', 'params' => ['value' => 25], 'ends_at' => now()->addWeek()->toDateString(),
            'targets' => [['type' => 'menu_section', 'id' => $section->id]],
        ])->assertCreated()->assertJsonPath('data.promotion.is_running', true);

        // the public page of the shop lists it with the items it covers and their price after it
        $public = $this->getJson("/api/v2/businesses/{$this->shop->id}/promotions")->assertOk()->json('data.promotions.0');
        $this->assertSame('Blue shirt', $public['items'][0]['name']);
        $this->assertEquals(75.0, $public['items'][0]['price_after']);

        // …and the shop's menu carries the badge on that item
        $menu = $this->getJson("/api/v2/discovery/menu/{$this->shop->id}")->assertOk()->json('data.sections');
        $row = collect($menu)->flatMap(fn ($s) => $s['items'])->firstWhere('id', $a->id);
        $this->assertSame('percent', $row['offer']['kind']);
        $this->assertEquals(75.0, $row['offer']['price_after']);

        // the page of the business shows an offers tab
        $this->assertTrue((bool) $this->getJson("/api/v2/businesses/{$this->shop->id}")->json('data.sections.offers'));
    }

    public function test_a_promotion_is_refused_when_it_is_empty_endless_foreign_or_gives_an_item_away(): void
    {
        $item = $this->seedMenuItem($this->shop->id, null, 30.0);
        $rival = $this->user('Rival', User::TYPE_BUSINESS);
        $theirs = $this->seedMenuItem($rival->id, null, 99.0);

        Sanctum::actingAs($this->shop);
        $base = ['kind' => 'percent', 'params' => ['value' => 10], 'ends_at' => now()->addDay()->toDateString(), 'targets' => [['type' => 'menu_item', 'id' => $item->id]]];

        $this->postJson('/api/v2/business/promotions', ['targets' => []] + $base)->assertStatus(422);
        $this->postJson('/api/v2/business/promotions', ['ends_at' => null] + $base)->assertStatus(422);            // no end
        $this->postJson('/api/v2/business/promotions', ['targets' => [['type' => 'menu_item', 'id' => $theirs->id]]] + $base)->assertStatus(422); // not his
        $this->postJson('/api/v2/business/promotions', ['params' => ['value' => 95]] + $base)->assertStatus(422);  // too deep
        $this->postJson('/api/v2/business/promotions', ['kind' => 'amount', 'params' => ['value' => 30]] + $base)->assertStatus(422); // = the price
        $this->postJson('/api/v2/business/promotions', ['kind' => 'n_for_price_of', 'params' => ['n' => 3, 'pay' => 3]] + $base)->assertStatus(422);
        $this->postJson('/api/v2/business/promotions', ['ends_at' => null, 'max_uses' => 50] + $base)->assertCreated(); // a number of orders is an end too

        $this->assertSame(1, Promotion::query()->where('business_id', $this->shop->id)->count());
    }

    public function test_only_the_owner_of_a_promotion_changes_it_and_a_raised_item_is_reported(): void
    {
        $item = $this->seedMenuItem($this->shop->id, null, 100.0);
        $item->update(['base_price' => 110.0]);
        $p = $this->promo(Promotion::PERCENT, ['value' => 10], [[Promotion::TARGET_MENU_ITEM, $item->id]]);

        $rival = $this->user('Rival', User::TYPE_BUSINESS);
        Sanctum::actingAs($rival);
        $this->postJson("/api/v2/business/promotions/{$p->id}/toggle")->assertNotFound();
        $this->deleteJson("/api/v2/business/promotions/{$p->id}")->assertNotFound();

        Sanctum::actingAs($this->shop);
        $res = $this->putJson("/api/v2/business/promotions/{$p->id}", ['kind' => 'percent', 'params' => ['value' => 15], 'ends_at' => now()->addDays(3)->toDateString(), 'targets' => [['type' => 'menu_item', 'id' => $item->id]]])->assertOk();
        $this->assertSame($item->id, $res->json('data.not_yet_discounted.0.id'), 'told which item is not discounted yet');

        $this->postJson("/api/v2/business/promotions/{$p->id}/toggle")->assertOk()->assertJsonPath('data.promotion.is_active', false);
        $this->deleteJson("/api/v2/business/promotions/{$p->id}")->assertOk();
        $this->assertNull(Promotion::query()->find($p->id));
    }

    /* ------------------------------------------------------------------ «اشترِ ن واحصل على م» */

    public function test_buy_n_get_m_charges_the_dearest_n_of_every_m_for_the_owners_three_examples(): void
    {
        $item = $this->seedMenuItem($this->shop->id, null, 50.0);

        foreach ([[1, 3, 6, 4], [2, 6, 6, 4], [4, 8, 8, 4]] as [$pay, $take, $qty, $free]) {
            Promotion::query()->where('business_id', $this->shop->id)->delete();
            $this->promo(Promotion::N_FOR_PRICE_OF, ['n' => $take, 'pay' => $pay], [[Promotion::TARGET_ALL, null]]);

            Sanctum::actingAs($this->buyer);
            \App\Models\Order::query()->where('user_id', $this->buyer->id)->where('business_id', $this->shop->id)->where('status', 'cart')->delete();
            $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $item->id, 'qty' => $qty])->assertCreated();
            $bill = $this->cart()['bill'];

            // one group of $take pieces: only $pay of them are paid; a second incomplete group pays in full
            $groups = intdiv($qty, $take);
            $this->assertEquals(($qty - $groups * ($take - $pay)) * 50.0, $bill['menu_subtotal'], "buy $pay get $take with $qty pieces");
        }
    }

    public function test_a_group_can_be_limited_to_one_item_and_the_repeats_can_be_capped(): void
    {
        $a = $this->seedMenuItem($this->shop->id, null, 100.0, 'A');
        $b = $this->seedMenuItem($this->shop->id, null, 60.0, 'B');
        $p = $this->promo(Promotion::N_FOR_PRICE_OF, ['n' => 2, 'pay' => 1], [[Promotion::TARGET_ALL, null]]);

        $this->addToCart($a);
        $this->assertEquals(60.0, $this->addToCart($b)['bill']['offers_discount'], 'mixed: the dearer of the pair is paid');

        $p->update(['params' => ['n' => 2, 'pay' => 1, 'same_item' => true]]);
        $this->assertEquals(0.0, $this->cart()['bill']['offers_discount'], 'one of each is not a pair of the same item');
    }

    public function test_repeats_in_an_order_stop_at_the_cap(): void
    {
        $item = $this->seedMenuItem($this->shop->id, null, 50.0);
        $this->promo(Promotion::N_FOR_PRICE_OF, ['n' => 2, 'pay' => 1, 'max_groups' => 1], [[Promotion::TARGET_ALL, null]]);

        $cart = $this->addToCart($item, 6);

        $this->assertEquals(50.0, $cart['bill']['offers_discount'], 'six pieces make three pairs, but the offer takes one');
    }

    /* ------------------------------------------------------------------ the quiet days */

    public function test_a_small_rise_does_not_block_a_big_one_does_for_fifteen_days(): void
    {
        $small = $this->seedMenuItem($this->shop->id, null, 100.0, 'Small');
        $small->update(['base_price' => 104.0]);   // +4%, under the 5% tolerance
        $big = $this->seedMenuItem($this->shop->id, null, 100.0, 'Big');
        $big->update(['base_price' => 120.0]);     // +20%
        $this->promo(Promotion::PERCENT, ['value' => 10], [[Promotion::TARGET_ALL, null]]);

        $this->addToCart($small);
        $cart = $this->addToCart($big);

        $this->assertEquals(10.4, $this->line($cart, $small)['discount']);
        $this->assertEquals(0.0, $this->line($cart, $big)['discount']);

        // fifteen days after the rise the big one may be discounted
        \App\Models\OfferingPriceChange::query()->where('priceable_id', $big->id)->update(['changed_at' => now()->subDays(16)]);
        $cart = $this->cart();
        $this->assertEquals(12.0, $this->line($cart, $big)['discount']);
    }

    public function test_the_rule_is_explained_with_its_numbers_and_the_day_an_item_becomes_eligible(): void
    {
        $item = $this->seedMenuItem($this->shop->id, null, 100.0);
        $item->update(['base_price' => 130.0]);

        Sanctum::actingAs($this->shop);
        $this->assertEquals(['quiet_days' => 15, 'rise_tolerance_percent' => 5.0], $this->getJson('/api/v2/business/promotions')->assertOk()->json('data.rules'));

        $res = $this->postJson('/api/v2/business/promotions', [
            'kind' => 'percent', 'params' => ['value' => 10], 'ends_at' => now()->addWeek()->toDateString(),
            'targets' => [['type' => 'menu_item', 'id' => $item->id]],
        ])->assertCreated();

        $this->assertSame(now()->addDays(15)->toDateString(), $res->json('data.not_yet_discounted.0.eligible_from'));
    }

    public function test_an_order_placed_before_the_offer_keeps_its_prices_and_is_never_repriced(): void
    {
        $item = $this->seedMenuItem($this->shop->id, null, 100.0);

        $this->addToCart($item);
        $this->postJson("/api/v2/cart/{$this->shop->id}/checkout", ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addHour()->toIso8601String()])->assertCreated();
        $order = Order::query()->where('user_id', $this->buyer->id)->where('business_id', $this->shop->id)->latest('id')->firstOrFail();
        $this->assertEquals(100.0, (float) $order->final_total);

        // an offer starts afterwards: the placed order is not touched, by a read or by a change of its lines
        $p = $this->promo(Promotion::PERCENT, ['value' => 50], [[Promotion::TARGET_ALL, null]]);
        app(\App\Services\MenuOrderService::class)->recalc($order->fresh());
        $order = $order->fresh();

        $this->assertEquals(0.0, (float) $order->items()->first()->discount);
        $this->assertEquals(100.0, (float) $order->items()->first()->total_price);
        $this->assertSame(0, $p->fresh()->used_count);

        // a NEW cart (a reorder, a fresh purchase) is priced by the offer as usual
        $this->assertEquals(50.0, $this->addToCart($item)['bill']['offers_discount']);
    }
}
