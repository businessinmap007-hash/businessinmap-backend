<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\GuaranteeLevel;
use App\Models\GuaranteeTransaction;
use App\Models\UserGuarantee;
use App\Services\Guarantees\GuaranteeAutoUpgradeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class GuaranteeController extends Controller
{
    public function levels(Request $request)
    {
        $targetType = $this->resolveTargetType($request);

        $levels = GuaranteeLevel::query()
            ->where('target_type', $targetType)
            ->where('is_active', 1)
            ->orderByDesc('priority')
            ->orderBy('required_locked_amount')
            ->get()
            ->map(fn (GuaranteeLevel $level) => $this->levelPayload($level))
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'target_type' => $targetType,
                'levels' => $levels,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $targetType = $this->resolveTargetType($request);

        $guarantee = UserGuarantee::query()
            ->with(['purchasedLevel', 'effectiveLevel'])
            ->where('user_id', (int) $user->id)
            ->where('target_type', $targetType)
            ->latest('id')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'target_type' => $targetType,
                'guarantee' => $guarantee ? $this->guaranteePayload($guarantee) : null,
                'has_usable_guarantee' => $guarantee ? $guarantee->isUsable() : false,
            ],
        ]);
    }

    public function transactions(Request $request)
    {
        $user = $request->user();
        $targetType = $this->resolveTargetType($request);
        $perPage = min(max((int) $request->get('per_page', 20), 1), 100);

        $transactions = GuaranteeTransaction::query()
            ->where('user_id', (int) $user->id)
            ->whereHas('guarantee', function ($q) use ($targetType) {
                $q->where('target_type', $targetType);
            })
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'target_type' => $targetType,
                'transactions' => $transactions->getCollection()->map(fn (GuaranteeTransaction $tx) => $this->transactionPayload($tx))->values(),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
            ],
        ]);
    }

    public function activate(Request $request, GuaranteeAutoUpgradeService $service)
    {
        $data = $request->validate([
            'level_id' => ['nullable', 'integer', 'exists:guarantee_levels,id'],
            'target_type' => ['nullable', Rule::in([GuaranteeLevel::TARGET_CLIENT, GuaranteeLevel::TARGET_BUSINESS])],
        ]);

        $user = $request->user();
        $targetType = $this->resolveTargetType($request, $data['target_type'] ?? null);

        if (! empty($data['level_id'])) {
            $level = GuaranteeLevel::query()
                ->where('id', (int) $data['level_id'])
                ->where('target_type', $targetType)
                ->where('is_active', 1)
                ->first();

            if (! $level) {
                throw ValidationException::withMessages([
                    'level_id' => 'مستوى الضمان غير متاح لهذا النوع من المستخدم.',
                ]);
            }

            $result = $service->upgradeToLevel(
                user: $user,
                level: $level,
                referenceType: 'api_v2_guarantee_activation',
                referenceId: (int) $user->id,
                meta: [
                    'source' => 'api_v2',
                    'requested_level_id' => (int) $level->id,
                ]
            );
        } else {
            $result = $service->autoUpgrade(
                user: $user,
                targetType: $targetType,
                referenceType: 'api_v2_guarantee_activation',
                referenceId: (int) $user->id,
                meta: [
                    'source' => 'api_v2',
                    'mode' => 'auto',
                ]
            );
        }

        $guarantee = $result['guarantee'] ?? null;

        return response()->json([
            'success' => true,
            'data' => [
                'changed' => (bool) ($result['changed'] ?? false),
                'reason' => (string) ($result['reason'] ?? 'done'),
                'level' => ! empty($result['level']) ? $this->levelPayload($result['level']) : null,
                'guarantee' => $guarantee ? $this->guaranteePayload($guarantee->refresh()) : null,
            ],
        ]);
    }

    private function resolveTargetType(Request $request, ?string $requested = null): string
    {
        if (in_array($requested, [GuaranteeLevel::TARGET_CLIENT, GuaranteeLevel::TARGET_BUSINESS], true)) {
            return $requested;
        }

        $queryTarget = (string) $request->get('target_type', '');

        if (in_array($queryTarget, [GuaranteeLevel::TARGET_CLIENT, GuaranteeLevel::TARGET_BUSINESS], true)) {
            return $queryTarget;
        }

        return method_exists($request->user(), 'isBusiness') && $request->user()->isBusiness()
            ? GuaranteeLevel::TARGET_BUSINESS
            : GuaranteeLevel::TARGET_CLIENT;
    }

    private function levelPayload(GuaranteeLevel $level): array
    {
        return [
            'id' => (int) $level->id,
            'code' => (string) $level->code,
            'name_ar' => $level->name_ar,
            'name_en' => $level->name_en,
            'display_name' => $level->display_name,
            'target_type' => (string) $level->target_type,
            'required_locked_amount' => (string) $level->required_locked_amount,
            'pending_coverage_amount' => (string) $level->pending_coverage_amount,
            'active_coverage_amount' => (string) $level->active_coverage_amount,
            'required_completed_operations' => (int) $level->required_completed_operations,
            'required_trust_score' => (string) $level->required_trust_score,
            'max_lost_disputes' => $level->max_lost_disputes === null ? null : (int) $level->max_lost_disputes,
            'max_late_cancellations' => $level->max_late_cancellations === null ? null : (int) $level->max_late_cancellations,
            'priority' => (int) $level->priority,
            'is_active' => (bool) $level->is_active,
        ];
    }

    private function guaranteePayload(UserGuarantee $guarantee): array
    {
        $availableCoverage = method_exists($guarantee, 'availableCoverage')
            ? $guarantee->availableCoverage()
            : max(round((float) $guarantee->current_coverage_amount - (float) $guarantee->used_coverage_amount, 2), 0);

        return [
            'id' => (int) $guarantee->id,
            'target_type' => (string) $guarantee->target_type,
            'status' => (string) $guarantee->status,
            'is_usable' => $guarantee->isUsable(),
            'purchased_level' => $guarantee->purchasedLevel ? $this->levelPayload($guarantee->purchasedLevel) : null,
            'effective_level' => $guarantee->effectiveLevel ? $this->levelPayload($guarantee->effectiveLevel) : null,
            'locked_amount' => (string) $guarantee->locked_amount,
            'current_coverage_amount' => (string) $guarantee->current_coverage_amount,
            'used_coverage_amount' => (string) $guarantee->used_coverage_amount,
            'available_coverage_amount' => (string) $availableCoverage,
            'pending_coverage_amount' => (string) $guarantee->pending_coverage_amount,
            'active_coverage_amount' => (string) $guarantee->active_coverage_amount,
            'completed_operations_count' => (int) $guarantee->completed_operations_count,
            'cancelled_operations_count' => (int) $guarantee->cancelled_operations_count,
            'late_cancellations_count' => (int) $guarantee->late_cancellations_count,
            'disputes_opened_count' => (int) $guarantee->disputes_opened_count,
            'disputes_lost_count' => (int) $guarantee->disputes_lost_count,
            'trust_score' => (string) $guarantee->trust_score,
            'grace_until' => $guarantee->grace_until ? $guarantee->grace_until->toDateTimeString() : null,
            'activated_at' => $guarantee->activated_at ? $guarantee->activated_at->toDateTimeString() : null,
            'upgraded_at' => $guarantee->upgraded_at ? $guarantee->upgraded_at->toDateTimeString() : null,
            'downgraded_at' => $guarantee->downgraded_at ? $guarantee->downgraded_at->toDateTimeString() : null,
            'cancelled_at' => $guarantee->cancelled_at ? $guarantee->cancelled_at->toDateTimeString() : null,
            'meta' => is_array($guarantee->meta) ? $guarantee->meta : null,
        ];
    }

    private function transactionPayload(GuaranteeTransaction $tx): array
    {
        return [
            'id' => (int) $tx->id,
            'type' => (string) $tx->type,
            'amount' => (string) $tx->amount,
            'coverage_amount' => (string) $tx->coverage_amount,
            'balance_before' => $tx->balance_before === null ? null : (string) $tx->balance_before,
            'balance_after' => $tx->balance_after === null ? null : (string) $tx->balance_after,
            'locked_before' => $tx->locked_before === null ? null : (string) $tx->locked_before,
            'locked_after' => $tx->locked_after === null ? null : (string) $tx->locked_after,
            'reference_type' => $tx->reference_type,
            'reference_id' => $tx->reference_id,
            'reason' => $tx->reason,
            'meta' => is_array($tx->meta) ? $tx->meta : null,
            'created_at' => $tx->created_at ? $tx->created_at->toDateTimeString() : null,
        ];
    }
}
