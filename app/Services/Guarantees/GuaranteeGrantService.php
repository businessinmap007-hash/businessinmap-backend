<?php

namespace App\Services\Guarantees;

use App\Models\GuaranteeLevel;
use App\Models\GuaranteeTransaction;
use App\Models\User;
use App\Models\UserGuarantee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin-only: grant a business a guarantee for free.
 *
 * Unlike a purchased guarantee (GuaranteeActivationService locks wallet balance
 * to back the coverage), a granted one has NO backing — the platform is choosing
 * to extend trust as a gift. So `locked_amount` stays 0, the wallet is never
 * touched, and it is flagged `is_granted` which:
 *   - blocks unlock-to-balance (nothing to refund; it must never mint money), and
 *   - exempts it from the funding-based downgrade, which would otherwise wipe a
 *     level whose required_locked_amount it can never meet.
 */
class GuaranteeGrantService
{
    public function __construct(
        protected \App\Services\ServiceFeeConsentEnforcer $feeConsent
    ) {
    }

    public function grant(User $business, GuaranteeLevel $level, int $adminId, ?string $note = null): UserGuarantee
    {
        if (! $business->isBusiness()) {
            throw ValidationException::withMessages([
                'user' => __('المنحة متاحة لحسابات الأعمال فقط.'),
            ]);
        }

        if ($level->target_type !== GuaranteeLevel::TARGET_BUSINESS) {
            throw ValidationException::withMessages([
                'level' => __('مستوى الضمان غير مناسب لنوع المستخدم.'),
            ]);
        }

        return DB::transaction(function () use ($business, $level, $adminId, $note) {
            $existing = UserGuarantee::query()
                ->where('user_id', (int) $business->id)
                ->where('target_type', GuaranteeLevel::TARGET_BUSINESS)
                ->lockForUpdate()
                ->first();

            // Never clobber real locked money with a free grant.
            if ($existing && round((float) $existing->locked_amount, 2) > 0) {
                throw ValidationException::withMessages([
                    'user' => __('لدى هذا البزنس ضمان مدفوع بالفعل؛ لا يمكن استبداله بمنحة.'),
                ]);
            }

            $coverage = round((float) $level->active_coverage_amount, 2);

            $guarantee = UserGuarantee::updateOrCreate(
                [
                    'user_id' => (int) $business->id,
                    'target_type' => GuaranteeLevel::TARGET_BUSINESS,
                ],
                [
                    'purchased_level_id' => (int) $level->id,
                    'effective_level_id' => (int) $level->id,
                    'status' => UserGuarantee::STATUS_ACTIVE,

                    'locked_amount' => 0,
                    'pending_coverage_amount' => round((float) $level->pending_coverage_amount, 2),
                    'active_coverage_amount' => $coverage,
                    'current_coverage_amount' => $coverage,
                    'used_coverage_amount' => 0,

                    'is_granted' => true,
                    'granted_by' => $adminId,
                    'trust_score' => $existing->trust_score ?? 0,
                    'activated_at' => now(),
                    'cancelled_at' => null,
                    'grace_until' => null,
                    'meta' => array_merge(
                        is_array($existing->meta ?? null) ? $existing->meta : [],
                        [
                            'granted_at' => now()->toDateTimeString(),
                            'granted_by' => $adminId,
                            'granted_level_id' => (int) $level->id,
                            'granted_note' => $note,
                        ]
                    ),
                ]
            );

            $business->forceFill([
                'guarantee_enabled' => true,
                'rating_enabled' => true,
                'commercial_operations_enabled' => true,
            ])->save();

            // A guarantee — even a free one — forces fee + rating consent.
            $this->feeConsent->enforce($business, 'منح ضمان من الإدارة');

            GuaranteeTransaction::create([
                'user_id' => (int) $business->id,
                'user_guarantee_id' => (int) $guarantee->id,
                'type' => 'grant',
                'amount' => 0,
                'coverage_amount' => $coverage,
                'balance_before' => null,
                'balance_after' => null,
                'locked_before' => 0,
                'locked_after' => 0,
                'reference_type' => 'admin_action',
                'reference_id' => $adminId,
                'reason' => 'Guarantee granted by admin (no payment)',
                'idempotency_key' => 'guarantee_grant_' . $guarantee->id . '_' . now()->format('YmdHis'),
                'meta' => [
                    'level_id' => (int) $level->id,
                    'level_code' => (string) $level->code,
                    'admin_id' => $adminId,
                    'note' => $note,
                ],
            ]);

            return $guarantee->refresh();
        });
    }
}
