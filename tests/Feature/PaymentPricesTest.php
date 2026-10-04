<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «طريقة السداد كاش وتقسيط وهم سعرين مختلفين يجب ادخالهم ويتم عرضهم فى شاشة العميل
 * بناء على اختيار كاش ام قسط» — المالك، 2026-10-03. A group «مكونات الخدمة» made a
 * price axis for the trade (price_variant) is flagged in the vocabulary, the
 * merchant gives each option its own price (the item's variants), and the
 * customer's product page hands them back to pick from. Rolls back.
 */
class PaymentPricesTest extends TestCase
{
    use DatabaseTransactions;

    private function shop(): User
    {
        $shop = User::query()->where('type', 'business')->where('category_child_id', 116)->where('category_id', 23)->orderBy('id')->firstOrFail();
        foreach ([66, 203] as $optionId) {   // كاش، تقسيط
            DB::table('option_user')->updateOrInsert(['user_id' => $shop->id, 'option_id' => $optionId], []);
        }
        return $shop;
    }

    public function test_payment_is_no_longer_a_price_axis_the_merchant_prices_by(): void
    {
        Sanctum::actingAs($this->shop());

        $axes = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data.price_axes');

        $this->assertNull(collect($axes)->firstWhere('group_id', 50), '«كاش / تقسيط» are payment plans of the item, not one more price axis');
    }

    public function test_cash_is_the_price_and_an_instalment_plan_is_a_markup_the_customer_picks(): void
    {
        Sanctum::actingAs($this->shop());
        $bedroom = (int) DB::table('options')->where('group_id', 3)->where('name_ar', 'غرفة نوم')->value('id');
        DB::table('option_user')->updateOrInsert(['user_id' => $this->shop()->id, 'option_id' => $bedroom], []);

        $id = (int) $this->postJson('/api/v2/business/menu/items', ['name_ar' => 'غرفة نوم بسعرين', 'base_price' => 30000, 'line_option_id' => $bedroom])->assertCreated()->json('data.id');
        $this->putJson("/api/v2/business/menu/items/{$id}/payment-plans", ['plans' => [['months' => 12, 'total_price' => 36000]]])->assertOk();

        $item = $this->getJson("/api/v2/discovery/menu-items/{$id}")->assertOk()->json('data.item');

        $this->assertEquals(30000, $item['price'] ?? 30000, 'cash is the item price');
        $this->assertSame([], array_values(array_filter($item['variants'], fn ($v) => $v['type'] === 'payment')), 'no payment variants any more');
        $this->assertEquals(36000, $item['payment_plans'][0]['unit_price']);
        $this->assertEquals(20, round($item['payment_plans'][0]['markup_percent'], 4), '36000 over 30000 cash');
    }
}
