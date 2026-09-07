<?php

namespace Tests\Feature;

use App\Models\GuaranteeLevel;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Gap #1 coverage: the personal-guarantee API layer (levels/me/transactions/
 * check-coverage + activate/unlock guards). Money mechanics are already covered
 * by the service-level GuaranteeUnlockService/AutoUpgrade tests — here we assert
 * the HTTP surface: auth, target-type scoping, validation. Rolls back.
 */
class GuaranteeApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::query()->orderBy('id')->firstOrFail();
    }

    public function test_levels_are_scoped_to_requested_target_type(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v2/guarantees/levels?target_type=' . GuaranteeLevel::TARGET_CLIENT)
            ->assertOk()
            ->assertJsonPath('data.target_type', GuaranteeLevel::TARGET_CLIENT);

        foreach ($response->json('data.levels') as $level) {
            $this->assertSame(GuaranteeLevel::TARGET_CLIENT, $level['target_type']);
        }
    }

    public function test_me_returns_structured_payload(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v2/guarantees/me?target_type=' . GuaranteeLevel::TARGET_CLIENT)
            ->assertOk()
            ->assertJsonPath('data.target_type', GuaranteeLevel::TARGET_CLIENT)
            ->assertJsonStructure(['data' => ['target_type', 'guarantee', 'has_usable_guarantee']]);
    }

    public function test_transactions_are_paginated_and_scoped(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v2/guarantees/transactions?target_type=' . GuaranteeLevel::TARGET_CLIENT)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'target_type',
                    'transactions',
                    'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ]);
    }

    public function test_check_operation_coverage_validates_input(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/guarantees/check-operation', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'operation_type']);
    }

    public function test_check_operation_coverage_returns_a_decision(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/guarantees/check-operation', [
                'amount' => 25,
                'operation_type' => 'booking_deposit',
                'target_type' => GuaranteeLevel::TARGET_CLIENT,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data']);
    }

    public function test_activate_rejects_unknown_level_id(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/guarantees/activate', [
                'level_id' => 999999999,
                'target_type' => GuaranteeLevel::TARGET_CLIENT,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['level_id']);
    }

    public function test_guarantee_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v2/guarantees/me')->assertUnauthorized();
        $this->getJson('/api/v2/guarantees/levels')->assertUnauthorized();
        $this->postJson('/api/v2/guarantees/unlock')->assertUnauthorized();
    }

    // ---- wallet PIN gate (activate spends, unlock drops coverage) --------

    public function test_activate_requires_a_pin(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/guarantees/activate', [
                'target_type' => GuaranteeLevel::TARGET_CLIENT,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pin']);
    }

    public function test_activate_rejects_a_wrong_pin(): void
    {
        app(WalletService::class)->setPin((int) $this->user->id, '111111');

        $level = GuaranteeLevel::query()
            ->where('target_type', GuaranteeLevel::TARGET_CLIENT)
            ->where('is_active', 1)
            ->first();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/guarantees/activate', [
                'level_id' => $level?->id,
                'target_type' => GuaranteeLevel::TARGET_CLIENT,
                'pin' => '222222',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pin']);
    }

    public function test_unlock_requires_a_pin(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/guarantees/unlock', [
                'target_type' => GuaranteeLevel::TARGET_CLIENT,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pin']);
    }
}
