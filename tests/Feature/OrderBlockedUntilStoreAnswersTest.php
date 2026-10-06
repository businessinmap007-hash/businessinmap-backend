<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «امنع الطلب حتى يختار» — المالك، 2026-10-06: a store whose trade asks how it delivers and has not answered cannot
 * be ordered from; and an order is placed only under a delivery/pickup method the store ticked in its profile (what
 * checkout showed and what the profile held differed before). Rolls back.
 */
class OrderBlockedUntilStoreAnswersTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;
    private User $shop;
    private int $menuId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = User::query()->where('type', 'business')->where('category_child_id', 116)->where('category_id', 23)->orderBy('id')->firstOrFail();
        $this->customer = User::query()->where('type', '!=', 'business')->where('id', '!=', $this->shop->id)->orderBy('id')->firstOrFail();
        DB::table('option_user')->where('user_id', $this->shop->id)->whereIn('option_id', $this->deliveryOptionIds())->delete();

        $this->menuId = MenuItem::create(['business_id' => $this->shop->id, 'name_ar' => 'صنف اختبار', 'name_en' => 'Test', 'base_price' => 50.00, 'is_active' => 1])->id;
    }

    private function deliveryOptionIds(): array
    {
        $group = (int) DB::table('option_groups')->where('name_ar', 'التسليم والاستلام')->value('id');

        return DB::table('options')->where('group_id', $group)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function checkoutPrepared(): void
    {
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->menuId, 'qty' => 1])->assertCreated();
    }

    private function checkout(array $extra = [])
    {
        $this->checkoutPrepared();

        return $this->postJson("/api/v2/cart/{$this->shop->id}/checkout", $extra + ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addDay()->toIso8601String()]);
    }

    public function test_the_page_offers_nothing_and_nothing_can_be_ordered_until_the_store_answers(): void
    {
        $this->getJson('/api/v2/businesses/' . $this->shop->id)->assertOk()->assertJsonPath('data.fulfillment.methods', []);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->menuId, 'qty' => 1])->assertUnprocessable()->assertJsonValidationErrors(['offering_id']);
        $this->assertSame(0, Order::query()->where('user_id', $this->customer->id)->where('business_id', $this->shop->id)->count());
    }

    public function test_a_cart_made_before_the_store_changed_its_mind_cannot_be_checked_out_either(): void
    {
        $this->offerDelivery($this->shop, ['استلام من المكان']);
        $this->checkoutPrepared();
        // the store now un-answers: the cart already holds its item, but the order is refused at the door
        DB::table('option_user')->where('user_id', $this->shop->id)->whereIn('option_id', $this->deliveryOptionIds())->delete();

        $this->postJson("/api/v2/cart/{$this->shop->id}/checkout", ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addDay()->toIso8601String()])
            ->assertUnprocessable()->assertJsonValidationErrors(['fulfillment_type']);
    }

    public function test_once_it_answers_the_order_goes_through_under_what_it_ticked(): void
    {
        $this->offerDelivery($this->shop, ['استلام من المكان']);

        $this->checkout()->assertCreated();
    }

    public function test_a_type_the_store_did_not_tick_is_refused(): void
    {
        $this->offerDelivery($this->shop, ['استلام من المكان']); // pickup only

        $this->checkout(['fulfillment_type' => 'delivery', 'address' => 'شارع الاختبار', 'governorate_id' => (int) DB::table('governorates')->orderBy('id')->value('id')])
            ->assertUnprocessable()->assertJsonValidationErrors(['fulfillment_type']);
    }
}
