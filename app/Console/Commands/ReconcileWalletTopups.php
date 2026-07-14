<?php

namespace App\Console\Commands;

use App\Models\WalletTopup;
use App\Services\Payments\Dtos\CallbackResult;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Payments\WalletTopupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Safety net for missed gateway callbacks: polls each still-pending top-up's
 * status and settles it (idempotently) if the gateway now reports it paid, or
 * marks it failed. Mirrors the old v1 `checkFawryOrders`, gateway-agnostic and
 * routed through the same WalletTopupService the callback uses.
 *
 * Schedule it (e.g. every 5 min) once Fawry credentials are configured.
 */
class ReconcileWalletTopups extends Command
{
    protected $signature = 'wallet:reconcile-topups {--minutes=15 : Only poll intents at least this old} {--limit=200}';

    protected $description = 'Poll pending wallet top-ups against the gateway and settle any that were paid.';

    public function handle(PaymentGatewayFactory $gateways, WalletTopupService $topups): int
    {
        $cutoff = now()->subMinutes((int) $this->option('minutes'));

        $pending = WalletTopup::query()
            ->where('status', WalletTopup::STATUS_PENDING)
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        $paid = 0;
        $failed = 0;

        foreach ($pending as $topup) {
            try {
                $gateway = $gateways->make($topup->gateway);
            } catch (\Throwable $e) {
                continue; // unknown gateway — skip
            }

            $result = $gateway->fetchStatus($topup);
            if (! $result) {
                continue; // not configured / unreachable
            }

            if ($result->isPaid()) {
                if ($result->amount !== null && abs($result->amount - (float) $topup->amount) > 0.001) {
                    Log::warning('Reconcile: amount mismatch, skipping.', [
                        'topup_id' => $topup->id, 'expected' => (float) $topup->amount, 'got' => $result->amount,
                    ]);
                    continue;
                }
                $topups->markPaid($topup, $result->gatewayRef, $result->method);
                $paid++;
            } elseif ($result->status === CallbackResult::STATUS_FAILED) {
                $topups->markFailed($topup, $result->gatewayRef, $result->method);
                $failed++;
            }
        }

        $this->info("Reconciled {$pending->count()} pending top-up(s): {$paid} paid, {$failed} failed.");

        return self::SUCCESS;
    }
}
