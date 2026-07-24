<?php

namespace Tests\Feature;

use App\Models\MerchantAccountRequest;
use App\Models\User;
use App\Services\Payments\MerchantPaymentAccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * A business applies for a Fawry merchant sub-account; an admin reviews and, on
 * approval, provisions the credentials (which activates sub-account routing for
 * that merchant). Covers the business API guards + the admin decisions.
 */
class MerchantAccountRequestTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = User::query()->where('type', User::TYPE_BUSINESS)->orderBy('id')->firstOrFail();
        // Clean slate for this business.
        MerchantAccountRequest::where('business_id', $this->business->id)->delete();
        \App\Models\MerchantPaymentAccount::where('business_id', $this->business->id)->delete();
    }

    public function test_a_business_can_apply_and_see_pending_status(): void
    {
        $this->actingAs($this->business, 'sanctum')
            ->postJson('/api/v2/merchant-account/request', ['note' => 'أرجو تفعيل حسابي'])
            ->assertCreated()
            ->assertJsonPath('data.status', MerchantAccountRequest::STATUS_PENDING);

        $this->actingAs($this->business, 'sanctum')
            ->getJson('/api/v2/merchant-account')
            ->assertOk()
            ->assertJsonPath('data.has_account', false)
            ->assertJsonPath('data.pending_request', true)
            ->assertJsonPath('data.request_status', MerchantAccountRequest::STATUS_PENDING);
    }

    public function test_a_client_cannot_apply(): void
    {
        $client = User::query()->where('type', User::TYPE_CLIENT)->orderBy('id')->firstOrFail();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v2/merchant-account/request')
            ->assertStatus(422);
    }

    public function test_cannot_apply_twice_while_pending(): void
    {
        $this->actingAs($this->business, 'sanctum')->postJson('/api/v2/merchant-account/request')->assertCreated();
        $this->actingAs($this->business, 'sanctum')->postJson('/api/v2/merchant-account/request')->assertStatus(422);
    }

    public function test_cannot_apply_when_already_configured(): void
    {
        app(MerchantPaymentAccountService::class)->save($this->business->id, 'HAVE-CODE', 'have-key', true);

        $this->actingAs($this->business, 'sanctum')
            ->postJson('/api/v2/merchant-account/request')
            ->assertStatus(422);
    }

    public function test_admin_approval_provisions_the_account(): void
    {
        $admin = User::query()->where('type', 'admin')->orderBy('id')->first();
        if (! $admin) {
            $this->markTestSkipped('Needs an admin user.');
        }

        $req = MerchantAccountRequest::create([
            'business_id' => $this->business->id,
            'status' => MerchantAccountRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->post("/admin/merchant-account-requests/{$req->id}/approve", [
                'merchant_code' => 'APPROVED-MC',
                'security_key' => 'approved-key',
            ])->assertRedirect(route('admin.merchant-account-requests.index'));

        $this->assertSame(MerchantAccountRequest::STATUS_APPROVED, $req->fresh()->status);
        $this->assertTrue(app(MerchantPaymentAccountService::class)->isConfigured($this->business->id));
    }

    public function test_admin_rejection_records_the_decision(): void
    {
        $admin = User::query()->where('type', 'admin')->orderBy('id')->first();
        if (! $admin) {
            $this->markTestSkipped('Needs an admin user.');
        }

        $req = MerchantAccountRequest::create([
            'business_id' => $this->business->id,
            'status' => MerchantAccountRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->post("/admin/merchant-account-requests/{$req->id}/reject", ['decision_note' => 'ناقص مستندات'])
            ->assertRedirect(route('admin.merchant-account-requests.index'));

        $this->assertSame(MerchantAccountRequest::STATUS_REJECTED, $req->fresh()->status);
        $this->assertSame('ناقص مستندات', $req->fresh()->decision_note);
        $this->assertFalse(app(MerchantPaymentAccountService::class)->isConfigured($this->business->id));
    }

    public function test_admin_index_renders(): void
    {
        $admin = User::query()->where('type', 'admin')->orderBy('id')->first();
        if (! $admin) {
            $this->markTestSkipped('Needs an admin user.');
        }

        $this->actingAs($admin)->get('/admin/merchant-account-requests')->assertOk();
    }
}
