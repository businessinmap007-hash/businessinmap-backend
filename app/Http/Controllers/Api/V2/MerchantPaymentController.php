<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\MerchantPayment;
use App\Models\User;
use App\Services\Payments\Dtos\CallbackResult;
use App\Services\Payments\MerchantPaymentService;
use App\Services\Payments\PaymentGatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Customer→merchant payment (real money-in that settles to the MERCHANT). The
 * customer starts a payment for a business → we create a `pending` intent and,
 * when sub-account routing is on and the merchant is configured, build the
 * gateway charge on the MERCHANT's Fawry account (routed_to = merchant),
 * otherwise the platform account. The gateway later calls `callback`
 * server-to-server; ONLY there is the intent marked paid — and NO platform
 * wallet is credited (the money is the merchant's). See [[fawry-submerchant-routing]].
 */
final class MerchantPaymentController extends Controller
{
    private const METHODS = ['card', 'apple_pay', 'google_pay', 'fawry_cash', 'mobile_wallet', 'valu'];

    public function __construct(
        private readonly PaymentGatewayFactory $gateways,
        private readonly MerchantPaymentService $payments,
    ) {
    }

    /** POST /api/v2/merchant-payments — start a payment to a merchant. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => [
                'required', 'numeric',
                'min:' . config('services.payments.topup_min', 10),
                'max:' . config('services.payments.topup_max', 50000),
            ],
            'payment_method' => ['nullable', 'string', 'in:' . implode(',', self::METHODS)],
        ]);

        $customer = $request->user();
        $businessId = (int) $data['business_id'];

        $business = User::query()->find($businessId);
        if (! $business || ! $business->isBusiness()) {
            throw ValidationException::withMessages(['business_id' => [__('الحساب المحدّد ليس حساب تاجر.')]]);
        }
        if ($businessId === (int) $customer->id) {
            throw ValidationException::withMessages(['business_id' => [__('لا يمكنك الدفع لنفسك.')]]);
        }

        // Route to the merchant's sub-account when configured, else the platform.
        $merchantGateway = $this->gateways->makeForMerchant($businessId);
        $gateway = $merchantGateway ?? $this->gateways->make();
        $routedTo = $merchantGateway ? MerchantPayment::ROUTED_MERCHANT : MerchantPayment::ROUTED_PLATFORM;
        $method = $data['payment_method'] ?? null;

        $payment = new MerchantPayment([
            'customer_id' => (int) $customer->id,
            'business_id' => $businessId,
            'gateway' => $gateway->name(),
            'routed_to' => $routedTo,
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
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

        return response()->json([
            'success' => true,
            'data' => $this->present($payment),
            'payment' => $charge->toArray(),
        ], 201);
    }

    /** GET /api/v2/merchant-payments/{payment} — poll status (own only). */
    public function show(Request $request, int $payment)
    {
        $model = MerchantPayment::query()
            ->where('customer_id', (int) $request->user()->id)
            ->findOrFail($payment);

        return response()->json(['success' => true, 'data' => $this->present($model)]);
    }

    /**
     * POST /api/v2/merchant-payments/callback — gateway server-to-server
     * notification. Public: security is the signature check, verified with the
     * SAME account (merchant or platform) that created the charge.
     */
    public function callback(Request $request)
    {
        $payload = $request->all();

        $result = null;
        // Resolve the intent first (by merchant_ref) so we know which key to verify with.
        $ref = (string) ($payload['merchantRefNumber'] ?? $payload['merchantRefNum'] ?? '');
        $payment = $ref !== '' ? MerchantPayment::where('merchant_ref', $ref)->first() : null;

        if (! $payment) {
            Log::warning('Merchant payment callback: unknown merchant_ref', ['ref' => $ref]);

            return response()->json(['success' => false, 'message' => 'not found'], 404);
        }

        $gateway = $this->gatewayFor($payment);
        if ($gateway === null || ! $gateway->verifyCallbackSignature($payload)) {
            Log::warning('Merchant payment callback: bad signature', ['ref' => $ref]);

            return response()->json(['success' => false, 'message' => 'invalid signature'], 400);
        }

        $result = $gateway->parseCallback($payload);

        if (! $result->isPaid()) {
            if ($result->status === CallbackResult::STATUS_FAILED) {
                $this->payments->markFailed($payment, $result->gatewayRef, $result->method);
            }

            return response()->json(['success' => true]);
        }

        if ($result->amount !== null && abs($result->amount - (float) $payment->amount) > 0.001) {
            Log::warning('Merchant payment callback: amount mismatch', [
                'payment_id' => $payment->id,
                'expected' => (float) $payment->amount,
                'got' => $result->amount,
            ]);

            return response()->json(['success' => false, 'message' => 'amount mismatch'], 422);
        }

        $this->payments->markPaid($payment, $result->gatewayRef, $result->method);

        return response()->json(['success' => true]);
    }

    /** Rebuild the gateway that created this payment's charge (right key). */
    private function gatewayFor(MerchantPayment $payment): ?\App\Services\Payments\PaymentGatewayInterface
    {
        if ($payment->routed_to === MerchantPayment::ROUTED_MERCHANT) {
            // Must verify with the merchant's key; if it's no longer resolvable,
            // refuse rather than silently verify with the wrong (platform) key.
            return $this->gateways->makeForMerchant((int) $payment->business_id);
        }

        return $this->gateways->make();
    }

    /** @return array<string,mixed> */
    private function present(MerchantPayment $p): array
    {
        return [
            'id' => (int) $p->id,
            'business_id' => (int) $p->business_id,
            'amount' => (string) $p->amount,
            'currency' => $p->currency,
            'status' => $p->status,
            'routed_to' => $p->routed_to,
            'merchant_ref' => $p->merchant_ref,
            'paid_at' => optional($p->paid_at)->toIso8601String(),
        ];
    }
}
