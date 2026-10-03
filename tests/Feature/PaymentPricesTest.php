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
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');
        DB::table('service_option_group_placements')->updateOrInsert(
            ['platform_service_id' => $menu, 'option_group_id' => 50, 'child_id' => 116, 'item_type_key' => ''],
            ['usage' => 'price_variant', 'branches_as_sections' => 0, 'is_active' => 1, 'sort_order' => 5, 'show_on_page' => 1, 'display' => 'auto', 'multiple' => 1, 'created_at' => now(), 'updated_at' => now()]
        );

        return $shop;
    }

    public function test_the_vocabulary_hands_over_the_payment_group_as_a_price_axis_with_the_options_he_offers(): void
    {
        Sanctum::actingAs($this->shop());

        $axes = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data.price_axes');

        $payment = collect($axes)->firstWhere('group_id', 50);
        $this->assertNotNull($payment, 'a price axis for this trade');
        $names = array_column($payment['options'], 'name_ar');
        $this->assertContains('كاش', $names);
        $this->assertContains('تقسيط', $names);
        $this->assertNotContains('تقسيط بدون فوائد', $names, 'only what he ticked');
    }

    public function test_cash_and_instalment_are_two_prices_the_customer_picks_between(): void
    {
        Sanctum::actingAs($this->shop());
        $bedroom = (int) DB::table('options')->where('group_id', 3)->where('name_ar', 'غرفة نوم')->value('id');
        DB::table('option_user')->updateOrInsert(['user_id' => $this->shop()->id, 'option_id' => $bedroom], []);

        $id = (int) $this->postJson('/api/v2/business/menu/items', ['name_ar' => 'غرفة نوم بسعرين', 'base_price' => 30000, 'line_option_id' => $bedroom])->assertCreated()->json('data.id');
        $this->postJson("/api/v2/business/menu/items/{$id}/variants", ['type' => 'payment', 'name_ar' => 'كاش', 'price' => 30000, 'is_default' => true])->assertCreated();
        $this->postJson("/api/v2/business/menu/items/{$id}/variants", ['type' => 'payment', 'name_ar' => 'تقسيط', 'price' => 36000])->assertCreated();

        $variants = collect($this->getJson("/api/v2/discovery/menu-items/{$id}")->assertOk()->json('data.item.variants'))->keyBy('name');

        $this->assertSame(30000.0, (float) $variants['كاش']['price']);
        $this->assertTrue($variants['كاش']['is_default']);
        $this->assertSame(36000.0, (float) $variants['تقسيط']['price']);
        $this->assertSame('payment', $variants['تقسيط']['type'], 'the client labels the picker «طريقة الدفع» from the type');
    }
}
