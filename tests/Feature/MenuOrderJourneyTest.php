<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The customer's real path through the menu service, end to end.
 *
 * Why this exists: `orders`, `order_items`, `menu_items` and `menu_carts` all
 * hold zero rows — of the six services only booking has ever run. And the
 * BIM-11.1 address bug proved that "has passing tests" is not "works": the old
 * AddressApiTest went green for months while creating an address was
 * *impossible*, because the test invented its own ids using the same wrong
 * assumption as the code, and those ids happened to satisfy the constraint the
 * real ones could not.
 *
 * So the rule here, and the whole point:
 *
 *   EVERY id the customer uses must come out of a previous API RESPONSE.
 *
 * Never `$item->id` from a seeded model — always the id the app would actually
 * have in its hands at that moment. If the app cannot discover it, the test
 * cannot proceed, and that is the bug being hunted.
 *
 * The business's own setup is seeded directly: that is the merchant's journey,
 * not the customer's. Rolls back.
 */
class MenuOrderJourneyTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'secret-password';

    private User $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = $this->seedRestaurantWithAMenu();
    }

    /** The merchant side, seeded — the customer never sees these ids directly. */
    private function seedRestaurantWithAMenu(): User
    {
        $business = new User();
        $business->name = 'مطعم الرحلة';
        $business->email = 'journey-biz-' . uniqid() . '@example.test';
        $business->phone = '0100' . random_int(1000000, 9999999);
        $business->password = self::PASSWORD;
        $business->type = User::TYPE_BUSINESS;
        $business->api_token = Str::random(80);
        $business->save();

        $section = MenuSection::query()->create([
            'business_id' => $business->id,
            'name_ar' => 'المشويات',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $item = MenuItem::query()->create([
            'business_id' => $business->id,
            'menu_section_id' => $section->id,
            'name_ar' => 'فرخة مشوية',
            'price' => 120,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $item->variants()->create([
            'type' => 'size',
            'name_ar' => 'نصف',
            'price' => 70,
            'is_active' => true,
            'is_default' => true,
        ]);

        $item->extras()->create([
            'name_ar' => 'أرز إضافي',
            'price' => 15,
            'is_active' => true,
            'group_key' => 'sides',
        ]);

        return $business->fresh();
    }

    private function registerCustomer(): string
    {
        $response = $this->postJson('/api/v2/auth/register', [
            'name' => 'عميل الرحلة',
            'email' => 'journey-' . uniqid() . '@example.test',
            'phone' => '0155' . random_int(1000000, 9999999),
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertCreated();

        // The app holds a token from here on — nothing else.
        return $response->json('token');
    }

    /**
     * Act as the holder of this token.
     *
     * forgetGuards() is not decoration. Laravel's auth manager caches the
     * resolved user for the whole test method, so simply swapping the Bearer
     * header does NOT re-authenticate: the FIRST identity sticks and every later
     * request is silently still that user. A journey test that switches sides
     * without this is asserting nothing — it would "prove" the restaurant can
     * see the order while actually asking as the customer.
     */
    private function actingWithToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function asCustomer(string $token): self
    {
        return $this->actingWithToken($token);
    }

    public function test_a_customer_can_order_food_from_registration_to_the_kitchen(): void
    {
        $token = $this->registerCustomer();

        // ── Browse. Public: signing in to read a menu would be absurd.
        $menu = $this->getJson('/api/v2/discovery/menu/' . $this->business->id)
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($menu['sections'] ?? [], 'the app must be able to see the menu it is meant to sell');

        // Every id below is the app's own — read out of the response above.
        $item = $menu['sections'][0]['items'][0];
        $itemId = $item['id'];
        $variantId = $item['variants'][0]['id'] ?? null;
        $extraId = $item['extras'][0]['id'] ?? null;

        $this->assertNotNull($variantId, 'the size the customer must choose has to be discoverable');
        $this->assertNotNull($extraId);

        // ── Add to cart, using what the menu gave us.
        $cart = $this->asCustomer($token)->postJson('/api/v2/cart/items', [
            'kind' => 'menu',
            'offering_id' => $itemId,
            'qty' => 2,
            'size_id' => $variantId,
            'extras' => [$extraId],
        ])->assertSuccessful()->json('data');

        $this->assertNotEmpty($cart, 'the cart must come back so the app can show it');

        // ── The cart is readable on its own, as the app's cart screen does.
        $this->asCustomer($token)->getJson('/api/v2/cart')->assertOk();

        // ── Checkout.
        $order = $this->asCustomer($token)->postJson('/api/v2/cart/' . $this->business->id . '/checkout', [
            'fulfillment_type' => 'pickup',
        ])->assertCreated()->json('data.order');

        $this->assertNotNull($order['id'] ?? null);

        // ── The customer can see it in their own list.
        $this->asCustomer($token)->getJson('/api/v2/orders')->assertOk();
        $this->asCustomer($token)->getJson('/api/v2/orders/' . $order['id'])->assertOk();

        // ── And it actually reaches the kitchen.
        $businessOrders = $this->actingWithToken($this->businessToken())
            ->getJson('/api/v2/business/orders')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($businessOrders, 'an order nobody in the restaurant can see is not an order');

        $this->assertContains(
            (int) $order['id'],
            array_column($businessOrders, 'id'),
            'the restaurant must see THIS order, not just some list'
        );
    }

    public function test_the_price_the_customer_is_charged_is_the_price_they_were_shown(): void
    {
        $token = $this->registerCustomer();

        $menu = $this->getJson('/api/v2/discovery/menu/' . $this->business->id)->assertOk()->json('data');
        $item = $menu['sections'][0]['items'][0];

        // The variant is a half chicken at 70, plus 15 of extra rice, times 2.
        $variant = $item['variants'][0];
        $extra = $item['extras'][0];
        $expectedLine = round(((float) $variant['price'] + (float) $extra['price']) * 2, 2);

        $this->asCustomer($token)->postJson('/api/v2/cart/items', [
            'kind' => 'menu',
            'offering_id' => $item['id'],
            'qty' => 2,
            'size_id' => $variant['id'],
            'extras' => [$extra['id']],
        ])->assertSuccessful();

        $cart = $this->asCustomer($token)->getJson('/api/v2/cart')->assertOk()->json('data');

        $this->assertNotEmpty($cart, 'the cart must be readable');

        // A menu that advertises one price and bills another is the worst bug
        // this service can have, so it is asserted against the shown numbers.
        $encoded = json_encode($cart);
        $this->assertStringContainsString((string) $expectedLine, $encoded, 'the line total must match what the menu showed: ' . $encoded);
    }

    public function test_an_inactive_item_is_neither_shown_nor_orderable(): void
    {
        $token = $this->registerCustomer();

        $hidden = MenuItem::query()->create([
            'business_id' => $this->business->id,
            'name_ar' => 'صنف موقوف',
            'price' => 999,
            'is_active' => false,
        ]);

        $menu = $this->getJson('/api/v2/discovery/menu/' . $this->business->id)->assertOk()->json('data');

        $shownIds = collect($menu['sections'])->flatMap(fn ($s) => collect($s['items'])->pluck('id'))->all();
        $this->assertNotContains($hidden->id, $shownIds, 'a withdrawn item must not be on the menu');

        // And the API must refuse it even to a client that kept the old id.
        $this->asCustomer($token)->postJson('/api/v2/cart/items', [
            'kind' => 'menu',
            'offering_id' => $hidden->id,
            'qty' => 1,
        ])->assertStatus(422);
    }

    private function businessToken(): string
    {
        return $this->postJson('/api/v2/auth/login', [
            'email' => $this->business->email,
            'password' => self::PASSWORD,
        ])->assertOk()->json('token');
    }
}
