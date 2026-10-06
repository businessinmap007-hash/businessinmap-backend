<?php

namespace Tests\Feature;

use App\Models\MenuItemExtra;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «كيلو وربع ونص لكل منتجات الأكل — 1.5 بورى مشوى و 1 كيلو مقلى: السمك 800، الطهى مشوى 150، مقلى 100،
 * الإجمالى 1050» — المالك، 2026-10-05. A kilo-priced item is ordered in parts (50 g steps); every cooking
 * method is its own line of the invoice priced for ITS weight. Rolls back.
 */
class FractionalQuantitiesTest extends TestCase
{
    use DatabaseTransactions;

    private User $shop;
    private User $customer;
    private int $fish;
    private int $grill;
    private int $fry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = User::query()->where('type', 'business')->where('category_child_id', 101)->orderBy('id')->firstOrFail();

        $this->offerDelivery($this->shop);
        $this->customer = User::query()->where('type', '!=', 'business')->where('id', '!=', $this->shop->id)->orderBy('id')->firstOrFail();
        DB::table('business_working_hours')->where('business_id', $this->shop->id)->delete();
        DB::table('business_addon_prices')->where('business_id', $this->shop->id)->delete();

        $method = fn (string $name) => (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')->where('g.name_ar', 'طريقة الطهي')->where('o.name_ar', $name)->value('o.id');
        $mullet = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')->where('g.name_ar', 'أنواع الأسماك والمأكولات البحرية')->where('o.name_ar', 'أسماك بوري')->value('o.id');

        Sanctum::actingAs($this->shop);
        $this->putJson('/api/v2/business/menu/addons', ['prices' => [$method('مشوي') => 100, $method('مقلي') => 100]])->assertOk();
        $this->fish = (int) $this->postJson('/api/v2/business/menu/items', ['name_ar' => 'بورى', 'base_price' => 320, 'line_option_id' => $mullet])->assertCreated()->json('data.id');
        $this->grill = (int) MenuItemExtra::query()->where('menu_item_id', $this->fish)->where('source_option_id', $method('مشوي'))->value('id');
        $this->fry = (int) MenuItemExtra::query()->where('menu_item_id', $this->fish)->where('source_option_id', $method('مقلي'))->value('id');
    }

    private function add(float $qty, ?int $cooking = null)
    {
        Sanctum::actingAs($this->customer);

        return $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->fish, 'qty' => $qty] + ($cooking ? ['extras' => [$cooking]] : []));
    }

    public function test_food_sold_by_weight_starts_out_sold_by_the_kilo(): void
    {
        $this->assertSame('kg', DB::table('menu_items')->where('id', $this->fish)->value('sale_unit'));
        $this->assertTrue($this->getJson("/api/v2/discovery/menu-items/{$this->fish}")->assertOk()->json('data.item.fractional'));
    }

    public function test_a_kilo_and_a_half_a_quarter_and_a_half_kilo_are_orderable(): void
    {
        $cart = $this->add(1.5)->assertCreated()->json('data.cart');
        $this->assertEquals(1.5, $cart['items'][0]['qty']);
        $this->assertEquals(480, $cart['items'][0]['total_price']);

        foreach ([0.25, 0.5, 0.75] as $part) {
            $cart = $this->add($part)->assertCreated()->json('data.cart');
        }

        $this->assertCount(1, $cart['items'], 'the same fish merges into one line');
        $this->assertEquals(3, $cart['items'][0]['qty'], '1.5 + 0.25 + 0.5 + 0.75');
        $this->assertEquals(960, $cart['items'][0]['total_price']);
    }

    public function test_the_invoice_prices_each_cooking_method_for_its_own_weight(): void
    {
        $this->add(1.5, $this->grill)->assertCreated();
        $cart = $this->add(1, $this->fry)->assertCreated()->json('data.cart');

        $lines = collect($cart['items']);
        $this->assertCount(2, $lines, 'the grilled and the fried are two lines of the same fish');

        $grilled = $lines->firstWhere('qty', 1.5);
        $this->assertEquals(480, $grilled['base_price'] * $grilled['qty']);
        $this->assertEquals(150, $grilled['extras_detail'][0]['total'], 'the grill for 1.5 kg');

        $fried = $lines->firstWhere('qty', 1);
        $this->assertEquals(100, $fried['extras_detail'][0]['total']);

        // fish 2.5 kg × 320 = 800, grill 150, fry 100
        $this->assertEquals(1050, round($lines->sum(fn ($l) => $l['total_price']), 2));
        $this->assertEquals(1050, $cart['total']);
    }

    public function test_the_placed_order_keeps_the_weight_and_the_split(): void
    {
        $this->add(1.5, $this->grill)->assertCreated();
        $this->add(1, $this->fry)->assertCreated();

        $orderId = (int) $this->postJson("/api/v2/cart/{$this->shop->id}/checkout", ['fulfillment_type' => 'pickup', 'pickup_at' => now()->addDay()->toIso8601String()])->assertCreated()->json('data.order.id');

        foreach ([$this->customer, $this->shop] as $reader) {
            Sanctum::actingAs($reader);
            $path = $reader->id === $this->shop->id ? "/api/v2/business/orders/{$orderId}" : "/api/v2/orders/{$orderId}";
            $items = collect($this->getJson($path)->assertOk()->json('data.items'));

            $this->assertEquals([1.5, 1], $items->pluck('qty')->all());
            $this->assertEquals(150, $items->firstWhere('qty', 1.5)['extras_detail'][0]['total']);
            $this->assertEquals(100, $items->firstWhere('qty', 1)['extras_detail'][0]['total']);
            $this->assertNotNull($items[0]['unit'], 'a weighed line says its unit');
        }
    }

    public function test_a_part_of_a_piece_is_refused_and_a_weight_is_kept_to_fifty_grams(): void
    {
        Sanctum::actingAs($this->shop);
        $bedroom = (int) DB::table('options')->where('group_id', 3)->where('name_ar', 'غرفة نوم')->value('id');
        $piece = User::query()->where('type', 'business')->where('category_child_id', 116)->orderBy('id')->firstOrFail();
        DB::table('option_user')->updateOrInsert(['user_id' => $piece->id, 'option_id' => $bedroom], []);
        Sanctum::actingAs($piece);
        $room = (int) $this->postJson('/api/v2/business/menu/items', ['name_ar' => 'غرفة بالقطعة', 'base_price' => 30000, 'line_option_id' => $bedroom])->assertCreated()->json('data.id');

        $this->assertNull(DB::table('menu_items')->where('id', $room)->value('sale_unit'), 'sold by the piece');
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $room, 'qty' => 1.5])->assertUnprocessable();

        $this->add(0.03)->assertUnprocessable(); // under 50 g is not a weight anyone sells
        $odd = $this->add(0.33)->assertCreated()->json('data.cart');
        $this->assertEquals(0.35, $odd['items'][0]['qty'], 'a weight is kept to the nearest 50 g');
    }

    public function test_the_stepper_changes_a_weight_line_by_parts(): void
    {
        $line = (int) $this->add(1)->assertCreated()->json('data.cart.items.0.id');

        $cart = $this->patchJson("/api/v2/cart/items/{$line}", ['qty' => 1.25])->assertOk()->json('data.cart');

        $this->assertEquals(1.25, $cart['items'][0]['qty']);
        $this->assertEquals(400, $cart['items'][0]['total_price']);
    }
}
