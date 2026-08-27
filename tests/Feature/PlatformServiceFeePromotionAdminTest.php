<?php

namespace Tests\Feature;

use App\Models\PlatformServiceFeePromotion;
use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Admin CRUD for platform-wide fee promotions. The toggle route is POST
 * (no PATCH registered) — the index view's form spoofed @method('PATCH')
 * against it, which 405'd every click. Regression cover for that mismatch.
 */
class PlatformServiceFeePromotionAdminTest extends TestCase
{
    use DatabaseTransactions;

    private function feesAdmin(): User
    {
        $admin = User::query()->where('type', User::TYPE_ADMIN)->firstOrFail();
        foreach ([AdminAbility::ACCESS, AdminAbility::FEES] as $ability) {
            \Bouncer::allow($admin)->to($ability);
        }
        \Bouncer::refresh();

        return $admin;
    }

    private function makePromotion(): PlatformServiceFeePromotion
    {
        return PlatformServiceFeePromotion::create([
            'scope_type' => PlatformServiceFeePromotion::SCOPE_ALL_SERVICES,
            'name' => 'عرض اختبار',
            'target_party' => PlatformServiceFeePromotion::TARGET_BOTH,
            'discount_type' => PlatformServiceFeePromotion::DISCOUNT_WAIVE,
            'discount_value' => 0,
            'is_active' => 0,
            'priority' => 0,
        ]);
    }

    public function test_the_toggle_route_only_accepts_post(): void
    {
        $promotion = $this->makePromotion();

        $this->actingAs($this->feesAdmin())
            ->post(route('admin.platform-service-fee-promotions.toggle', $promotion, false))
            ->assertRedirect();

        $this->assertTrue((bool) $promotion->fresh()->is_active);
    }

    public function test_the_index_screen_toggle_button_actually_works(): void
    {
        $promotion = $this->makePromotion();

        $this->actingAs($this->feesAdmin())
            ->get(route('admin.platform-service-fee-promotions.index', [], false))
            ->assertOk()
            ->assertSee(route('admin.platform-service-fee-promotions.toggle', $promotion, false));
    }
}
