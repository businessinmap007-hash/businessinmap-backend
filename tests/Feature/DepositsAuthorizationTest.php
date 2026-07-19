<?php

namespace Tests\Feature;

use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Escrow authorization cover for the v2 deposits surface.
 *
 * The 2026-07-18 hole lived on `Api\V1\DepositController`, which had no policy
 * or ownership check — any signed-in user could move anyone's escrow and page
 * the whole ledger. v1 has since been deleted outright, so that hole is closed
 * by removal and its regression tests went with it. What remains under test is
 * the v2 surface, which is read-only and party-scoped by design.
 *
 * These tests create their own deposits and never touch the real rows.
 */
class DepositsAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type = 'client'): User
    {
        return User::query()->forceCreate([
            'name' => 'Test '.$type.' '.uniqid(),
            'phone' => '01'.random_int(100000000, 999999999),
            'email' => $type.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'api_token' => Str::random(60),
            'type' => $type,
        ]);
    }

    private function deposit(User $client, User $business): Deposit
    {
        return Deposit::create([
            'client_id' => $client->id,
            'business_id' => $business->id,
            'total_amount' => 100.00,
            'client_percent' => 50,
            'business_percent' => 50,
            'client_amount' => 50.00,
            'business_amount' => 50.00,
            'status' => DepositStatus::FROZEN,
            // Both are NOT NULL without a default. Every real deposit points
            // at a Booking; 0 keeps this row unattached to a real one.
            'target_type' => \App\Models\Booking::class,
            'target_id' => 0,
        ]);
    }

    // ──────────────────────────── v2 surface ────────────────────────────

    public function test_v2_lists_only_deposits_you_are_a_party_to(): void
    {
        $client = $this->user();
        $business = $this->user('business');

        $asClient = $this->deposit($client, $this->user('business'));
        $asBusiness = $this->deposit($this->user(), $business);
        $unrelated = $this->deposit($this->user(), $this->user('business'));

        $response = $this->actingAs($client, 'sanctum')->getJson('/api/v2/deposits?per_page=50');
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($asClient->id, $ids);
        $this->assertNotContains($unrelated->id, $ids);
        $this->assertNotContains($asBusiness->id, $ids);

        $businessSide = $this->actingAs($business, 'sanctum')->getJson('/api/v2/deposits?per_page=50');
        $this->assertContains($asBusiness->id, collect($businessSide->json('data'))->pluck('id')->all());
    }

    public function test_v2_reports_which_side_of_the_deposit_you_are_on(): void
    {
        $client = $this->user();
        $business = $this->user('business');
        $deposit = $this->deposit($client, $business);

        $asClient = $this->actingAs($client, 'sanctum')->getJson('/api/v2/deposits/'.$deposit->id);
        $asClient->assertOk();
        $this->assertSame('client', $asClient->json('data.my_role'));
        $this->assertEquals(50.0, $asClient->json('data.my_amount'));

        $asBusiness = $this->actingAs($business, 'sanctum')->getJson('/api/v2/deposits/'.$deposit->id);
        $this->assertSame('business', $asBusiness->json('data.my_role'));
    }

    public function test_v2_show_is_404_for_a_non_party(): void
    {
        $deposit = $this->deposit($this->user(), $this->user('business'));

        $this->actingAs($this->user(), 'sanctum')
            ->getJson('/api/v2/deposits/'.$deposit->id)
            ->assertNotFound();
    }

    public function test_v2_does_not_leak_internal_escrow_columns(): void
    {
        $client = $this->user();
        $deposit = $this->deposit($client, $this->user('business'));

        $response = $this->actingAs($client, 'sanctum')->getJson('/api/v2/deposits/'.$deposit->id);

        $response->assertOk();
        $payload = $response->json('data');

        foreach ([
            'client_wallet_transaction_id',
            'business_wallet_transaction_id',
            'external_proof_path',
            'external_verified_by',
            'policy_snapshot',
        ] as $leaky) {
            $this->assertArrayNotHasKey($leaky, $payload, "{$leaky} must not reach the app.");
        }
    }

    public function test_v2_deposits_require_authentication(): void
    {
        $this->getJson('/api/v2/deposits')->assertUnauthorized();
    }
}
