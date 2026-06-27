<?php

namespace App\Services\Guarantees;

use App\Models\GuaranteeTransaction;
use App\Models\UserGuarantee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class GuaranteeExpirationService
{
    public function expireIfDue(
        UserGuarantee $guarantee,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $meta = []
    ): array {
        return DB::transaction(function () use ($guarantee, $referenceType, $referenceId, $meta) {
            /** @var UserGuarantee $lockedGuarantee */
            $lockedGuarantee = UserGuarantee::query()
                ->whereKey((int) $guarantee->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array((string) $lockedGuarantee->status, [
                UserGuarantee::STATUS_CANCELLED,
                UserGuarantee::STATUS_SUSPENDED,
            ], true)) {
                return [
                    'changed' => false,
                    'reason' => 'already_inactive',
                    'guarantee' => $lockedGuarantee,
                    'expires_at' => $this->expiresAt($lockedGuarantee),
                ];
            }

            $expiresAt = $this->expiresAt($lockedGuarantee);

            if (! $expiresAt) {
                return [
                    'changed' => false,
                    'reason' => 'missing_expiration_date',
                    'guarantee' => $lockedGuarantee,
                    'expires_at' => null,
                ];
            }

            if ($expiresAt->isFuture()) {
                return [
                    'changed' => false,
                    'reason' => 'not_expired',
                    'guarantee' => $lockedGuarantee,
                    'expires_at' => $expiresAt,
                ];
            }

            $oldStatus = (string) $lockedGuarantee->status;
            $oldEffectiveLevelId = $lockedGuarantee->effective_level_id ? (int) $lockedGuarantee->effective_level_id : null;
            $oldCoverage = round((float) $lockedGuarantee->current_coverage_amount, 2);

            $lockedGuarantee->effective_level_id = null;
            $lockedGuarantee->status = UserGuarantee::STATUS_SUSPENDED;
            $lockedGuarantee->current_coverage_amount = 0;
            $lockedGuarantee->meta = array_merge(
                is_array($lockedGuarantee->meta ?? null) ? $lockedGuarantee->meta : [],
                [
                    'expired_at' => now()->toDateTimeString(),
                    'expiration_reason' => $meta['expiration_reason'] ?? 'guarantee_period_expired',
                    'last_expiration_reference_type' => $referenceType,
                    'last_expiration_reference_id' => $referenceId,
                ]
            );
            $lockedGuarantee->save();

            GuaranteeTransaction::create([
                'user_id' => (int) $lockedGuarantee->user_id,
                'user_guarantee_id' => (int) $lockedGuarantee->id,
                'type' => 'expiration',
                'amount' => 0,
                'coverage_amount' => 0,
                'balance_before' => null,
                'balance_after' => null,
                'locked_before' => round((float) $lockedGuarantee->locked_amount, 2),
                'locked_after' => round((float) $lockedGuarantee->locked_amount, 2),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reason' => 'Guarantee expired',
                'idempotency_key' => $meta['idempotency_key'] ?? $this->buildIdempotencyKey($lockedGuarantee, $expiresAt, $referenceType, $referenceId),
                'meta' => array_merge($meta, [
                    'old_status' => $oldStatus,
                    'new_status' => UserGuarantee::STATUS_SUSPENDED,
                    'old_effective_level_id' => $oldEffectiveLevelId,
                    'new_effective_level_id' => null,
                    'old_coverage_amount' => $oldCoverage,
                    'new_coverage_amount' => 0,
                    'expires_at' => $expiresAt->toDateTimeString(),
                ]),
            ]);

            return [
                'changed' => true,
                'reason' => 'expired',
                'guarantee' => $lockedGuarantee->refresh(),
                'expires_at' => $expiresAt,
            ];
        });
    }

    public function expiresAt(UserGuarantee $guarantee): ?Carbon
    {
        $meta = is_array($guarantee->meta ?? null) ? $guarantee->meta : [];

        $value = $meta['guarantee_expires_at']
            ?? $meta['expires_at']
            ?? $meta['valid_until']
            ?? $meta['subscription_expires_at']
            ?? null;

        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function buildIdempotencyKey(
        UserGuarantee $guarantee,
        Carbon $expiresAt,
        ?string $referenceType,
        ?int $referenceId
    ): string {
        $reference = $referenceType && $referenceId
            ? ($referenceType . ':' . $referenceId)
            : ('expiration:' . $expiresAt->format('YmdHis'));

        return implode(':', [
            'guarantee_expiration',
            (int) $guarantee->id,
            $expiresAt->format('YmdHis'),
            $reference,
        ]);
    }
}
