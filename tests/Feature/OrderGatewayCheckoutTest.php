<?php

namespace Tests\Feature;

use App\Models\BusinessCatalogListing;
use App\Models\MerchantPayment;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\MerchantPaymentAccountService;
use App\Services\Payments\PaymentSettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * Cart checkout wired to the gateway: choosing an online payment method opens a
 * MerchantPayment for the ORDER total (routed to the merchant's sub-account when
 * configured), while 'cash' stays pay-in-person. The callback settles the linked
 * payment. The amount always comes from the order, never the client.
 */
class OrderGatewayCheckoutTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRetailCatalog;

    private const PLATFORM_SEC = 'platform-sec-key';
    private const MERCHANT_SEC = 'merchant-sec-key';

    private User $customer;
    private int $businessId;
    private int $listingId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.fawry.merchant_code' => 'PLATFORM_MC',
            'services.fawry.security_key' => self::PLATFORM_SEC,
            'services.fawry.return_url' => 'https://example.com/return',
            'services.fawry.base_url' => 'https://atfawry.com',
            'services.fawry.currency' => 'EGP',
            'services.payments.default_gateway' => 'fawry',
        ]);

        $this->customer = User::query()->where('type', User::TYPE_CLIENT)->orderBy('id')->first()
            ?? User::query()->orderBy('id')->firstOrFail();
        $business = User::query()->where('type', 'business')->where('id', '!=', $this->customer->id)->orderBy('id')->first();
        if (! $business) {
            $this->markTestSkipped('Needs a business.');
        }
        $this->businessId = (int) $business->id;

        $product = $this->makeCatalogProduct('furniture');
        $this->listingId = BusinessCatalogListing::create([
            'business_id' => $this->businessId, 'catalog_product_id' => $product,
            'sku' => 'GW', 'price' => 30.00, 'currency' => 'EGP', 'stock' => 50, 'is_active' => 1,
        ])->id;
    }

    private function addItemAndCheckout(string $method): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $this->listingId, 'qty' => 2])->assertCreated();

        return $this->postJson("/api/v2/cart/{$this->businessId}/checkout", [
            'fulfillment_type' => 'pickup',
            'payment_method' => $method,
        ])->assertCreated();
    }

    public function test_cash_checkout_creates_no_gateway_payment(): void
    {
        $res = $this->addItemAndCheckout('cash');

        $res->assertJsonMissingPath('payment');
        $orderId = (int) $res->json('data.order.id');
        $this->assertSame(0, MerchantPayment::where('order_id', $orderId)->count());
    }

    public function test_online_checkout_opens_a_gateway_payment_for_the_order_total(): void
    {
        $res = $this->addItemAndCheckout('card');

        $orderId = (int) $res->json('data.order.id');
        $paymentId = (int) $res->json('data.merchant_payment_id');
        $this->assertNotEmpty($res->json('payment.charge_request.signature'));

        $payment = MerchantPayment::findOrFail($paymentId);
        $this->assertSame($orderId, (int) $payment->order_id);
        $this->assertSame($this->businessId, (int) $payment->business_id);
        $this->assertSame((int) $this->customer->id, (int) $payment->customer_id);

        // Amount is the order's total (2 × 30.00 = 60.00), sourced from the order.
        $orderTotal = round((float) Order::whereKey($orderId)->value('final_total'), 2);
        $this->assertSame($orderTotal, (float) $payment->amount);
        $this->assertSame(60.0, (float) $payment->amount);
    }

    public function test_online_checkout_routes_to_the_merchant_sub_account_when_configured(): void
    {
        app(PaymentSettingsService::class)->setSubMerchantEnabled(true);
        app(MerchantPaymentAccountService::class)->save($this->businessId, 'MERCH-CODE', self::MERCHANT_SEC, true);

        $res = $this->addItemAndCheckout('card');
        $payment = MerchantPayment::findOrFail((int) $res->json('data.merchant_payment_id'));

        $this->assertSame(MerchantPayment::ROUTED_MERCHANT, $payment->routed_to);
        $this->assertSame('MERCH-CODE', $payment->meta['charge_request']['merchantCode'] ?? null);

        // The callback (signed with the merchant key) settles the order's payment.
        $amt = number_format((float) $payment->amount, 2, '.', '');
        $ref = (string) $payment->id;
        $fawryRef = 'FW-' . $ref;
        $payload = [
            'merchantRefNumber' => $ref, 'fawryRefNumber' => $fawryRef,
            'paymentAmount' => $amt, 'orderAmount' => $amt, 'orderStatus' => 'PAID',
            'paymentMethod' => 'CARD', 'paymentReferenceNumber' => '',
        ];
        $payload['messageSignature'] = hash('sha256', $fawryRef . $ref . $amt . $amt . 'PAID' . 'CARD' . '' . self::MERCHANT_SEC);

        $this->postJson('/api/v2/merchant-payments/callback', $payload)->assertOk();
        $this->assertSame(MerchantPayment::STATUS_PAID, $payment->fresh()->status);
    }
}
