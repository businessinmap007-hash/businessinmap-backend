<?php

namespace App\Services\Guarantees;

use App\Models\GuaranteeLevel;
use App\Models\OperationGuarantor;
use App\Models\User;
use App\Models\UserGuarantee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Friend co-guarantors for a single operation (Phase: guarantee-as-deposit).
 *
 * When a user's own platform-purchased guarantee coverage is not enough for an
 * operation, they invite a friend whose guarantee coverage supplements theirs
 * for that operation only. Accepting FREEZES the friend's coverage (increments
 * used_coverage_amount, reducing available); it is never charged. Releasing on
 * completion / dispute resolution returns it. Fees, if any, are taken from the
 * wallet balance elsewhere — never from the guarantee.
 */
class OperationGuarantorService
{
    public function __construct(protected GuaranteeCoverageService $coverage)
    {
    }

    /** The requester's own available guarantee coverage (as a client). */
    public function selfCoverage(User $requester): float
    {
        $guarantee = $this->coverage->activeGuarantee($requester, GuaranteeLevel::TARGET_CLIENT);

        return $guarantee ? $guarantee->availableCoverage() : 0.0;
    }

    /** Accepted friends' frozen contribution (excludes the requester's own self row). */
    public function friendsCoverage(string $operationType, int $operationId, User $requester): float
    {
        return round((float) OperationGuarantor::query()
            ->forOperation($operationType, $operationId)
            ->active()
            ->where('guarantor_user_id', '!=', (int) $requester->id)
            ->sum('covered_amount'), 2);
    }

    /**
     * Combined coverage for an operation = the requester's own available
     * coverage (self rows excluded — self is represented by availableCoverage)
     * plus every accepted friend's frozen contribution.
     */
    public function combinedCoverage(string $operationType, int $operationId, User $requester): float
    {
        return round($this->selfCoverage($requester) + $this->friendsCoverage($operationType, $operationId, $requester), 2);
    }

    public function isOperationCovered(string $operationType, int $operationId, User $requester, float $required): bool
    {
        return $this->combinedCoverage($operationType, $operationId, $requester) >= round($required, 2);
    }

