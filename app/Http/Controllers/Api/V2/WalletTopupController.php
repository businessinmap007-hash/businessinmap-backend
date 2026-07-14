<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\WalletTopupResource;
use App\Models\WalletTopup;
use App\Services\Payments\Dtos\CallbackResult;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Payments\WalletTopupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Wallet top-up (real money-in). The customer starts a top-up → we create a
 * `pending` intent and hand back the gateway's hosted-checkout payload. The
 * gateway later calls `callback` server-to-server; ONLY there do we credit the
 * points wallet (never on the customer's browser return). See
 * [[wallet-topup-payment-plan]].
 */
final class WalletTopupController extends Controller
{
    /** App-selectable payment methods (mapped to the gateway in the gateway). */
    private const METHODS = ['card', 'apple_pay', 'google_pay', 'fawry_cash', 'mobile_wallet', 'valu'];

    public function __construct(
        private readonly PaymentGatewayFactory $gateways,
        private readonly WalletTopupService $topups,
    ) {
    }

    /** POST /api/v2/wallet/topup — start a top-up, return the checkout payload. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'amount' => [
                'required', 'numeric',
                'min:' . config('services.payments.topup_min', 10),
                'max:' . config('services.payments.topup_max', 50000),
            ],
            'gateway' => ['nullable', 'string', 'max:40'],
            'payment_method' => ['nullable', 'string', 'in:' . implode(',', self::METHODS)],
        ]);

        $user = $request->user();
        $gateway = $this->gateways->make($data['gateway'] ?? null);
        $method = $data['payment_method'] ?? null;

        $topup = new WalletTopup([
            'user_id' => (int) $user->id,
            'gateway' => $gateway->name(),
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'currency' => (string) config('services.fawry.currency', 'EGP'),
            'status' => WalletTopup::STATUS_PENDING,
        ]);
        $topup->save();

        // merchant_ref must be unique across all charges forever — the PK is.
        $topup->merchant_ref = (string) $topup->id;
        $topup->save();

        $charge = $gateway->createCharge($topup, [
            'mobile' => (string) ($user->phone ?? ''),
            'email' => (string) ($user->email ?? ''),
            'name' => (string) ($user->name ?? ''),
        ], $method);

        $topup->update([
            'meta' => array_merge((array) $topup->meta, [
                'requested_method' => $method,
                'charge_request' => $charge->chargeRequest,
            ]),
        ]);

        return (new WalletTopupResource($topup))->additional([
            'success' => true,
            'payment' => $charge->toArray(),
        ]);
    }

    /** GET /api/v2/wallet/topup/{topup} — poll a top-up's status (own only). */
    public function show(Request $request, int $topup)
    {
        $model = WalletTopup::query()
            ->where('user_id', (int) $request->user()->id)
            ->findOrFail($topup);

        return (new WalletTopupResource($model))->additional(['success' => true]);
    }

    /**
     * POST /api/v2/wallet/topup/callback — gateway server-to-server notification.
     * Public (no auth): the gateway calls it. Security is the signature check.
     */
    public function callback(Request $request)
    {
        $payload = $request->all();
        $gateway = $this->gateways->make($request->query('gateway') ?: null);

        if (! $gateway->verifyCallbackSignature($payload)) {
            Log::warning('Wallet top-up callback: bad signature', ['payload' => $payload]);

            return response()->json(['success' => false, 'message' => 'invalid signature'], 400);
        }

        $result = $gateway->parseCallback($payload);

        $topup = WalletTopup::where('merchant_ref', $result->merchantRef)->first();
        if (! $topup) {
            Log::warning('Wallet top-up callback: unknown merchant_ref', ['ref' => $result->merchantRef]);

            return response()->json(['success' => false, 'message' => 'not found'], 404);
        }

        // Not a success signal → record terminal failure once, then ack.
        if (! $result->isPaid()) {
            if ($result->status === CallbackResult::STATUS_FAILED) {
                $this->topups->markFailed($topup, $result->gatewayRef, $result->method);
            }

            return response()->json(['success' => true]);
        }

        // Guard against a tampered/incorrect amount — credit our stored intent.
        if ($result->amount !== null && abs($result->amount - (float) $topup->amount) > 0.001) {
            Log::warning('Wallet top-up callback: amount mismatch', [
                'topup_id' => $topup->id,
                'expected' => (float) $topup->amount,
                'got' => $result->amount,
            ]);

            return response()->json(['success' => false, 'message' => 'amount mismatch'], 422);
        }

        $this->topups->markPaid($topup, $result->gatewayRef, $result->method);

        return response()->json(['success' => true]);
    }
}
