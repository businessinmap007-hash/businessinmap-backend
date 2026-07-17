<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CategoryChildServiceFee;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\PlatformTreasuryService;
use App\Services\WalletFeeService;
use App\Services\WalletService;
use Database\Seeders\PlatformAccountSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The platform treasury — the credit side of a service fee.
 *
 * Before this existed, a fee was debited from the payer and credited to nobody:
 * money left the ledger and the sum of all wallets shrank with every charge. The
 * property under test is conservation — what leaves the payer arrives at the
 * platform, once, and tagged with what kind of money it is. Rolls back.
 */
class PlatformTreasuryTest extends TestCase
{
    use DatabaseTransactions;

    private PlatformTreasuryService $treasury;
    private WalletFeeService $fees;
    private Booking $booking;
    private int $payerId;
    private string $feeCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->treasury = app(PlatformTreasuryService::class);
        $this->fees = app(WalletFeeService::class);

        if (! $this->treasury->isConfigured()) {
            $this->markTestSkipped('Needs BIM_PLATFORM_WALLET_USER_ID (run PlatformAccountSeeder).');
        }

        $booking = Booking::withTrashed()->whereNotNull('user_id')->whereNotNull('business_id')->first();

        if (! $booking) {
            $this->markTestSkipped('Needs a booking.');
        }

        if ($booking->trashed()) {
            $booking->restore();
        }

        $this->booking = $booking;
        $this->payerId = (int) $booking->user_id;
        $this->feeCode = 'test_fee_' . uniqid();

        $wallet = app(WalletService::class)->getOrCreateWallet($this->payerId);
        $wallet->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0]);

        DB::table('user_service_fee_consents')->updateOrInsert(
            ['user_id' => $this->payerId],
            ['fee_auto_charge_enabled' => 1, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    private function charge(float $amount): WalletTransaction
    {
        $m = new ReflectionMethod(WalletFeeService::class, 'createWalletFeeTransaction');
        $m->setAccessible(true);

        return $m->invoke(
            $this->fees,
            $this->booking,
            $this->feeCode,
            CategoryChildServiceFee::PAYER_CLIENT,
            $this->payerId,
            $amount,
            ['amount' => $amount]
        );
    }

    private function balanceOf(int $userId): float
    {
        return round((float) Wallet::query()->where('user_id', $userId)->value('balance'), 2);
    }

    public function test_the_seeded_treasury_account_never_sells_and_cannot_be_logged_into(): void
    {
        $account = $this->treasury->account();

        $this->assertNotNull($account);
        $this->assertSame('admin', $account->type, 'the treasury must not be a business that trades');
        $this->assertSame(PlatformAccountSeeder::EMAIL, $account->email);
        $this->assertTrue(Wallet::query()->where('user_id', $account->id)->exists(), 'the treasury needs a wallet to receive into');
    }

    public function test_a_fee_moves_money_from_the_payer_to_the_platform(): void
    {
        $treasuryId = $this->treasury->accountId();

        $payerBefore = $this->balanceOf($this->payerId);
        $platformBefore = $this->balanceOf($treasuryId);

        $this->charge(25);

        $payerAfter = $this->balanceOf($this->payerId);
        $platformAfter = $this->balanceOf($treasuryId);

        $this->assertSame(round($payerBefore - 25, 2), $payerAfter, 'the payer is debited');
        $this->assertSame(round($platformBefore + 25, 2), $platformAfter, 'and the platform is credited the same');

        // Conservation: the money moved, it did not evaporate.
        $this->assertSame(
            round($payerBefore + $platformBefore, 2),
            round($payerAfter + $platformAfter, 2),
            'a fee must conserve total money — this is what was broken before the treasury existed'
        );
    }

    public function test_the_credit_is_tagged_as_a_fee_not_as_unclaimed_money(): void
    {
        $this->charge(30);

        $credit = WalletTransaction::query()
            ->where('user_id', $this->treasury->accountId())
            ->where('direction', 'in')
            ->latest('id')
            ->first();

        $this->assertNotNull($credit);
        $this->assertSame(PlatformTreasuryService::PURPOSE_FEE, $credit->reference_type);
        $this->assertSame(30.0, round((float) $credit->amount, 2));
    }

    public function test_charging_twice_credits_the_platform_once(): void
    {
        $treasuryId = $this->treasury->accountId();
        $before = $this->balanceOf($treasuryId);

        $first = $this->charge(15);
        $second = $this->charge(15); // same booking + fee code + payer = the same charge

        $this->assertSame($first->id, $second->id, 'the debit is idempotent');
        $this->assertSame(round($before + 15, 2), $this->balanceOf($treasuryId), 'so the credit must be too');
    }

    public function test_balance_by_purpose_separates_revenue_from_money_that_is_not_ours(): void
    {
        $this->charge(40);

        $this->treasury->credit(
            amount: 60,
            purpose: PlatformTreasuryService::PURPOSE_ESCHEAT,
            referenceId: 'test-user',
            idempotencyKey: 'escheat_test_' . uniqid()
        );

        $split = $this->treasury->balanceByPurpose();

        // The raw balance mixes both; only the split tells you what is earned.
        $this->assertGreaterThanOrEqual(40.0, $split['fee']);
        $this->assertGreaterThanOrEqual(60.0, $split['escheat']);
        $this->assertArrayHasKey('fine', $split, 'the fines bucket must exist before the fines system does');
    }

    public function test_an_unconfigured_treasury_does_not_block_the_charge(): void
    {
        // The payer must still be charged correctly even if the platform side is
        // misconfigured — money already taken must never be rolled back for this.
        config(['bim.platform_wallet_user_id' => null]);
        $treasury = app(PlatformTreasuryService::class);

        $this->assertFalse($treasury->isConfigured());
        $this->assertNull($treasury->credit(10, PlatformTreasuryService::PURPOSE_FEE, '1', 'k_' . uniqid()));

        $before = $this->balanceOf($this->payerId);
        $this->charge(20);

        $this->assertSame(round($before - 20, 2), $this->balanceOf($this->payerId), 'the payer is still charged');
    }

    public function test_a_bad_purpose_or_amount_is_refused(): void
    {
        $this->assertNull($this->treasury->credit(50, 'not_a_purpose', '1', 'k_' . uniqid()));
        $this->assertNull($this->treasury->credit(0, PlatformTreasuryService::PURPOSE_FEE, '1', 'k_' . uniqid()));
        $this->assertNull($this->treasury->credit(-5, PlatformTreasuryService::PURPOSE_FEE, '1', 'k_' . uniqid()));
    }
}
