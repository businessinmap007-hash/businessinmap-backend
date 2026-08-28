<?php

namespace Tests\Feature;

use App\Models\NotificationDeliveryLog;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 2026-08-28: wallet_deposit/wallet_withdraw were declared in
 * NotificationChannelRule but nothing ever dispatched them — a top-up or a
 * withdrawal changed the balance silently. WalletService::deposit()/withdraw()
 * are the single choke-point every caller (Fawry top-up settlement, the manual
 * deposit endpoint, PIN-gated withdrawal) already goes through, so the
 * notification is wired there once instead of at each call site.
 */
class WalletMoveNotificationTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::query()->where('type', 'client')->firstOrFail();
    }

    public function test_a_deposit_notifies_the_owner_on_realtime_and_firebase(): void
    {
        $tx = app(WalletService::class)->deposit($this->user->id, 150, 'test top-up');

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->user->id,
            'source_type' => WalletTransaction::class,
            'source_id' => $tx->id,
            'action_url' => '/wallet',
        ]);
        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $this->user->id,
            'event_key' => 'wallet_deposit',
            'channel' => NotificationDeliveryLog::CHANNEL_IN_APP,
        ]);
    }

    public function test_a_withdrawal_notifies_the_owner_and_is_attempted_on_firebase(): void
    {
        app(WalletService::class)->deposit($this->user->id, 500);

        $tx = app(WalletService::class)->withdraw($this->user->id, 200, 'test spend');

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->user->id,
            'source_type' => WalletTransaction::class,
            'source_id' => $tx->id,
        ]);
        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $this->user->id,
            'event_key' => 'wallet_withdraw',
            'channel' => NotificationDeliveryLog::CHANNEL_FIREBASE,
        ]);
    }

    public function test_the_notification_carries_the_amount_and_resulting_balance(): void
    {
        $tx = app(WalletService::class)->deposit($this->user->id, 75);

        $expectedBalance = number_format((float) $tx->balance_after, 2);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->user->id,
            'source_id' => $tx->id,
            'body_ar' => "تم إيداع 75.00 جنيه في محفظتك. الرصيد الحالي: {$expectedBalance} جنيه.",
        ]);
    }

    public function test_a_replayed_idempotent_deposit_is_not_notified_twice(): void
    {
        $key = 'wallet-move-notif-test:' . uniqid();

        $first = app(WalletService::class)->deposit($this->user->id, 40, null, 'manual', null, $key);
        $second = app(WalletService::class)->deposit($this->user->id, 40, null, 'manual', null, $key);

        $this->assertSame($first->id, $second->id, 'the idempotency guard must return the same ledger row');

        $this->assertSame(1, DB::table('app_notifications')
            ->where('user_id', $this->user->id)
            ->where('source_type', WalletTransaction::class)
            ->where('source_id', $first->id)
            ->count());
    }
}
