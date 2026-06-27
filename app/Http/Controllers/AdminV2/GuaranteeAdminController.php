<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\GuaranteeLevel;
use App\Models\GuaranteeTransaction;
use App\Models\UserGuarantee;
use App\Services\Guarantees\GuaranteeAutoDowngradeService;
use App\Services\Guarantees\GuaranteeAutoUpgradeService;
use App\Services\Guarantees\GuaranteeExpirationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GuaranteeAdminController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));
        $targetType = trim((string) $request->get('target_type', ''));
        $levelId = (int) $request->get('level_id', 0);
        $expires = trim((string) $request->get('expires', ''));
        $perPage = $this->normalizePerPage($request->get('per_page', 50));

        $base = UserGuarantee::query()
            ->with([
                'user:id,name,email,phone,type',
                'user.wallet:id,user_id,balance,locked_balance,status',
                'purchasedLevel:id,code,name_ar,name_en,target_type,priority',
                'effectiveLevel:id,code,name_ar,name_en,target_type,priority',
            ]);

        $this->applySearch($base, $q);
        $this->applyFilters($base, $status, $targetType, $levelId, $expires);

        $totals = $this->totalsForQuery($base);

        $guarantees = (clone $base)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $levels = GuaranteeLevel::query()
            ->orderBy('target_type')
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get(['id', 'code', 'name_ar', 'name_en', 'target_type']);

        return view('admin-v2.guarantees.index', [
            'guarantees' => $guarantees,
            'levels' => $levels,
            'q' => $q,
            'status' => $status,
            'targetType' => $targetType,
            'levelId' => $levelId,
            'expires' => $expires,
            'perPage' => $perPage,
            'totals' => $totals,
        ]);
    }

    public function show(UserGuarantee $guarantee)
    {
        $guarantee->load([
            'user:id,name,email,phone,type,guarantee_enabled,rating_enabled,commercial_operations_enabled',
            'user.wallet:id,user_id,balance,locked_balance,status',
            'purchasedLevel',
            'effectiveLevel',
        ]);

        $transactions = GuaranteeTransaction::query()
            ->where('user_guarantee_id', (int) $guarantee->id)
            ->with('user:id,name')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $levels = GuaranteeLevel::query()
            ->where('target_type', (string) $guarantee->target_type)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        return view('admin-v2.guarantees.show', [
            'guarantee' => $guarantee,
            'transactions' => $transactions,
            'levels' => $levels,
        ]);
    }

    public function syncCoverage(UserGuarantee $guarantee, GuaranteeAutoDowngradeService $service)
    {
        $result = $service->syncEffectiveLevel(
            guarantee: $guarantee,
            referenceType: 'admin_action',
            referenceId: (int) $guarantee->id,
            meta: $this->adminActionMeta('sync_coverage')
        );

        return $this->backWithActionResult($result, 'تمت مزامنة تغطية الضمان.');
    }

    public function processGraceNow(UserGuarantee $guarantee, GuaranteeAutoDowngradeService $service)
    {
        DB::transaction(function () use ($guarantee) {
            $lockedGuarantee = UserGuarantee::query()
                ->whereKey((int) $guarantee->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $lockedGuarantee->status === UserGuarantee::STATUS_UNDERFUNDED) {
                $lockedGuarantee->grace_until = now();
                $lockedGuarantee->meta = array_merge(
                    is_array($lockedGuarantee->meta ?? null) ? $lockedGuarantee->meta : [],
                    $this->adminActionMeta('force_grace_expired')
                );
                $lockedGuarantee->save();
            }
        });

        $result = $service->downgradeExpiredGrace(
            guarantee: $guarantee->refresh(),
            referenceType: 'admin_action',
            referenceId: (int) $guarantee->id,
            meta: $this->adminActionMeta('process_grace_now')
        );

        return $this->backWithActionResult($result, 'تمت معالجة Grace Period.');
    }

    public function autoUpgrade(UserGuarantee $guarantee, GuaranteeAutoUpgradeService $service)
    {
        $guarantee->load('user');

        if (! $guarantee->user) {
            throw ValidationException::withMessages([
                'user' => 'لا يوجد مستخدم مرتبط بهذا الضمان.',
            ]);
        }

        $result = $service->autoUpgrade(
            user: $guarantee->user,
            targetType: (string) $guarantee->target_type,
            referenceType: 'admin_action',
            referenceId: (int) $guarantee->id,
            meta: $this->adminActionMeta('auto_upgrade')
        );

        return $this->backWithActionResult($result, 'تم تشغيل Auto Upgrade.');
    }

    public function autoDowngrade(UserGuarantee $guarantee, GuaranteeAutoDowngradeService $service)
    {
        $result = $service->syncEffectiveLevel(
            guarantee: $guarantee,
            referenceType: 'admin_action',
            referenceId: (int) $guarantee->id,
            meta: $this->adminActionMeta('auto_downgrade')
        );

        return $this->backWithActionResult($result, 'تم تشغيل Auto Downgrade / Coverage Sync.');
    }

    public function expireNow(UserGuarantee $guarantee)
    {
        $this->forceStatus(
            guarantee: $guarantee,
            newStatus: UserGuarantee::STATUS_SUSPENDED,
            type: 'manual_expiration',
            reason: 'Guarantee manually expired by admin',
            metaAction: 'expire_now',
            clearEffectiveLevel: true,
            clearCoverage: true,
            extraMeta: ['expired_at' => now()->toDateTimeString()]
        );

        return back()->with('success', 'تم إنهاء الضمان يدويًا وتعليق التغطية.');
    }

    public function suspend(UserGuarantee $guarantee)
    {
        $this->forceStatus(
            guarantee: $guarantee,
            newStatus: UserGuarantee::STATUS_SUSPENDED,
            type: 'manual_suspend',
            reason: 'Guarantee manually suspended by admin',
            metaAction: 'suspend',
            clearEffectiveLevel: true,
            clearCoverage: true
        );

        return back()->with('success', 'تم تعليق الضمان يدويًا.');
    }

    public function reactivate(UserGuarantee $guarantee, GuaranteeAutoDowngradeService $service)
    {
        DB::transaction(function () use ($guarantee) {
            $lockedGuarantee = UserGuarantee::query()
                ->whereKey((int) $guarantee->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldStatus = (string) $lockedGuarantee->status;
            $oldEffectiveLevelId = $lockedGuarantee->effective_level_id ? (int) $lockedGuarantee->effective_level_id : null;
            $oldCoverage = round((float) $lockedGuarantee->current_coverage_amount, 2);

            $lockedGuarantee->status = UserGuarantee::STATUS_PENDING_OPERATIONS;
            $lockedGuarantee->cancelled_at = null;
            $lockedGuarantee->grace_until = null;
            $lockedGuarantee->current_coverage_amount = round((float) $lockedGuarantee->pending_coverage_amount, 2);
            $lockedGuarantee->meta = array_merge(
                is_array($lockedGuarantee->meta ?? null) ? $lockedGuarantee->meta : [],
                $this->adminActionMeta('reactivate')
            );
            $lockedGuarantee->save();

            GuaranteeTransaction::create([
                'user_id' => (int) $lockedGuarantee->user_id,
                'user_guarantee_id' => (int) $lockedGuarantee->id,
                'type' => 'manual_reactivate',
                'amount' => 0,
                'coverage_amount' => round((float) $lockedGuarantee->current_coverage_amount, 2),
                'balance_before' => null,
                'balance_after' => null,
                'locked_before' => round((float) $lockedGuarantee->locked_amount, 2),
                'locked_after' => round((float) $lockedGuarantee->locked_amount, 2),
                'reference_type' => 'admin_action',
                'reference_id' => (int) $lockedGuarantee->id,
                'reason' => 'Guarantee manually reactivated by admin',
                'idempotency_key' => $this->adminIdempotencyKey($lockedGuarantee, 'reactivate'),
                'meta' => [
                    'old_status' => $oldStatus,
                    'new_status' => (string) $lockedGuarantee->status,
                    'old_effective_level_id' => $oldEffectiveLevelId,
                    'new_effective_level_id' => $lockedGuarantee->effective_level_id ? (int) $lockedGuarantee->effective_level_id : null,
                    'old_coverage_amount' => $oldCoverage,
                    'new_coverage_amount' => round((float) $lockedGuarantee->current_coverage_amount, 2),
                ],
            ]);
        });

        $result = $service->syncEffectiveLevel(
            guarantee: $guarantee->refresh(),
            referenceType: 'admin_action',
            referenceId: (int) $guarantee->id,
            meta: $this->adminActionMeta('reactivate_sync')
        );

        return $this->backWithActionResult($result, 'تمت إعادة تفعيل الضمان ومحاولة مزامنة التغطية.');
    }

    public function expireIfDue(UserGuarantee $guarantee, GuaranteeExpirationService $service)
    {
        $result = $service->expireIfDue(
            guarantee: $guarantee,
            referenceType: 'admin_action',
            referenceId: (int) $guarantee->id,
            meta: $this->adminActionMeta('expire_if_due')
        );

        return $this->backWithActionResult($result, 'تم فحص انتهاء الضمان.');
    }

    private function forceStatus(
        UserGuarantee $guarantee,
        string $newStatus,
        string $type,
        string $reason,
        string $metaAction,
        bool $clearEffectiveLevel = false,
        bool $clearCoverage = false,
        array $extraMeta = []
    ): void {
        DB::transaction(function () use ($guarantee, $newStatus, $type, $reason, $metaAction, $clearEffectiveLevel, $clearCoverage, $extraMeta) {
            $lockedGuarantee = UserGuarantee::query()
                ->whereKey((int) $guarantee->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldStatus = (string) $lockedGuarantee->status;
            $oldEffectiveLevelId = $lockedGuarantee->effective_level_id ? (int) $lockedGuarantee->effective_level_id : null;
            $oldCoverage = round((float) $lockedGuarantee->current_coverage_amount, 2);

            if ($clearEffectiveLevel) {
                $lockedGuarantee->effective_level_id = null;
            }

            if ($clearCoverage) {
                $lockedGuarantee->current_coverage_amount = 0;
            }

            $lockedGuarantee->status = $newStatus;
            $lockedGuarantee->meta = array_merge(
                is_array($lockedGuarantee->meta ?? null) ? $lockedGuarantee->meta : [],
                $this->adminActionMeta($metaAction),
                $extraMeta
            );
            $lockedGuarantee->save();

            GuaranteeTransaction::create([
                'user_id' => (int) $lockedGuarantee->user_id,
                'user_guarantee_id' => (int) $lockedGuarantee->id,
                'type' => $type,
                'amount' => 0,
                'coverage_amount' => round((float) $lockedGuarantee->current_coverage_amount, 2),
                'balance_before' => null,
                'balance_after' => null,
                'locked_before' => round((float) $lockedGuarantee->locked_amount, 2),
                'locked_after' => round((float) $lockedGuarantee->locked_amount, 2),
                'reference_type' => 'admin_action',
                'reference_id' => (int) $lockedGuarantee->id,
                'reason' => $reason,
                'idempotency_key' => $this->adminIdempotencyKey($lockedGuarantee, $metaAction),
                'meta' => [
                    'old_status' => $oldStatus,
                    'new_status' => (string) $lockedGuarantee->status,
                    'old_effective_level_id' => $oldEffectiveLevelId,
                    'new_effective_level_id' => $lockedGuarantee->effective_level_id ? (int) $lockedGuarantee->effective_level_id : null,
                    'old_coverage_amount' => $oldCoverage,
                    'new_coverage_amount' => round((float) $lockedGuarantee->current_coverage_amount, 2),
                ],
            ]);
        });
    }

    private function backWithActionResult(array $result, string $successMessage)
    {
        $reason = (string) ($result['reason'] ?? 'done');
        $changed = (bool) ($result['changed'] ?? false);

        return back()->with(
            $changed ? 'success' : 'info',
            $successMessage . ' النتيجة: ' . $reason
        );
    }

    private function adminActionMeta(string $action): array
    {
        return [
            'source' => 'admin_v2',
            'admin_action' => $action,
            'admin_id' => auth()->id(),
            'admin_action_at' => now()->toDateTimeString(),
        ];
    }

    private function adminIdempotencyKey(UserGuarantee $guarantee, string $action): string
    {
        return implode(':', [
            'admin_guarantee_action',
            $action,
            (int) $guarantee->id,
            now()->format('YmdHis'),
            uniqid(),
        ]);
    }

    private function normalizePerPage($perPage): int
    {
        $perPage = (int) $perPage;

        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 50;
    }

    private function applySearch(Builder $query, string $q): void
    {
        if ($q === '') {
            return;
        }

        $query->where(function (Builder $w) use ($q) {
            $w->where('id', $q)
                ->orWhere('user_id', $q)
                ->orWhere('status', 'like', "%{$q}%")
                ->orWhere('target_type', 'like', "%{$q}%")
                ->orWhereHas('user', function (Builder $u) use ($q) {
                    $u->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                })
                ->orWhereHas('purchasedLevel', function (Builder $l) use ($q) {
                    $l->where('code', 'like', "%{$q}%")
                        ->orWhere('name_ar', 'like', "%{$q}%")
                        ->orWhere('name_en', 'like', "%{$q}%");
                });
        });
    }

    private function applyFilters(Builder $query, string $status, string $targetType, int $levelId, string $expires): void
    {
        if ($status !== '') {
            $query->where('status', $status);
        }

        if (in_array($targetType, [GuaranteeLevel::TARGET_CLIENT, GuaranteeLevel::TARGET_BUSINESS], true)) {
            $query->where('target_type', $targetType);
        }

        if ($levelId > 0) {
            $query->where(function (Builder $w) use ($levelId) {
                $w->where('purchased_level_id', $levelId)
                    ->orWhere('effective_level_id', $levelId);
            });
        }

        if ($expires === 'has_expiration') {
            $query->whereNotNull('meta');
        } elseif ($expires === 'expired') {
            $query->whereNotNull('meta')
                ->where(function (Builder $w) {
                    foreach ($this->expirationMetaKeys() as $key) {
                        $w->orWhere("meta->{$key}", '<=', now()->toDateTimeString());
                    }
                });
        } elseif ($expires === 'missing') {
            $query->where(function (Builder $w) {
                $w->whereNull('meta');

                foreach ($this->expirationMetaKeys() as $key) {
                    $w->orWhereNull("meta->{$key}");
                }
            });
        }
    }

    private function totalsForQuery(Builder $base): array
    {
        $row = (clone $base)
            ->selectRaw("\n                COUNT(*) as cnt,\n                COALESCE(SUM(CASE WHEN status='active' THEN 1 ELSE 0 END),0) as active_count,\n                COALESCE(SUM(CASE WHEN status='pending_operations' THEN 1 ELSE 0 END),0) as pending_count,\n                COALESCE(SUM(CASE WHEN status='underfunded' THEN 1 ELSE 0 END),0) as underfunded_count,\n                COALESCE(SUM(CASE WHEN status='suspended' THEN 1 ELSE 0 END),0) as suspended_count,\n                COALESCE(SUM(locked_amount),0) as locked_sum,\n                COALESCE(SUM(current_coverage_amount),0) as coverage_sum,\n                COALESCE(SUM(used_coverage_amount),0) as used_sum\n            ")
            ->first();

        return [
            'count' => (int) ($row->cnt ?? 0),
            'active' => (int) ($row->active_count ?? 0),
            'pending' => (int) ($row->pending_count ?? 0),
            'underfunded' => (int) ($row->underfunded_count ?? 0),
            'suspended' => (int) ($row->suspended_count ?? 0),
            'locked_sum' => (float) ($row->locked_sum ?? 0),
            'coverage_sum' => (float) ($row->coverage_sum ?? 0),
            'used_sum' => (float) ($row->used_sum ?? 0),
        ];
    }

    private function expirationMetaKeys(): array
    {
        return [
            'guarantee_expires_at',
            'expires_at',
            'valid_until',
            'subscription_expires_at',
        ];
    }
}
