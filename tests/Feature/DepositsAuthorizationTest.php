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
 * Regression cover for the escrow authorization hole found on 2026-07-18.
 *
 * Every method on `Api\V1\DepositController` sat behind `auth:sanctum` and
 * nothing else — no policy, no ownership check. Any signed-in user could
 * freeze money in two arbitrary wallets, release or refund somebody else's
 * escrow (choosing which party got paid), charge an execution fee on it, and
 * page through the platform's entire escrow ledger.
 *
 * These tests create their own deposits and never touch the real rows; nothing
 * here calls a money-moving endpoint with a payload that could succeed.
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

    private function admin(): User
    {
        $admin = User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin account to act as.');
        }

        return $admin;
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

    // ───────────────────── v1: the writes are shut ─────────────────────

    public function test_a_stranger_cannot_create_escrow_naming_other_people(): void
    {
        $stranger = $this->user();
        $client = $this->user();
        $business = $this->user('business');

        $before = Deposit::count();

        $this->actingAs($stranger, 'sanctum')->postJson('/api/v1/deposits', [
            'client_id' => $client->id,
            'business_id' => $business->id,
            'total_amount' => 500,
        ])->assertForbidden();

        $this->assertSame($before, Deposit::count(), 'No escrow may be created by a stranger.');
    }

    public function test_a_stranger_cannot_release_refund_or_charge_someone_elses_deposit(): void
    {
        $deposit = $this->deposit($this->user(), $this->user('business'));
        $stranger = $this->user();

        $this->actingAs($stranger, 'sanctum')
            ->postJson('/api/v1/deposits/'.$deposit->id.'/release')
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->postJson('/api/v1/deposits/'.$deposit->id.'/refund', [
                'refund_client' => true, 'refund_business' => false,
            ])->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->postJson('/api/v1/deposits/'.$deposit->id.'/start-execution')
            ->assertForbidden();

        $this->assertSame(
            DepositStatus::FROZEN,
            $deposit->fresh()->status,
            'The deposit must be untouched.'
        );
    }

    public function test_even_a_party_cannot_drive_escrow_directly(): void
    {
        // Release/refund belong to BookingDepositService and DisputeService,
        // which know why the money is moving. Being a party is not a licence
        // to release your own escrow on demand.
        $client = $this->user();
        $business = $this->user('business');
        $deposit = $this->deposit($client, $business);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/deposits/'.$deposit->id.'/release')
            ->assertForbidden();

        $this->actingAs($business, 'sanctum')
            ->postJson('/api/v1/deposits/'.$deposit->id.'/release')
            ->assertForbidden();

        $this->assertSame(DepositStatus::FROZEN, $deposit->fresh()->status);
    }

    // ───────────────────── v1: the reads are scoped ─────────────────────

    public function test_a_stranger_gets_404_not_403_on_someone_elses_deposit(): void
    {
        $deposit = $this->deposit($this->user(), $this->user('business'));

        // 403 would confirm the id exists.
        $this->actingAs($this->user(), 'sanctum')
            ->getJson('/api/v1/deposits/'.$deposit->id)
            ->assertNotFound();
    }

    public function test_both_parties_can_read_their_own_deposit(): void
    {
        $client = $this->user();
        $business = $this->user('business');
        $deposit = $this->deposit($client, $business);

        $this->actingAs($client, 'sanctum')->getJson('/api/v1/deposits/'.$deposit->id)->assertOk();
        $this->actingAs($business, 'sanctum')->getJson('/api/v1/deposits/'.$deposit->id)->assertOk();
    }

    public function test_the_ledger_can_no_longer_be_enumerated(): void
    {
        $mine = $this->deposit($caller = $this->user(), $this->user('business'));
        $theirs = $this->deposit($this->user(), $this->user('business'));

        $response = $this->actingAs($caller, 'sanctum')->getJson('/api/v1/deposits');

        $response->assertOk();
        $ids = collect($response->json('deposits.data'))->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_filtering_by_another_users_id_does_not_widen_the_scope(): void
    {
        $victimClient = $this->user();
        $theirs = $this->deposit($victimClient, $this->user('business'));
        $caller = $this->user();

        $response = $this->actingAs($caller, 'sanctum')
            ->getJson('/api/v1/deposits?client_id='.$victimClient->id);

        $response->assertOk();
        $ids = collect($response->json('deposits.data'))->pluck('id')->all();

        $this->assertNotContains($theirs->id, $ids, 'A filter must never widen visibility.');
    }

    public function test_an_admin_can_still_see_across_parties(): void
    {
        $deposit = $this->deposit($this->user(), $this->user('business'));

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/deposits/'.$deposit->id)
            ->assertOk();
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
