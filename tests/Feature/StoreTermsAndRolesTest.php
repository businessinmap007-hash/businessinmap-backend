<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\Menu\StoreTerms;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase one of re-ordering the option groups — المالك، 2026-10-04: the role of a group is decided PER
 * TRADE. Store policies (returns, minimum order…) are answered once by the store, shown on its page and
 * at checkout and frozen on the order; the wood of a bedroom is a COMPONENT. Rolls back.
 */
class StoreTermsAndRolesTest extends TestCase
{
    use DatabaseTransactions;

    private function group(string $name): int
    {
        return (int) DB::table('option_groups')->where('name_ar', $name)->value('id');
    }

    private function option(string $group, string $name): int
    {
        return (int) DB::table('options')->where('group_id', $this->group($group))->where('name_ar', $name)->value('id');
    }

    private function shop(): User
    {
        return User::query()->where('type', 'business')->where('category_child_id', 73)->orderBy('id')->first()
            ?: $this->markTestSkipped('No business stands on child #73.');
    }

    public function test_the_policies_are_no_longer_item_fields_anywhere(): void
    {
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');
        $policies = array_map(fn ($name) => $this->group($name), ['الاستبدال والإرجاع', 'الحد الأدنى للطلب', 'نطاق التعامل']);

        $this->assertSame(0, DB::table('service_option_group_placements')->where('platform_service_id', $menu)->whereIn('option_group_id', $policies)->where('usage', 'descriptive')->count());
        $this->assertGreaterThan(0, DB::table('service_option_group_placements')->where('platform_service_id', $menu)->whereIn('option_group_id', $policies)->where('usage', 'store_terms')->count());

        // …so the item form of a shop does not ask them per item.
        Sanctum::actingAs($this->shop());
        $asked = collect($this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data.modifiers'))
            ->where('descriptive', true)->pluck('group_name')->all();
        $this->assertNotContains('الاستبدال والإرجاع', $asked);
    }

    public function test_the_wood_of_a_bedroom_is_a_component_and_still_described_per_item(): void
    {
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');

        $this->assertTrue(DB::table('service_option_group_placements')->where('platform_service_id', $menu)->where('child_id', 116)->where('option_group_id', $this->group('أنواع الأخشاب'))->where('usage', 'component')->exists());

        $shop = User::query()->where('type', 'business')->where('category_child_id', 116)->orderBy('id')->firstOrFail();
        DB::table('option_user')->where('user_id', $shop->id)->delete();
        Sanctum::actingAs($shop);
        $wood = collect($this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data.modifiers'))->firstWhere('group_name', 'أنواع الأخشاب');

        $this->assertTrue($wood['descriptive'], 'a component is chosen per item, like a description');
    }

    public function test_a_store_answers_its_terms_once_and_the_customer_reads_them(): void
    {
        $shop = $this->shop();
        $swap = $this->option('الاستبدال والإرجاع', 'استبدال');
        $minimum = $this->option('الحد الأدنى للطلب', 'بدون حد أدنى');
        DB::table('option_user')->where('user_id', $shop->id)->delete();

        Sanctum::actingAs($shop);
        $mine = collect($this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/terms')->assertOk()->json('data.terms'))->keyBy('group_name');
        $this->assertTrue($mine->has('الاستبدال والإرجاع'), 'the trade asks for its return policy');
        $this->assertNotContains(true, array_column($mine['الاستبدال والإرجاع']['options'], 'selected'), 'nothing answered yet');

        $saved = $this->putJson('/api/v2/business/menu/terms', ['groups' => [$this->group('الاستبدال والإرجاع') => [$swap], $this->group('الحد الأدنى للطلب') => [$minimum]]])->assertOk()->json('data.terms');
        $this->assertContains(true, array_column(collect($saved)->firstWhere('group_name', 'الاستبدال والإرجاع')['options'], 'selected'));

        Sanctum::actingAs(User::query()->where('type', '!=', 'business')->orderBy('id')->firstOrFail());
        $seen = collect($this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/discovery/menu/' . $shop->id)->assertOk()->json('data.terms'))->keyBy('group_name');
        $this->assertSame(['استبدال'], array_column($seen['الاستبدال والإرجاع']['options'], 'name'), 'a customer reads only what the store answered');
    }

    public function test_a_group_the_trade_does_not_ask_cannot_be_answered(): void
    {
        $shop = $this->shop();
        Sanctum::actingAs($shop);
        $paymentOption = $this->option('الدفع والسداد', 'كاش'); // a price changer, not a policy
        DB::table('option_user')->where('user_id', $shop->id)->where('option_id', $paymentOption)->delete();

        $this->putJson('/api/v2/business/menu/terms', ['groups' => [$this->group('الدفع والسداد') => [$paymentOption]]])->assertOk();

        $this->assertFalse(DB::table('option_user')->where('user_id', $shop->id)->where('option_id', $paymentOption)->exists());
    }

    public function test_an_order_keeps_the_terms_it_was_placed_under(): void
    {
        app()->setLocale('ar');
        $shop = $this->shop();
        $swap = $this->option('الاستبدال والإرجاع', 'استبدال');
        $change = $this->option('الاستبدال والإرجاع', 'تغيير');
        DB::table('option_user')->where('user_id', $shop->id)->delete();
        Sanctum::actingAs($shop);
        $this->putJson('/api/v2/business/menu/terms', ['groups' => [$this->group('الاستبدال والإرجاع') => [$swap]]])->assertOk();

        app()->setLocale('ar'); // a request resets the locale; the snapshot is taken in the customer's own
        $order = Order::create([
            'user_id' => User::query()->where('type', '!=', 'business')->orderBy('id')->value('id'), 'business_id' => $shop->id,
            'fulfillment_type' => Order::FULFILLMENT_PICKUP, 'status' => 'pending', 'total' => 0, 'discount' => 0, 'delivery_fee' => 0,
            'service_fee' => 0, 'tax' => 0, 'final_total' => 0, 'payment_method' => 'cash', 'address' => 'x',
            'terms' => app(StoreTerms::class)->forCustomer($shop->id),
        ]);

        // The store edits its profile afterwards …
        $this->putJson('/api/v2/business/menu/terms', ['groups' => [$this->group('الاستبدال والإرجاع') => [$change]]])->assertOk();

        app()->setLocale('ar');
        // … the order still says what the customer agreed to.
        $this->assertSame(['استبدال'], array_column($order->fresh()->terms[0]['options'], 'name'));
        $this->assertSame(['تغيير'], array_column(app(StoreTerms::class)->forCustomer($shop->id)[0]['options'], 'name'));
    }
}
