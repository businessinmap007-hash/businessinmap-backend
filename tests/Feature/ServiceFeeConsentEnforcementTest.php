<?php

namespace Tests\Feature;

use App\Models\GuaranteeLevel;
use App\Models\User;
use App\Models\UserServiceFeeConsent;
use App\Models\Wallet;
use App\Services\DepositsEscrowService;
use App\Services\ServiceFeeConsentEnforcer;
use App\Services\UserGuaranteeService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The anti-evasion rule: buying a guarantee (any level) or posting a deposit
 * forces the user into the fee + rating programme, so trust instruments can't
 * be used to dodge service fees. Everything rolls back (DatabaseTransactions).
 */
class ServiceFeeConsentEnforcementTest extends TestCase
{
    use DatabaseTransactions;

    private ServiceFeeConsentEnforcer $enforcer;

    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enforcer = app(ServiceFeeConsentEnforcer::class);
        $this->wallet = app(WalletService::class);
    }

    /** Reset a user to "outside the programme" so the enforcement is observable. */
    private function disableConsent(User $user): void
    {
        UserServiceFeeConsent::updateOrCreate(
            ['user_id' => $user->id],
            ['fee_auto_charge_enabled' => false, 'rating_enabled' => false, 'stats_enabled' => false, 'enabled_at' => null]
        );
        $user->forceFill(['rating_enabled' => false])->save();
    }

    public function test_enforce_enables_fee_and_rating_idempotently(): void
    {
        $user = User::query()->orderBy('id')->firstOrFail();
        $this->disableConsent($user);

        $this->enforcer->enforce($user, 'test');
        $this->enforcer->enforce($user, 'test again'); // idempotent

        $rows = UserServiceFeeConsent::where('user_id', $user->id)->get();
        $this->assertCount(1, $rows, 'exactly one consent row');

        $consent = $rows->first();
        $this->assertTrue((bool) $consent->fee_auto_charge_enabled, 'fee charging forced on');
        $this->assertTrue((bool) $consent->rating_enabled, 'rating forced on');
        $this->assertNotNull($consent->enabled_at, 'enabled_at stamped');
        $this->assertTrue((bool) $user->fresh()->rating_enabled, 'users.rating_enabled synced');
    }

    public function test_guarantee_subscription_forces_consent(): void
    {
        $user = User::query()->where('type', '!=', 'business')->orderBy('id')->first();
        $level = GuaranteeLevel::query()->where('target_type', GuaranteeLevel::TARGET_CLIENT)->orderBy('id')->first();

        if (! $user || ! $level) {
            $this->markTestSkipped('Needs a client user and a client guarantee level.');
        }

        $this->disableConsent($user);

        // Fund the wallet so the guarantee lock succeeds.
        $w = $this->wallet->getOrCreateWallet((int) $user->id);
        $w->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => (float) $level->required_locked_amount + 100, 'locked_balance' => 0]);

        app(UserGuaranteeService::class)->subscribe($user, $level);

        $consent = UserServiceFeeConsent::where('user_id', $user->id)->first();
        $this->assertNotNull($consent);
        $this->assertTrue((bool) $consent->fee_auto_charge_enabled, 'guarantee forced fee charging on');
        $this->assertTrue((bool) $consent->rating_enabled, 'guarantee forced rating on');
    }

    public function test_deposit_creation_forces_client_consent(): void
    {
        $client = User::query()->where('type', '!=', 'business')->orderBy('id')->first();
        $business = User::query()->where('id', '!=', optional($client)->id)->orderBy('id')->first();

        if (! $client || ! $business) {
            $this->markTestSkipped('Needs two distinct users.');
        }

        $this->disableConsent($client);

        $w = $this->wallet->getOrCreateWallet((int) $client->id);
        $w->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 500, 'locked_balance' => 0]);

        app(DepositsEscrowService::class)->create(
            clientId: (int) $client->id,
            businessId: (int) $business->id,
            totalAmount: 0,
            targetType: 'test_consent',
            targetId: random_int(2_000_000, 9_000_000),
            clientAmount: 25.0,
            businessAmount: 0.0,
        );

        $consent = UserServiceFeeConsent::where('user_id', $client->id)->first();
        $this->assertNotNull($consent);
        $this->assertTrue((bool) $consent->fee_auto_charge_enabled, 'deposit forced fee charging on');
        $this->assertTrue((bool) $consent->rating_enabled, 'deposit forced rating on');
    }
}
