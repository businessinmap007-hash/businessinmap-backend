<?php

namespace Tests\Feature;

use App\Models\GuaranteeLevel;
use App\Models\Order;
use App\Models\Post;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\UserServiceFeeConsent;
use App\Models\Wallet;
use App\Services\OrderFeeSettlementService;
use App\Services\TalentScoutingService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 2026-08-28: the guarantee-level loyalty discount (WalletFeeService,
 * originally booking-only) was extracted into a shared
 * FeeLoyaltyDiscountService and wired into every other platform-fee path too
 * — a user asked for it to cover "any service that charges a fee", not just
 * bookings. This proves the two other wired paths: menu-order commission
 * (OrderFeeSettlementService) and talent-scouting fees (TalentScoutingService).
 * Dispute/arbitration fees are deliberately excluded — see
 * FeeLoyaltyDiscountService's class docblock.
 */
class FeeLoyaltyDiscountAcrossServicesTest extends TestCase
{
    use DatabaseTransactions;

    private function makeLevel(string $targetType, array $overrides = []): GuaranteeLevel
    {
        return GuaranteeLevel::create(array_merge([
            'code' => 'cross_loyalty_' . uniqid(),
            'name_ar' => 'مستوى اختبار',
            'name_en' => 'Test Level',
            'target_type' => $targetType,
            'required_locked_amount' => 100,
            'pending_coverage_amount' => 0,
            'active_coverage_amount' => 0,
            'required_completed_operations' => 0,
            'required_trust_score' => 0,
            'priority' => 0,
            'is_active' => true,
            'fee_discount_amount' => 1,
        ], $overrides));
    }

    private function giveGuarantee(User $user, GuaranteeLevel $level): void
    {
        UserGuarantee::query()->updateOrCreate(
            ['user_id' => $user->id, 'target_type' => $level->target_type],
            [
                'purchased_level_id' => $level->id,
                'effective_level_id' => $level->id,
                'status' => UserGuarantee::STATUS_ACTIVE,
                'locked_amount' => 0,
                'current_coverage_amount' => 0,
                'used_coverage_amount' => 0,
            ]
        );
    }

    public function test_a_menu_order_commission_is_shaved_by_the_business_loyalty_discount(): void
    {
        $business = User::query()->where('type', 'business')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs a business user.');
        $customer = User::query()->where('id', '!=', $business->id)->orderBy('id')->firstOrFail();

        UserServiceFeeConsent::updateOrCreate(
            ['user_id' => $business->id],
            ['fee_auto_charge_enabled' => true, 'rating_enabled' => true, 'enabled_at' => now()]
        );
        app(WalletService::class)->getOrCreateWallet((int) $business->id)
            ->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 100, 'locked_balance' => 0]);

        $level = $this->makeLevel(GuaranteeLevel::TARGET_BUSINESS);
        $this->giveGuarantee($business, $level);

        $order = Order::create([
            'user_id' => $customer->id, 'business_id' => $business->id,
            'fulfillment_type' => Order::FULFILLMENT_DELIVERY, 'status' => 'pending',
            'total' => 50, 'discount' => 0, 'delivery_fee' => 0, 'service_fee' => 5,
            'tax' => 0, 'final_total' => 55, 'payment_method' => 'cash', 'address' => 'x',
        ]);

        $tx = app(OrderFeeSettlementService::class)->settleForOrder($order);

        $this->assertNotNull($tx);
        $this->assertSame(4.0, (float) $tx->amount, 'a 5 EGP commission minus a 1 EGP loyalty discount must charge 4');
        $this->assertDatabaseHas('guarantee_loyalty_grants', [
            'user_id' => $business->id,
            'guarantee_level_id' => $level->id,
            'discount_given' => 1.00,
        ]);
    }

    public function test_a_talent_scouting_fee_is_shaved_by_the_scouts_loyalty_discount(): void
    {
        config(['bim.talent.view_fee' => 5, 'bim.talent.reveal_fee' => 50]);

        $talent = Post::create([
            'user_id' => User::where('type', 'client')->value('id'),
            'type' => 'talent',
            'title' => 'لاعب وسط ١٦ سنة',
            'sport' => 'كرة قدم',
            'playing_position' => 'وسط',
            'is_active' => 1,
        ]);

        $scout = User::create([
            'name' => 'أكاديمية كشافة',
            'email' => 'scout' . uniqid() . '@test.local',
            'password' => bcrypt('x'),
            'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
            'phone' => '01' . random_int(100000000, 999999999),
            'type' => 'business',
            'category_id' => 7,
            'category_child_id' => (int) config('bim.talent.scout_child_id'),
        ]);

        $wallet = app(WalletService::class);
        $wallet->deposit($scout->id, 500, 'test float');

        $level = $this->makeLevel(GuaranteeLevel::TARGET_BUSINESS);
        $this->giveGuarantee($scout, $level);

        $before = (float) $wallet->getOrCreateWallet($scout->id)->balance;

        $view = app(TalentScoutingService::class)->recordView($talent, $scout);

        $after = (float) $wallet->getOrCreateWallet($scout->id)->balance;

        $this->assertSame($before - 4, $after, 'a 5 EGP view fee minus a 1 EGP loyalty discount must charge 4');
        $this->assertSame(4.0, (float) $view->view_fee, 'the stored fee must be the actual discounted amount, not the sticker price');
    }
}
