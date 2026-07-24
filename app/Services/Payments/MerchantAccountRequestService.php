<?php

namespace App\Services\Payments;

use App\Models\MerchantAccountRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The business-facing "apply for a Fawry merchant sub-account" flow and its admin
 * decisions. Submitting is guarded (business only, not already configured, no
 * open request); approving provisions the merchant_payment_accounts row via
 * MerchantPaymentAccountService.
 */
class MerchantAccountRequestService
{
    public function __construct(private readonly MerchantPaymentAccountService $accounts)
    {
    }

    /** A business applies for a sub-account. */
    public function submit(User $business, ?string $note): MerchantAccountRequest
    {
        if (! $business->isBusiness()) {
            throw ValidationException::withMessages(['business' => [__('هذه الخدمة متاحة لحسابات التجّار فقط.')]]);
        }

        if ($this->accounts->isConfigured((int) $business->id)) {
            throw ValidationException::withMessages(['business' => [__('لديك حساب merchant مُفعّل بالفعل.')]]);
        }

        if ($this->pendingFor((int) $business->id) !== null) {
            throw ValidationException::withMessages(['business' => [__('لديك طلب قيد المراجعة بالفعل.')]]);
        }

        return MerchantAccountRequest::create([
            'business_id' => (int) $business->id,
            'status' => MerchantAccountRequest::STATUS_PENDING,
            'note' => $note ? mb_substr($note, 0, 1000) : null,
        ]);
    }

    /** Status view model for a business. */
    public function statusFor(User $business): array
    {
        $pending = $this->pendingFor((int) $business->id);

        return [
            'has_account' => $this->accounts->isConfigured((int) $business->id),
            'routing_enabled' => $this->accounts->isEnabled(),
            'pending_request' => $pending !== null,
            'request_status' => optional(
                $pending ?? MerchantAccountRequest::query()->where('business_id', $business->id)->latest('id')->first()
            )->status,
        ];
    }

    /** Approve a request AND provision the merchant's credentials in one step. */
    public function approve(MerchantAccountRequest $request, string $merchantCode, ?string $securityKey, int $adminId, ?string $note = null): void
    {
        $this->accounts->save((int) $request->business_id, $merchantCode, $securityKey, true);

        $request->update([
            'status' => MerchantAccountRequest::STATUS_APPROVED,
            'decision_note' => $note,
            'decided_by' => $adminId,
            'decided_at' => now(),
        ]);
    }

    /** Reject a request with an optional reason. */
    public function reject(MerchantAccountRequest $request, ?string $reason, int $adminId): void
    {
        $request->update([
            'status' => MerchantAccountRequest::STATUS_REJECTED,
            'decision_note' => $reason,
            'decided_by' => $adminId,
            'decided_at' => now(),
        ]);
    }

    private function pendingFor(int $businessId): ?MerchantAccountRequest
    {
        return MerchantAccountRequest::query()
            ->where('business_id', $businessId)
            ->where('status', MerchantAccountRequest::STATUS_PENDING)
            ->latest('id')
            ->first();
    }
}
