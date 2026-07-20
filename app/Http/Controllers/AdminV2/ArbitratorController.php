<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\ArbitrationSession;
use App\Models\User;
use App\Services\ArbitrationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The arbitrators' register: who holds the role, and what each of them has
 * actually done with it.
 *
 * Gated on ROLES rather than DISPUTES on purpose — appointing the people who
 * rule on money is a staffing decision, not a triage one. An arbitrator can run
 * their own queue all day and can still never appoint another arbitrator.
 */
final class ArbitratorController extends Controller
{
    public function __construct(protected ArbitrationService $arbitration)
    {
    }

    public function index()
    {
        $arbitrators = $this->arbitration->arbitrators()->map(function (User $user) {
            return [
                'user' => $user,
                'stats' => $this->arbitration->statsFor((int) $user->id),
            ];
        });

        // Admins who could be appointed — everyone in the panel who is not
        // already an arbitrator.
        $candidates = User::query()
            ->where('type', User::TYPE_ADMIN)
            ->whereNotIn('id', $arbitrators->pluck('user.id')->all())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin-v2.arbitrators.index', compact('arbitrators', 'candidates'));
    }

    /** One arbitrator's record, case by case. */
    public function show(User $user)
    {
        $stats = $this->arbitration->statsFor((int) $user->id);

        $sessions = ArbitrationSession::query()
            ->with('dispute:id,status,disputeable_type,disputeable_id,reason_code')
            ->where('arbitrator_id', $user->id)
            ->orderByDesc('id')
            ->paginate(50);

        return view('admin-v2.arbitrators.show', compact('user', 'stats', 'sessions'));
    }

    public function promote(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $this->arbitration->promote(User::findOrFail((int) $data['user_id']));
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', __('تم تعيين الحكم.'));
    }

    public function demote(User $user)
    {
        $this->arbitration->demote($user);

        return back()->with('success', __('تم إلغاء صفة الحكم. سجل جلساته محفوظ.'));
    }
}
