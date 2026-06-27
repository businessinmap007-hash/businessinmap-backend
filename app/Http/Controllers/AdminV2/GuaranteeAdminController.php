<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\GuaranteeLevel;
use App\Models\GuaranteeTransaction;
use App\Models\UserGuarantee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
