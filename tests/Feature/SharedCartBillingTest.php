<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsMenu;
use Tests\TestCase;

/**
 * Shared-cart per-person billing: each participant's bill = their own items +
 * their share of the platform service fee (client fee for the menu service) +
 * tax, computed on their order only. Cash on arrival.
 */
class SharedCartBillingTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsMenu;

    private User $host;
    private User $member;
    private User $biz;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bim.menu_tax_rate_percent' => 14]);

        $this->biz = User::query()->where('type', 'business')->firstOrFail();
        $customers = User::query()->where('id', '!=', $this->biz->id)->orderBy('id')->take(2)->get();
        if ($customers->count() < 2) {
            $this->markTestSkipped('Needs two non-business users.');
        }
        [$this->host, $this->member] = [$customers[0], $customers[1]];

        $menuId = (int) DB::table('platform_services')->where('key', 'menu')->value('id');

        // A real category child (FK on users.category_child_id) that has no menu
        // fee yet, so our 10% row is the one resolved.
        $childId = (int) DB::table('category_children_master')
            ->whereNotIn('id', function ($q) use ($menuId) {
                $q->from('category_child_service_fees')->where('platform_service_id', $menuId)->select('child_id');
            })
            ->orderBy('id')->value('id');

        if (! $childId) {
            $this->markTestSkipped('No free category child for the fee fixture.');
        }

        // Point the business at that child and give it a 10% menu client fee
        // (rolled back with the transaction).
        $this->biz->forceFill(['category_child_id' => $childId])->save();
        DB::table('category_child_service_fees')->insert([
            'category_id' => (int) DB::table('categories')->min('id'),
            'child_id' => $childId,
            'platform_service_id' => $menuId,
            'client_fee_enabled' => 1,
            'client_fee_type' => 'percent',
            'client_fee_amount' => 10,
            'currency' => 'EGP',
            'is_active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_each_participant_bill_has_own_service_fee_and_tax(): void
    {
        $item100 = $this->seedMenuItem($this->biz->id, null, 100.0, 'طبق كبير')->id;
        $item50 = $this->seedMenuItem($this->biz->id, null, 50.0, 'طبق صغير')->id;

        Sanctum::actingAs($this->host);
        $shareRes = $this->postJson("/api/v2/cart/{$this->biz->id}/share")->assertCreated();
        $orderId = (int) $shareRes->json('data.order_id');
        $token = $shareRes->json('data.share_token');
        $this->postJson("/api/v2/cart/shared/{$orderId}/items", ['kind' => 'menu', 'offering_id' => $item100, 'qty' => 1])->assertCreated();

        Sanctum::actingAs($this->member);
        $this->postJson("/api/v2/cart/join/{$token}")->assertCreated();
        $this->postJson("/api/v2/cart/shared/{$orderId}/items", ['kind' => 'menu', 'offering_id' => $item50, 'qty' => 1])->assertCreated();

        $res = $this->getJson("/api/v2/cart/shared/{$orderId}")->assertOk();
        $parts = collect($res->json('data.cart.participants'));

        // Host: items 100, fee 10, tax (110*.14)=15.4, total 125.4
        $host = $parts->firstWhere('user_id', $this->host->id);
        $this->assertSame(100.0, (float) $host['items_subtotal']);
        $this->assertSame(10.0, (float) $host['service_fee']);
        $this->assertSame(15.4, (float) $host['tax']);
        $this->assertSame(125.4, (float) $host['total']);

        // Member: items 50, fee 5, tax (55*.14)=7.7, total 62.7
        $member = $parts->firstWhere('user_id', $this->member->id);
        $this->assertSame(50.0, (float) $member['items_subtotal']);
        $this->assertSame(5.0, (float) $member['service_fee']);
        $this->assertSame(7.7, (float) $member['tax']);
        $this->assertSame(62.7, (float) $member['total']);

        // Grand total = sum of the two bills.
        $this->assertSame(188.1, (float) $res->json('data.cart.totals.grand_total'));
        $this->assertSame('cash', $res->json('data.cart.payment_method'));
    }

    public function test_inclusive_prices_are_not_added_on_top(): void
    {
        // Owner declares prices already include service + tax.
        DB::table('business_menu_settings')->insert([
            'business_id' => $this->biz->id,
            'prices_include_service' => 1,
            'prices_include_tax' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // 125.40 = 100 net + 10 service (10%) + 15.40 tax (14% of 110).
        $item = $this->seedMenuItem($this->biz->id, null, 125.40, 'وجبة شاملة')->id;

        Sanctum::actingAs($this->host);
        $orderId = (int) $this->postJson("/api/v2/cart/{$this->biz->id}/share")->json('data.order_id');
        $this->postJson("/api/v2/cart/shared/{$orderId}/items", ['kind' => 'menu', 'offering_id' => $item, 'qty' => 1])->assertCreated();

        $host = collect($this->getJson("/api/v2/cart/shared/{$orderId}")->json('data.cart.participants'))
            ->firstWhere('user_id', $this->host->id);

        $this->assertSame(125.40, (float) $host['items_subtotal']);
        $this->assertSame(10.0, (float) $host['service_fee']);
        $this->assertTrue((bool) $host['service_included']);
        $this->assertSame(15.4, (float) $host['tax']);
        $this->assertTrue((bool) $host['tax_included']);
        $this->assertSame(125.40, (float) $host['total']); // nothing added on top
    }

    public function test_mixed_service_added_tax_included(): void
    {
        DB::table('business_menu_settings')->insert([
            'business_id' => $this->biz->id,
            'prices_include_service' => 0,
            'prices_include_tax' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // 115.40 = 100 net + 15.40 tax (included); service (10) added on top.
        $item = $this->seedMenuItem($this->biz->id, null, 115.40, 'وجبة شاملة ضريبة')->id;

        Sanctum::actingAs($this->host);
        $orderId = (int) $this->postJson("/api/v2/cart/{$this->biz->id}/share")->json('data.order_id');
        $this->postJson("/api/v2/cart/shared/{$orderId}/items", ['kind' => 'menu', 'offering_id' => $item, 'qty' => 1])->assertCreated();

        $host = collect($this->getJson("/api/v2/cart/shared/{$orderId}")->json('data.cart.participants'))
            ->firstWhere('user_id', $this->host->id);

        $this->assertSame(10.0, (float) $host['service_fee']);
        $this->assertFalse((bool) $host['service_included']);
        $this->assertSame(15.4, (float) $host['tax']);
        $this->assertTrue((bool) $host['tax_included']);
        $this->assertSame(125.40, (float) $host['total']); // 115.40 + 10 service
    }

    public function test_checkout_forces_cash(): void
    {
        $item = $this->seedMenuItem($this->biz->id, null, 100.0)->id;

        Sanctum::actingAs($this->host);
        $orderId = (int) $this->postJson("/api/v2/cart/{$this->biz->id}/share")->json('data.order_id');
        $this->postJson("/api/v2/cart/shared/{$orderId}/items", ['kind' => 'menu', 'offering_id' => $item, 'qty' => 1])->assertCreated();

        $this->postJson("/api/v2/cart/shared/{$orderId}/checkout", ['fulfillment_type' => 'dine_in'])->assertCreated();

        // Persisted: service fee (10% of 100) + tax (14% of 110) + final total.
        $this->assertDatabaseHas('orders', [
            'id' => $orderId, 'status' => 'pending', 'payment_method' => 'cash',
            'service_fee' => '10.00', 'tax' => '15.40', 'final_total' => '125.40',
        ]);
    }
}
