<?php

namespace App\Services\Payments;

use App\Models\MerchantPayment;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\Dtos\ChargeResult;
use Illuminate\Support\Facades\DB;

/**
 * Customer→merchant payment intents: creation (with per-merchant routing) and
 * settlement. Unlike WalletTopupService it credits NO platform wallet — the money
 * already settles into the merchant's own gateway account (or the platform's for
 * later payout). Settlement only flips the intent's status, idempotently and
 * under a row lock, so a replayed callback can't double-settle.
 */
class MerchantPaymentService
{
    public function __construct(private readonly PaymentGatewayFactory $gateways)
    {
    }

    /**
     * Create a pending payment intent and build its gateway charge, routed to the
     * merchant's sub-account when configured (else the platform account). Shared
     * by the standalone endpoint and the cart checkout.
     *
     * @return array{payment: MerchantPayment, charge: ChargeResult}
     */
    public function start(User $customer, User $business, float $amount, ?string $method = null, ?int $orderId = null): array
    {
        $merchantGateway = $this->gateways->makeForMerchant((int) $business->id);
        $gateway = $merchantGateway ?? $this->gateways->make();

        $payment = new MerchantPayment([
            'customer_id' => (int) $customer->id,
            'business_id' => (int) $business->id,
            'order_id' => $orderId,
            'gateway' => $gateway->name(),
            'routed_to' => $merchantGateway ? MerchantPayment::ROUTED_MERCHANT : MerchantPayment::ROUTED_PLATFORM,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => (string) config('services.fawry.currency', 'EGP'),
            'status' => MerchantPayment::STATUS_PENDING,
        ]);
        $payment->save();

        $payment->merchant_ref = (string) $payment->id;
        $payment->save();

        $charge = $gateway->createCharge($payment, [
            'mobile' => (string) ($customer->phone ?? ''),
            'email' => (string) ($customer->email ?? ''),
            'name' => (string) ($customer->name ?? ''),
        ], $method);

        $payment->update([
            'meta' => array_merge((array) $payment->meta, [
                'requested_method' => $method,
                'charge_request' => $charge->chargeRequest,
            ]),
        ]);

        return ['payment' => $payment, 'charge' => $charge];
    }

    /** Mark a payment paid. Idempotent + row-locked. */
    public function markPaid(MerchantPayment $payment, ?string $gatewayRef, ?string $method): MerchantPayment
    {
        return DB::transaction(function () use ($payment, $gatewayRef, $method) {
            /** @var MerchantPayment $locked */
            $locked = MerchantPayment::where('id', $payment->id)->lockForUpdate()->first();

            if ($locked->isPaid()) {
                return $locked; // already settled
            }

            $locked->update([
                'status' => MerchantPayment::STATUS_PAID,
                'gateway_ref' => $gatewayRef,
                'method' => $method,
                'paid_at' => now(),
            ]);

            // Mark the linked order paid (gateway-settled). Fulfillment status is
            // left alone — this only records that the money arrived.
            if ($locked->order_id) {
                Order::whereKey($locked->order_id)->update([
                    'payment_status' => Order::PAYMENT_PAID,
                    'paid_at' => now(),
                ]);
            }

            return $locked->fresh();
        });
    }

    /** Record a terminal failure on a still-pending payment. */
    public function markFailed(MerchantPayment $payment, ?string $gatewayRef = null, ?string $method = null): void
    {
        if ($payment->isPending()) {
            $payment->update([
                'status' => MerchantPayment::STATUS_FAILED,
                'gateway_ref' => $gatewayRef,
                'method' => $method,
            ]);
        }
    }
}