    /**
     * Freeze the requester's OWN guarantee coverage for the operation (called at
     * operation start). Recorded as a self row so releaseOperation returns it
     * alongside the friends'. Idempotent per operation.
     */
    public function freezeSelf(string $operationType, int $operationId, User $requester, float $amount): ?OperationGuarantor
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($operationType, $operationId, $requester, $amount) {
            $existing = OperationGuarantor::query()
                ->forOperation($operationType, $operationId)
                ->where('guarantor_user_id', (int) $requester->id)
                ->active()
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $guarantee = $this->coverage->activeGuarantee($requester, GuaranteeLevel::TARGET_CLIENT);

            if (! $guarantee || ! $guarantee->isUsable()) {
                throw ValidationException::withMessages(['guarantee' => __('لا تملك ضمانًا نشطًا صالحًا.')]);
            }

            $guarantee = UserGuarantee::query()->whereKey($guarantee->id)->lockForUpdate()->first();

            if (! $guarantee->covers($amount)) {
                throw ValidationException::withMessages(['guarantee' => __('سعة ضمانك لا تكفي لتجميد هذه القيمة.')]);
            }

            $guarantee->used_coverage_amount = round((float) $guarantee->used_coverage_amount + $amount, 2);
            $guarantee->save();

            return OperationGuarantor::create([
                'operation_type' => $operationType,
                'operation_id' => $operationId,
                'requester_user_id' => (int) $requester->id,
                'guarantor_user_id' => (int) $requester->id,
                'user_guarantee_id' => (int) $guarantee->id,
                'covered_amount' => $amount,
                'status' => OperationGuarantor::STATUS_ACCEPTED,
                'invited_at' => now(),
                'responded_at' => now(),
                'meta' => ['is_self' => true],
            ]);
        });
    }

    /**
     * Invite a friend to co-guarantee. The friend must hold a usable
     * (platform-purchased) guarantee. Re-inviting returns the existing pending row.
     */
    public function invite(string $operationType, int $operationId, User $requester, User $friend): OperationGuarantor
    {
        if ((int) $friend->id === (int) $requester->id) {
            throw ValidationException::withMessages(['guarantor' => __('لا يمكنك ضمان نفسك.')]);
        }

        $guarantee = $this->coverage->activeGuarantee($friend, GuaranteeLevel::TARGET_CLIENT);

        if (! $guarantee || ! $guarantee->isUsable()) {
            throw ValidationException::withMessages(['guarantor' => __('الصديق لا يملك ضمانًا نشطًا صالحًا.')]);
        }

        $existing = OperationGuarantor::query()
            ->forOperation($operationType, $operationId)
            ->where('guarantor_user_id', $friend->id)
            ->first();

        if ($existing && in_array($existing->status, [OperationGuarantor::STATUS_INVITED, OperationGuarantor::STATUS_ACCEPTED], true)) {
            return $existing;
        }

        return OperationGuarantor::create([
            'operation_type' => $operationType,
            'operation_id' => $operationId,
            'requester_user_id' => (int) $requester->id,
            'guarantor_user_id' => (int) $friend->id,
            'user_guarantee_id' => (int) $guarantee->id,
            'covered_amount' => 0,
            'status' => OperationGuarantor::STATUS_INVITED,
            'invited_at' => now(),
        ]);
    }

    /**
     * The friend accepts and freezes $amount of their guarantee coverage for the
     * operation. Idempotent: accepting an already-accepted row is a no-op.
     */
    public function accept(OperationGuarantor $row, float $amount): OperationGuarantor
    {
        $amount = round($amount, 2);

        return DB::transaction(function () use ($row, $amount) {
            $row = OperationGuarantor::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();

            if ($row->status === OperationGuarantor::STATUS_ACCEPTED) {
                return $row;
            }

            if ($row->status !== OperationGuarantor::STATUS_INVITED) {
                throw ValidationException::withMessages(['guarantor' => __('لا يمكن قبول هذه الدعوة في حالتها الحالية.')]);
            }

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => __('قيمة التغطية غير صالحة.')]);
            }

            $guarantee = UserGuarantee::query()->whereKey($row->user_guarantee_id)->lockForUpdate()->first();

            if (! $guarantee || ! $guarantee->isUsable()) {
                throw ValidationException::withMessages(['guarantor' => __('ضمان الصديق لم يعد صالحًا.')]);
            }

            if (! $guarantee->covers($amount)) {
                throw ValidationException::withMessages(['guarantor' => __('سعة ضمان الصديق لا تكفي لتغطية هذه القيمة.')]);
            }

            // Freeze (not charge): lock the coverage by raising used_coverage_amount.
            $guarantee->used_coverage_amount = round((float) $guarantee->used_coverage_amount + $amount, 2);
            $guarantee->save();

            $row->covered_amount = $amount;
            $row->status = OperationGuarantor::STATUS_ACCEPTED;
            $row->responded_at = now();
            $row->save();

            return $row;
        });
    }

    public function decline(OperationGuarantor $row): OperationGuarantor
    {
        if ($row->status === OperationGuarantor::STATUS_INVITED) {
            $row->status = OperationGuarantor::STATUS_DECLINED;
            $row->responded_at = now();
            $row->save();
        }

        return $row;
    }

    /**
     * Release every accepted friend's frozen coverage for the operation
     * (completion or dispute resolution). Idempotent per row.
     */
    public function releaseOperation(string $operationType, int $operationId): void
    {
        DB::transaction(function () use ($operationType, $operationId) {
            $rows = OperationGuarantor::query()
                ->forOperation($operationType, $operationId)
                ->active()
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $guarantee = UserGuarantee::query()->whereKey($row->user_guarantee_id)->lockForUpdate()->first();

                if ($guarantee) {
                    $guarantee->used_coverage_amount = max(
                        round((float) $guarantee->used_coverage_amount - (float) $row->covered_amount, 2),
                        0
                    );
                    $guarantee->save();
                }

                $row->status = OperationGuarantor::STATUS_RELEASED;
                $row->released_at = now();
                $row->save();
            }
        });
    }
}
