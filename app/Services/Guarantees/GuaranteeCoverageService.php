<?php

namespace App\Services\Guarantees;

use App\Models\GuaranteeLevel;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use Illuminate\Validation\ValidationException;

class GuaranteeCoverageService
{
    public function activeGuarantee(User $user, ?string $targetType = null): ?UserGuarantee
    {
        $targetType = $targetType ?: $this->resolveTargetType($user);

        return UserGuarantee::query()
            ->where('user_id', (int) $user->id)
            ->where('target_type', $targetType)
            ->whereIn('status', [
                UserGuarantee::STATUS_ACTIVE,
                UserGuarantee::STATUS_PENDING_OPERATIONS,
                UserGuarantee::STATUS_UNDERFUNDED,
            ])
            ->latest('id')
            ->first();
    }

    public function payload(User $user, ?string $targetType = null): array
    {
        $guarantee = $this->activeGuarantee($user, $targetType);

        if (! $guarantee) {
            return [
                'enabled' => false,
                'status' => null,
                'available_coverage' => 0.0,
            ];
        }

        return [
            'enabled' => true,
            'id' => (int) $guarantee->id,
            'target_type' => (string) $guarantee->target_type,
            'status' => (string) $guarantee->status,
            'locked_amount' => (float) $guarantee->locked_amount,
            'current_coverage_amount' => (float) $guarantee->current_coverage_amount,
            'used_coverage_amount' => (float) $guarantee->used_coverage_amount,
            'available_coverage' => (float) $guarantee->availableCoverage(),
            'purchased_level_id' => (int) $guarantee->purchased_level_id,
            'effective_level_id' => $guarantee->effective_level_id ? (int) $guarantee->effective_level_id : null,
        ];
    }

    public function covers(User $user, float $amount, ?string $targetType = null): bool
    {
        $guarantee = $this->activeGuarantee($user, $targetType);

        return $guarantee
            && $guarantee->isUsable()
            && $guarantee->covers($amount);
    }

    public function ensureFreeBalanceForFee(User $user, float $requiredAmount): void
    {
        $requiredAmount = round(max($requiredAmount, 0), 2);

        if ($requiredAmount <= 0) {
            return;
        }

        $wallet = Wallet::query()
            ->where('user_id', (int) $user->id)
            ->first();

        if (! $wallet || (float) $wallet->balance < $requiredAmount) {
            throw ValidationException::withMessages([
                'wallet' => 'يجب وجود رصيد حر كافٍ لخصم رسوم المنصة. الضمان لا يستخدم لدفع رسوم التشغيل.',
            ]);
        }
    }

    protected function resolveTargetType(User $user): string
    {
        return $user->isBusiness()
            ? GuaranteeLevel::TARGET_BUSINESS
            : GuaranteeLevel::TARGET_CLIENT;
    }
}