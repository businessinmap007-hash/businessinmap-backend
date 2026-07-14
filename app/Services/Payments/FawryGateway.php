<?php

namespace App\Services\Payments;

use App\Models\WalletTopup;
use App\Services\Payments\Dtos\CallbackResult;
use App\Services\Payments\Dtos\ChargeResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fawry (Egypt) hosted-checkout gateway. Cleaned port of the logic that lived
 * in app/Libraries/Main.php (fawryPayment/signRequest) — credentials now come
 * from config('services.fawry'), never hard-coded. Card / Apple Pay / Fawry
 * cash all ride on the same hosted checkout.
 *
 * NOTE: the callback signature scheme below follows Fawry's ServerToServer
 * notification (v2) docs. Fawry has shipped more than one scheme over time, so
 * before go-live confirm the exact field order for your merchant product.
 */
final class FawryGateway implements PaymentGatewayInterface
{
    /** @param array<string,mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function name(): string
    {
        return 'fawry';
    }

    public function createCharge(WalletTopup $topup, array $customer = [], ?string $method = null): ChargeResult
    {
        $charge = [
            'merchantCode' => (string) ($this->config['merchant_code'] ?? ''),
            'merchantRefNum' => (string) $topup->merchant_ref,
            'customerMobile' => (string) ($customer['mobile'] ?? ''),
            'customerEmail' => (string) ($customer['email'] ?? ''),
            'customerProfileId' => (string) $topup->user_id,
            'customerName' => (string) ($customer['name'] ?? ''),
            'chargeItems' => [[
                'itemId' => 'WT-' . $topup->id,
                'description' => 'BIM wallet top-up',
                'price' => $this->money($topup->amount),
                'quantity' => 1,
            ]],
            'returnUrl' => (string) ($this->config['return_url'] ?? ''),
            'authCaptureModePayment' => false,
        ];

        // Force a specific Fawry payment method when the app asked for one that
        // maps to a distinct rail; card / Apple Pay / Google Pay are left to the
        // hosted page (which presents them from the card rails when enabled).
        $fawryMethod = $this->mapMethod($method);
        if ($fawryMethod !== null) {
            $charge['paymentMethod'] = $fawryMethod;
        }

        $charge['signature'] = $this->signCharge($charge);

        return new ChargeResult('fawry', (string) $topup->merchant_ref, $this->initUrl(), $charge);
    }

    /**
     * Poll Fawry's payment-status API for a top-up. Signature is
     * sha256(merchantCode + merchantRefNum + secureKey). Returns null when
     * credentials are missing or the request fails.
     */
    public function fetchStatus(WalletTopup $topup): ?CallbackResult
    {
        if ($this->securityKey() === '' || (string) ($this->config['merchant_code'] ?? '') === '') {
            return null;
        }

        $merchantCode = (string) $this->config['merchant_code'];
        $ref = (string) $topup->merchant_ref;
        $signature = hash('sha256', $merchantCode . $ref . $this->securityKey());

        try {
            $base = rtrim((string) ($this->config['base_url'] ?? 'https://atfawry.com'), '/');
            $response = Http::timeout(15)->acceptJson()->get(
                $base . '/ECommerceWeb/Fawry/payments/status/v2',
                ['merchantCode' => $merchantCode, 'merchantRefNumber' => $ref, 'signature' => $signature]
            );

            if (! $response->ok()) {
                return null;
            }

            return $this->parseCallback((array) $response->json());
        } catch (\Throwable $e) {
            Log::warning('Fawry status poll failed.', ['ref' => $ref, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** App-level method → Fawry `paymentMethod`, or null to leave it to the page. */
    private function mapMethod(?string $method): ?string
    {
        return match ($method) {
            'fawry_cash' => 'PayAtFawry',
            'mobile_wallet' => 'MWALLET',
            'valu' => 'VALU',
            default => null, // card / apple_pay / google_pay / null → hosted page
        };
    }

    public function verifyCallbackSignature(array $payload): bool
    {
        $provided = (string) ($payload['messageSignature'] ?? $payload['signature'] ?? '');
        if ($provided === '' || $this->securityKey() === '') {
            return false;
        }

        return hash_equals($this->callbackSignature($payload), strtolower($provided));
    }

    public function parseCallback(array $payload): CallbackResult
    {
        $orderStatus = strtoupper((string) ($payload['orderStatus'] ?? ''));

        $status = match ($orderStatus) {
            'PAID' => CallbackResult::STATUS_PAID,
            'FAILED', 'EXPIRED', 'CANCELED', 'CANCELLED', 'REFUNDED' => CallbackResult::STATUS_FAILED,
            'NEW', 'UNPAID' => CallbackResult::STATUS_PENDING,
            default => CallbackResult::STATUS_UNKNOWN,
        };

        $amount = isset($payload['paymentAmount']) ? (float) $payload['paymentAmount'] : null;

        return new CallbackResult(
            merchantRef: (string) ($payload['merchantRefNumber'] ?? $payload['merchantRefNum'] ?? ''),
            gatewayRef: isset($payload['fawryRefNumber'])
                ? (string) $payload['fawryRefNumber']
                : (isset($payload['referenceNumber']) ? (string) $payload['referenceNumber'] : null),
            status: $status,
            amount: $amount,
            method: isset($payload['paymentMethod']) ? (string) $payload['paymentMethod'] : null,
            raw: $payload,
        );
    }

    // ─────────────────────────── internals ───────────────────────────

    /**
     * Charge signature: sha256 of
     * merchantCode + merchantRefNum + customerProfileId + returnUrl +
     * itemId + quantity + price(2dp) + secureKey.
     */
    private function signCharge(array $c): string
    {
        $item = $c['chargeItems'][0];

        $s = $c['merchantCode']
            . $c['merchantRefNum']
            . ($c['customerProfileId'] ?? '')
            . ($c['returnUrl'] ?? '')
            . $item['itemId']
            . $item['quantity']
            . $this->money($item['price'])
            . $this->securityKey();

        return hash('sha256', $s);
    }

    /**
     * Callback signature: sha256 of
     * fawryRefNumber + merchantRefNumber + paymentAmount(2dp) +
     * orderAmount(2dp) + orderStatus + paymentMethod +
     * paymentReferenceNumber('' if none) + secureKey.
     */
    private function callbackSignature(array $p): string
    {
        $s = (string) ($p['fawryRefNumber'] ?? $p['referenceNumber'] ?? '')
            . (string) ($p['merchantRefNumber'] ?? $p['merchantRefNum'] ?? '')
            . $this->money($p['paymentAmount'] ?? 0)
            . $this->money($p['orderAmount'] ?? 0)
            . (string) ($p['orderStatus'] ?? '')
            . (string) ($p['paymentMethod'] ?? '')
            . (string) ($p['paymentReferenceNumber'] ?? '')
            . $this->securityKey();

        return hash('sha256', $s);
    }

    private function initUrl(): string
    {
        $base = rtrim((string) ($this->config['base_url'] ?? 'https://atfawry.com'), '/');

        return $base . '/fawrypay-api/api/payments/init';
    }

    private function securityKey(): string
    {
        return (string) ($this->config['security_key'] ?? '');
    }

    /** Fawry wants amounts as fixed 2-decimal strings. */
    private function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
