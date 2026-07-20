<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Account deletions the day-31 sweep refused (BIM-15.1).
 *
 * finalize() flags rather than guesses whenever money is locked or a dispute
 * appeared after the request, and dueForFinalization() then skips anything
 * carrying a hold reason. Nothing brings those rows back on its own, so before
 * this screen existed they simply waited, unseen, forever.
 *
 * The two actions are the two real outcomes of a human review: the cause is
 * resolved, so finish the deletion; or the deletion should not proceed, so give
 * the account back. Both are the service's existing methods — this screen
 * decides nothing on its own.
 */
class HeldDeletionController extends Controller
{
    public function __construct(private readonly AccountDeletionService $deletion)
    {
    }

    /** GET admin/held-deletions */
    public function index(Request $request): View
    {
        $held = $this->deletion->held();

        $rows = $held->map(function (User $user) {
            // The stored reason is a snapshot from the night the sweep ran.
            // Re-evaluating tells the admin whether retrying would do anything.
            $current = $this->deletion->currentHoldReason($user);

            return [
                'user' => $user,
                'stored_reason' => (string) $user->deletion_hold_reason,
                'current_reason' => $current,
                'resolved' => $current === null,
                'locked_balance' => round((float) (Wallet::query()
                    ->where('user_id', $user->id)
                    ->value('locked_balance') ?? 0), 2),
                'available_balance' => round((float) (Wallet::query()
                    ->where('user_id', $user->id)
                    ->value('balance') ?? 0), 2),
                'open_disputes' => Dispute::query()
                    ->whereIn('status', AccountDeletionService::LIVE_DISPUTE_STATUSES)
                    ->where(fn ($q) => $q
                        ->where('opened_by_user_id', $user->id)
                        ->orWhere('against_user_id', $user->id))
                    ->count(),
            ];
        });

        return view('admin-v2.held-deletions.index', [
            'rows' => $rows,
            'resolvedCount' => $rows->where('resolved', true)->count(),
        ]);
    }

    /**
     * POST admin/held-deletions/{user}/finalize — retry the sweep for one row.
     *
     * finalize() re-checks the hold itself, so a still-blocked account is simply
     * flagged again with a fresh reason rather than forced through.
     */
    public function finalize(int $user): RedirectResponse
    {
        $model = $this->heldUser($user);

        $result = $this->deletion->finalize($model);

        if ($result['status'] === 'held') {
            return back()->with('error', __('لا يزال الحساب موقوفًا: ') . ($result['reason'] ?? ''));
        }

        if ($result['status'] === 'already_finalized') {
            return back()->with('error', __('هذا الحساب مُنفَّذ حذفه بالفعل.'));
        }

        return back()->with('success', __('تم إتمام حذف الحساب. المبلغ المُؤول للمنصة: ') . number_format($result['escheated'], 2));
    }

    /** POST admin/held-deletions/{user}/restore — cancel the deletion instead. */
    public function restore(int $user): RedirectResponse
    {
        $model = $this->heldUser($user);

        $this->deletion->restore($model);

        return back()->with('success', __('تمت استعادة الحساب وإلغاء طلب الحذف.'));
    }

    /**
     * Only a held, not-yet-anonymized account is actionable from here. Anything
     * else 404s rather than 403s — same no-enumeration rule the rest of the
     * panel's ownership checks follow.
     */
    private function heldUser(int $id): User
    {
        return User::onlyTrashed()
            ->whereNotNull('deletion_hold_reason')
            ->whereNull('anonymized_at')
            ->findOrFail($id);
    }
}
