<?php

namespace Tests\Feature;

use App\Models\BusinessMenuSetting;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Order;
use App\Models\GuaranteeLevel;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\VisitDeposit;
use App\Models\Wallet;
use App\Services\VisitDepositService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Orders had no financial protection at all — a client could refuse to
 * receive a delivered order and the business ate the shipping cost, with
 * nothing to deter it either way. The merchant sets its own
 * `deposit_required_above` (BusinessMenuSetting); a checkout above it is
 * checked against the customer's guarantee/wallet cover — deliberately
 * NOT a hard gate: an uncovered order still reaches the business, and the
 * business must explicitly choose at accept time (accept_without_deposit)
 * rather than the API silently letting it through.
 */
class OrderDepositTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;

    private User $customer;

    private int $expensiveItemId;

    private int $cheapItemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = User::query()->where('type', 'business')->firstOrFail();

        $this->offerDelivery($this->business);
        $this->customer = User::query()->where('type', 'client')->firstOrFail();

        BusinessMenuSetting::query()->updateOrCreate(
            ['business_id' => $this->business->id],
            ['deposit_required_above' => 200]
        );

        // The business's own wallet must cover its service fee at accept time,
        // or every accept() 422s on something unrelated to this feature.
        Wallet::query()->updateOrCreate(
            ['user_id' => $this->business->id],
            ['balance' => 5000, 'locked_balance' => 0, 'total_in' => 5000, 'total_out' => 0, 'status' => 'active']
        );
        Wallet::query()->updateOrCreate(
            ['user_id' => $this->customer->id],
            ['balance' => 0, 'locked_balance' => 0, 'total_in' => 0, 'total_out' => 0, 'status' => 'active']
        );

        $section = MenuSection::query()->create([
            'business_id' => $this->business->id, 'name_ar' => 'قسم اختبار', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->expensiveItemId = MenuItem::query()->create([
            'business_id' => $this->business->id, 'menu_section_id' => $section->id,
            'name_ar' => 'صنف غالي', 'base_price' => 300, 'is_active' => true, 'sort_order' => 1,
        ])->id;
        $this->cheapItemId = MenuItem::query()->create([
            'business_id' => $this->business->id, 'menu_section_id' => $section->id,
            'name_ar' => 'صنف رخيص', 'base_price' => 50, 'is_active' => true, 'sort_order' => 2,
        ])->id;
    }

    private function checkout(int $itemId): Order
    {
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $itemId, 'qty' => 1])->assertSuccessful();

        $res = $this->postJson('/api/v2/cart/' . $this->business->id . '/checkout', [
            'governorate_id' => (int) \Illuminate\Support\Facades\DB::table('governorates')->orderBy('id')->value('id'),
            'fulfillment_type' => 'delivery', 'address' => 'شارع الاختبار',
        ])->assertCreated();

        return Order::findOrFail((int) $res->json('data.order.id'));
    }

    public function test_an_order_above_threshold_with_no_cover_is_flagged_uncovered(): void
    {
        $order = $this->checkout($this->expensiveItemId);

        $this->assertTrue((bool) $order->requires_deposit);
        $this->assertFalse((bool) $order->deposit_covered);
        $this->assertNull($order->deposit_covered_by);
        $this->assertSame((float) $order->final_total, (float) $order->deposit_amount);
    }

    public function test_an_order_below_threshold_never_requires_a_deposit(): void
    {
        $order = $this->checkout($this->cheapItemId);

        $this->assertFalse((bool) $order->requires_deposit);
        $this->assertNull($order->deposit_amount);
    }

    public function test_no_threshold_set_never_requires_a_deposit(): void
    {
        BusinessMenuSetting::query()->where('business_id', $this->business->id)->update(['deposit_required_above' => null]);

        $order = $this->checkout($this->expensiveItemId);

        $this->assertFalse((bool) $order->requires_deposit);
    }

    public function test_a_wallet_balance_covering_the_order_counts_as_deposit_cover(): void
    {
        Wallet::query()->where('user_id', $this->customer->id)->update(['balance' => 1000]);

        $order = $this->checkout($this->expensiveItemId);

        $this->assertTrue((bool) $order->requires_deposit);
        $this->assertTrue((bool) $order->deposit_covered);
        $this->assertSame('wallet', $order->deposit_covered_by);
    }

    public function test_business_cannot_silently_accept_an_uncovered_order(): void
    {
        $order = $this->checkout($this->expensiveItemId);

        Sanctum::actingAs($this->business);
        $this->postJson('/api/v2/business/orders/' . $order->id . '/accept')
            ->assertStatus(422)
            ->assertJsonValidationErrors('accept_without_deposit');

        $this->assertNull($order->fresh()->prep_status);
    }

    public function test_business_can_explicitly_accept_an_uncovered_order(): void
    {
        $order = $this->checkout($this->expensiveItemId);

        Sanctum::actingAs($this->business);
        $this->postJson('/api/v2/business/orders/' . $order->id . '/accept', [
            'accept_without_deposit' => true,
        ])->assertSuccessful();

        $order->refresh();
        $this->assertSame(Order::PREP_ACCEPTED, $order->prep_status);
        $this->assertTrue((bool) $order->deposit_accepted_without_cover);
    }

    public function test_business_accepts_a_covered_order_normally_without_the_flag(): void
    {
        Wallet::query()->where('user_id', $this->customer->id)->update(['balance' => 1000]);
        $order = $this->checkout($this->expensiveItemId);

        Sanctum::actingAs($this->business);
        $this->postJson('/api/v2/business/orders/' . $order->id . '/accept')->assertSuccessful();

        $order->refresh();
        $this->assertSame(Order::PREP_ACCEPTED, $order->prep_status);
        $this->assertFalse((bool) $order->deposit_accepted_without_cover);
    }

    public function test_the_order_resource_exposes_the_deposit_block(): void
    {
        $order = $this->checkout($this->expensiveItemId);

        Sanctum::actingAs($this->customer);
        $res = $this->getJson('/api/v2/orders/' . $order->id)->assertOk();

        $res->assertJsonPath('data.deposit.required', true)
            ->assertJsonPath('data.deposit.covered', false);
        // assertJsonPath compares strictly (===) — a whole-number total (no
        // menu tax is set for this business) round-trips through JSON as a
        // bare int, not a float, so compare numerically instead.
        $this->assertEquals((float) $order->fresh()->final_total, (float) $res->json('data.deposit.amount'));
    }

    // ── the order on the deposit core: frozen when the business accepts, back when the order ends ──────────────

    private function guarantee(User $user, float $coverage): UserGuarantee
    {
        $levelId = (int) \DB::table('guarantee_levels')->value('id');

        if ($levelId <= 0) {
            $this->markTestSkipped('Needs a guarantee level.');
        }

        UserGuarantee::query()->where('user_id', $user->id)->where('target_type', GuaranteeLevel::TARGET_CLIENT)->delete();

        return UserGuarantee::create([
            'user_id' => $user->id,
            'target_type' => GuaranteeLevel::TARGET_CLIENT,
            'purchased_level_id' => $levelId,
            'effective_level_id' => $levelId,
            'status' => UserGuarantee::STATUS_ACTIVE,
            'current_coverage_amount' => $coverage,
            'used_coverage_amount' => 0,
        ]);
    }

    private function accept(Order $order): void
    {
        Sanctum::actingAs($this->business);
        $this->postJson('/api/v2/business/orders/' . $order->id . '/accept')->assertSuccessful();
    }

    public function test_accepting_a_covered_order_freezes_the_customers_wallet_share(): void
    {
        Wallet::query()->where('user_id', $this->customer->id)->update(['balance' => 1000, 'locked_balance' => 0]);
        $order = $this->checkout($this->expensiveItemId);

        // nothing is held at checkout — only judged
        $this->assertEqualsWithDelta(1000.0, (float) Wallet::query()->where('user_id', $this->customer->id)->value('balance'), 0.001);

        $this->accept($order);

        $wallet = Wallet::query()->where('user_id', $this->customer->id)->firstOrFail();
        $this->assertEqualsWithDelta(1000.0 - (float) $order->final_total, (float) $wallet->balance, 0.001);
        $this->assertEqualsWithDelta((float) $order->final_total, (float) $wallet->locked_balance, 0.001);

        $row = app(VisitDepositService::class)->rowFor($order);
        $this->assertSame(VisitDeposit::STATUS_HELD, $row->status);
    }

    public function test_the_customers_guarantee_and_wallet_combine_to_cover_an_order(): void
    {
        $this->guarantee($this->customer, 100);
        Wallet::query()->where('user_id', $this->customer->id)->update(['balance' => 250, 'locked_balance' => 0]);

        $order = $this->checkout($this->expensiveItemId);

        $this->assertTrue((bool) $order->deposit_covered);
        $this->assertSame('guarantee_wallet', $order->deposit_covered_by);

        $this->accept($order);

        $g = UserGuarantee::query()->where('user_id', $this->customer->id)->where('target_type', 'client')->firstOrFail();
        $this->assertEqualsWithDelta(100.0, (float) $g->used_coverage_amount, 0.001, 'his guarantee comes first');

        $wallet = Wallet::query()->where('user_id', $this->customer->id)->firstOrFail();
        $this->assertEqualsWithDelta((float) $order->final_total - 100.0, (float) $wallet->locked_balance, 0.001, 'the wallet holds only the rest');
    }

    public function test_a_guarantee_that_covers_it_all_leaves_the_wallet_alone(): void
    {
        $this->guarantee($this->customer, 5000);
        $order = $this->checkout($this->expensiveItemId);

        $this->assertSame('guarantee', $order->deposit_covered_by);

        $this->accept($order);

        $wallet = Wallet::query()->where('user_id', $this->customer->id)->firstOrFail();
        $this->assertEqualsWithDelta(0.0, (float) $wallet->locked_balance, 0.001);
        $g = UserGuarantee::query()->where('user_id', $this->customer->id)->where('target_type', 'client')->firstOrFail();
        $this->assertEqualsWithDelta((float) $order->final_total, (float) $g->used_coverage_amount, 0.001);
    }

    public function test_completing_the_order_returns_everything(): void
    {
        $this->guarantee($this->customer, 100);
        Wallet::query()->where('user_id', $this->customer->id)->update(['balance' => 250, 'locked_balance' => 0]);
        $order = $this->checkout($this->expensiveItemId);
        $this->accept($order);

        $order->refresh();
        $order->status = 'completed';
        $order->save();

        // a cash order waits for every party's confirmation of the cash before the deposit goes back
        if ($order->requiresPaymentConfirmation()) {
            $this->assertSame(VisitDeposit::STATUS_HELD, app(VisitDepositService::class)->rowFor($order)->status);

            $order->payment_settled_at = now();
            $order->save();
        }

        $this->assertSame(VisitDeposit::STATUS_RELEASED, app(VisitDepositService::class)->rowFor($order)->status);
        $wallet = Wallet::query()->where('user_id', $this->customer->id)->firstOrFail();
        $this->assertEqualsWithDelta(250.0, (float) $wallet->balance, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $wallet->locked_balance, 0.001);
        $g = UserGuarantee::query()->where('user_id', $this->customer->id)->where('target_type', 'client')->firstOrFail();
        $this->assertEqualsWithDelta(0.0, (float) $g->used_coverage_amount, 0.001);
    }

    public function test_an_order_cancelled_after_acceptance_refunds_the_deposit(): void
    {
        Wallet::query()->where('user_id', $this->customer->id)->update(['balance' => 1000, 'locked_balance' => 0]);
        $order = $this->checkout($this->expensiveItemId);
        $this->accept($order);

        $order->refresh();
        $order->status = 'cancelled';
        $order->save();

        $this->assertSame(VisitDeposit::STATUS_REFUNDED, app(VisitDepositService::class)->rowFor($order)->status);
        $this->assertEqualsWithDelta(1000.0, (float) Wallet::query()->where('user_id', $this->customer->id)->value('balance'), 0.001);
    }

    public function test_an_uncovered_order_accepted_on_the_merchants_responsibility_holds_nothing(): void
    {
        $order = $this->checkout($this->expensiveItemId);

        Sanctum::actingAs($this->business);
        $this->postJson('/api/v2/business/orders/' . $order->id . '/accept', ['accept_without_deposit' => true])->assertSuccessful();

        $this->assertNull(app(VisitDepositService::class)->rowFor($order));
    }

    public function test_a_balance_that_moved_after_checkout_makes_it_uncovered_at_accept(): void
    {
        Wallet::query()->where('user_id', $this->customer->id)->update(['balance' => 1000, 'locked_balance' => 0]);
        $order = $this->checkout($this->expensiveItemId);
        $this->assertTrue((bool) $order->deposit_covered);

        // the customer spent it in between
        Wallet::query()->where('user_id', $this->customer->id)->update(['balance' => 10]);

        Sanctum::actingAs($this->business);
        $this->postJson('/api/v2/business/orders/' . $order->id . '/accept')
            ->assertStatus(422)->assertJsonValidationErrors('accept_without_deposit');
    }

    public function test_the_order_payload_says_where_the_deposit_stands(): void
    {
        Wallet::query()->where('user_id', $this->customer->id)->update(['balance' => 1000, 'locked_balance' => 0]);
        $order = $this->checkout($this->expensiveItemId);

        Sanctum::actingAs($this->customer);
        $this->getJson('/api/v2/orders/' . $order->id)->assertOk()->assertJsonPath('data.deposit.state.status', 'quoted');

        $this->accept($order);

        Sanctum::actingAs($this->customer);
        $this->getJson('/api/v2/orders/' . $order->id)->assertOk()->assertJsonPath('data.deposit.state.status', 'held');
    }
}
