<?php

namespace Tests\Feature;

use App\Models\BusinessMenuSetting;
use App\Models\GuaranteeLevel;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Order;
use App\Models\TrustedPartner;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 2 of the order-deposit feature (2026-08-28): a business's standing
 * vouch for a repeat customer waives ITS OWN deposit_required_above check
 * for that customer's future orders — pure trust, nothing frozen on the
 * business's side, conditioned on the customer currently holding some
 * guarantee of their own (never a total stranger). See TrustedPartnerService.
 */
class TrustedPartnerTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;

    private User $customer;

    private int $expensiveItemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = User::query()->where('type', 'business')->firstOrFail();
        $this->customer = User::query()->where('type', 'client')->firstOrFail();

        BusinessMenuSetting::query()->updateOrCreate(
            ['business_id' => $this->business->id],
            ['deposit_required_above' => 200]
        );

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
    }

    private function giveCustomerAGuarantee(): void
    {
        $level = GuaranteeLevel::query()->where('target_type', GuaranteeLevel::TARGET_CLIENT)->first()
            ?: $this->markTestSkipped('Needs a client guarantee level.');

        UserGuarantee::query()->updateOrCreate(
            ['user_id' => $this->customer->id, 'target_type' => GuaranteeLevel::TARGET_CLIENT],
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

    private function checkout(): Order
    {
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'menu', 'offering_id' => $this->expensiveItemId, 'qty' => 1])->assertSuccessful();

        $res = $this->postJson('/api/v2/cart/' . $this->business->id . '/checkout', [
            'fulfillment_type' => 'delivery', 'address' => 'شارع الاختبار',
        ])->assertCreated();

        return Order::findOrFail((int) $res->json('data.order.id'));
    }

    public function test_owner_vouches_for_an_existing_user_who_holds_a_guarantee(): void
    {
        $this->giveCustomerAGuarantee();

        $this->actingAs($this->business)->get('/business/trusted-partners')->assertOk()->assertSee('شركاء موثوقون');

        $this->actingAs($this->business)->post('/business/trusted-partners', [
            'phone' => $this->customer->phone,
        ])->assertRedirect();

        $this->assertDatabaseHas('trusted_partners', [
            'business_id' => $this->business->id,
            'user_id' => $this->customer->id,
            'is_active' => 1,
        ]);
    }

    public function test_vouching_for_a_user_with_no_guarantee_is_refused(): void
    {
        // No guarantee given — the hard precondition.
        $this->actingAs($this->business)->post('/business/trusted-partners', [
            'phone' => $this->customer->phone,
        ])->assertSessionHasErrors('phone');

        $this->assertDatabaseMissing('trusted_partners', [
            'business_id' => $this->business->id,
            'user_id' => $this->customer->id,
        ]);
    }

    public function test_an_unknown_phone_is_reported(): void
    {
        do {
            $absent = '0100' . random_int(1000000, 9999999);
        } while (User::query()->where('phone', $absent)->exists());

        $this->actingAs($this->business)->post('/business/trusted-partners', ['phone' => $absent])
            ->assertSessionHasErrors('phone');
    }

    public function test_a_vouched_customers_order_is_covered_without_wallet_or_guarantee_math(): void
    {
        $this->giveCustomerAGuarantee();
        $this->actingAs($this->business)->post('/business/trusted-partners', ['phone' => $this->customer->phone])->assertRedirect();

        $order = $this->checkout();

        $this->assertTrue((bool) $order->requires_deposit);
        $this->assertTrue((bool) $order->deposit_covered);
        $this->assertSame('vouched', $order->deposit_covered_by);

        // Covered means the business can accept it plainly, no explicit flag needed.
        Sanctum::actingAs($this->business);
        $this->postJson('/api/v2/business/orders/' . $order->id . '/accept')->assertSuccessful();
    }

    public function test_a_non_vouched_customer_still_needs_the_normal_check(): void
    {
        $order = $this->checkout();

        $this->assertTrue((bool) $order->requires_deposit);
        $this->assertFalse((bool) $order->deposit_covered);
    }

    public function test_revoking_the_vouch_restores_the_normal_check(): void
    {
        $this->giveCustomerAGuarantee();
        $this->actingAs($this->business)->post('/business/trusted-partners', ['phone' => $this->customer->phone])->assertRedirect();

        $partnerId = (int) TrustedPartner::query()
            ->where('business_id', $this->business->id)->where('user_id', $this->customer->id)->value('id');

        $this->actingAs($this->business)->put("/business/trusted-partners/{$partnerId}", ['is_active' => 0])->assertRedirect();

        $order = $this->checkout();

        $this->assertFalse((bool) $order->deposit_covered);
        $this->assertNotSame('vouched', $order->deposit_covered_by);
    }

    public function test_a_lapsed_guarantee_stops_honouring_a_standing_vouch(): void
    {
        $this->giveCustomerAGuarantee();
        $this->actingAs($this->business)->post('/business/trusted-partners', ['phone' => $this->customer->phone])->assertRedirect();

        // The vouch itself stays active, but the guarantee it depended on lapses.
        UserGuarantee::query()->where('user_id', $this->customer->id)->update(['status' => UserGuarantee::STATUS_CANCELLED]);

        $order = $this->checkout();

        $this->assertFalse((bool) $order->deposit_covered, 'a stale vouch must not survive the guarantee it was conditioned on');
    }

    public function test_another_businesss_owner_cannot_toggle_a_partner_that_is_not_theirs(): void
    {
        $this->giveCustomerAGuarantee();
        $this->actingAs($this->business)->post('/business/trusted-partners', ['phone' => $this->customer->phone])->assertRedirect();

        $otherOwner = User::query()->where('type', 'business')->where('id', '!=', $this->business->id)->first()
            ?: $this->markTestSkipped('Needs a second business.');
        $partnerId = (int) TrustedPartner::query()
            ->where('business_id', $this->business->id)->where('user_id', $this->customer->id)->value('id');

        $this->actingAs($otherOwner)->put("/business/trusted-partners/{$partnerId}", ['is_active' => 0])
            ->assertStatus(404);
    }

    public function test_vouching_for_yourself_is_refused(): void
    {
        // The business must already have a phone; use its own.
        $this->actingAs($this->business)->post('/business/trusted-partners', [
            'phone' => $this->business->phone,
        ])->assertSessionHasErrors('phone');
    }
}
