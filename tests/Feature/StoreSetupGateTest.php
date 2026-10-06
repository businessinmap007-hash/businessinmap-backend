<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\User;
use App\Services\Business\StoreSetup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «اجعل الحساب لا يمكن أن يعرض منتجات دون اختيار طرق الاستلام والتسليم» — المالك، 2026-10-06. A business that has not
 * said how it delivers shows no products to customers and cannot be ordered from; its owner still sees everything and
 * the account says what is missing. Rolls back.
 */
class StoreSetupGateTest extends TestCase
{
    use DatabaseTransactions;

    private User $shop;
    private User $customer;
    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = User::query()->where('type', 'business')->where('category_child_id', 116)->where('category_id', 23)->orderBy('id')->firstOrFail();
        $this->customer = User::query()->where('type', '!=', 'business')->where('id', '!=', $this->shop->id)->orderBy('id')->firstOrFail();
        $group = (int) DB::table('option_groups')->where('name_ar', 'التسليم والاستلام')->value('id');
        DB::table('option_user')->where('user_id', $this->shop->id)->whereIn('option_id', DB::table('options')->where('group_id', $group)->select('id'))->delete();

        $this->itemId = MenuItem::create(['business_id' => $this->shop->id, 'name_ar' => 'صنف ظاهر', 'name_en' => 'Visible', 'base_price' => 50.00, 'is_active' => 1])->id;
    }

    public function test_the_account_says_what_is_missing_until_it_chooses(): void
    {
        $this->assertSame(['fulfillment'], app(StoreSetup::class)->missing($this->shop));

        Sanctum::actingAs($this->shop);
        $this->getJson('/api/v2/auth/me')->assertOk()->assertJsonPath('data.setup.complete', false)->assertJsonPath('data.setup.missing.0', 'fulfillment');

        $this->offerDelivery($this->shop, ['استلام من المكان']);
        $this->getJson('/api/v2/auth/me')->assertOk()->assertJsonPath('data.setup.complete', true)->assertJsonPath('data.setup.missing', []);
    }

    public function test_an_incomplete_shop_shows_no_products_to_a_customer(): void
    {
        Sanctum::actingAs($this->customer);

        $menu = $this->getJson('/api/v2/discovery/menu/' . $this->shop->id)->assertOk()->assertJsonPath('data.setup_incomplete', true)->json('data');
        $this->assertSame([], $menu['sections']);

        $this->getJson('/api/v2/discovery/menu-items/' . $this->itemId)->assertNotFound();
        $this->getJson('/api/v2/businesses/' . $this->shop->id)->assertOk()->assertJsonPath('data.sections.menu', false);
    }

    public function test_its_owner_still_sees_his_own_menu(): void
    {
        Sanctum::actingAs($this->shop);

        $menu = $this->getJson('/api/v2/discovery/menu/' . $this->shop->id)->assertOk()->json('data');

        $this->assertArrayNotHasKey('setup_incomplete', $menu);
        $this->assertNotEmpty($menu['sections']);
    }

    public function test_the_products_cannot_be_put_in_a_cart(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 1])->assertUnprocessable()->assertJsonValidationErrors(['offering_id']);
    }

    public function test_once_it_chooses_everything_appears(): void
    {
        $this->offerDelivery($this->shop, ['توصيل طلبات']);
        Sanctum::actingAs($this->customer);

        $menu = $this->getJson('/api/v2/discovery/menu/' . $this->shop->id)->assertOk()->json('data');
        $this->assertArrayNotHasKey('setup_incomplete', $menu);
        $this->assertContains($this->itemId, collect($menu['sections'])->flatMap(fn ($s) => $s['items'] ?? [])->pluck('id')->all());
        $this->getJson('/api/v2/discovery/menu-items/' . $this->itemId)->assertOk();
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->itemId, 'qty' => 1])->assertCreated();
    }

    public function test_a_trade_that_is_never_asked_is_never_held_back(): void
    {
        $group = (int) DB::table('option_groups')->where('name_ar', 'التسليم والاستلام')->value('id');
        $asked = DB::table('service_option_group_placements')->where('option_group_id', $group)->where('usage', 'store_terms')->pluck('child_id')->all();
        $other = User::query()->where('type', 'business')->whereNotNull('category_child_id')->whereNotIn('category_child_id', $asked ?: [0])->first();
        if (! $other) {
            $this->markTestSkipped('Every trade is asked.');
        }
        $ids = app(StoreSetup::class)->incompleteIds();

        $this->assertNotContains((int) $other->id, $ids);
        $this->assertTrue(app(StoreSetup::class)->isComplete($other));
    }
}
