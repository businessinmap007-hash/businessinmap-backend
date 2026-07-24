<?php

namespace App\Console\Commands;

use App\Models\MerchantPayment;
use App\Services\Payments\Dtos\CallbackResult;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Payments\MerchantPaymentService;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Safety net for missed gateway callbacks on customer→merchant payments. Polls
 * each still-pending payment's status and settles it (idempotently) if the
 * gateway now reports it paid, or marks it failed. Mirrors
 * ReconcileWalletTopups, but rebuilds the SAME account (merchant vs platform)
 * that created the charge so the status call is signed with the right key.
 *
 * Schedule it (e.g. every 5 min) once Fawry credentials are configured — no-op
 * until then.
 */
class ReconcileMerchantPayments extends Command
{
    protected $signature = 'payments:reconcile-merchant {--minutes=15 : Only poll intents at least this old} {--limit=200}';

    protected $description = 'Poll pending customer→merchant payments against the gateway and settle any that were paid.';

    public function handle(PaymentGatewayFactory $gateways, MerchantPaymentService $payments): int
    {
        $cutoff = now()->subMinutes((int) $this->option('minutes'));

        $pending = MerchantPayment::query()
            ->where('status', MerchantPayment::STATUS_PENDING)
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        $paid = 0;
        $failed = 0;

        foreach ($pending as $payment) {
            $gateway = $this->gatewayFor($gateways, $payment);
            if (! $gateway) {
                continue; // routed account no longer resolvable / not configured
            }

            $result = $gateway->fetchStatus($payment);
            if (! $result) {
                continue; // not configured / unreachable
            }

            if ($result->isPaid()) {
                if ($result->amount !== null && abs($result->amount - (float) $payment->amount) > 0.001) {
                    Log::warning('Reconcile merchant payment: amount mismatch, skipping.', [
                        'payment_id' => $payment->id, 'expected' => (float) $payment->amount, 'got' => $result->amount,
                    ]);
                    continue;
                }
                $payments->markPaid($payment, $result->gatewayRef, $result->method);
                $paid++;
            } elseif ($result->status === CallbackResult::STATUS_FAILED) {
                $payments->markFailed($payment, $result->gatewayRef, $result->method);
                $failed++;
            }
        }

        $this->info("Reconciled {$pending->count()} pending merchant payment(s): {$paid} paid, {$failed} failed.");

        return self::SUCCESS;
    }

    /** The gateway that created this payment's charge (merchant or platform key). */
    private function gatewayFor(PaymentGatewayFactory $gateways, MerchantPayment $payment): ?PaymentGatewayInterface
    {
        if ($payment->routed_to === MerchantPayment::ROUTED_MERCHANT) {
            return $gateways->makeForMerchant((int) $payment->business_id);
        }

        try {
            return $gateways->make($payment->gateway);
        } catch (\Throwable) {
            return null;
        }
    }
}
