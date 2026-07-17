<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AdminAbilityService;
use App\Support\AdminAbility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AdminV2 screen for granting and revoking admin abilities (BIM-14.1).
 *
 * Before this existed, enforcement was real but unusable: a new admin started
 * with no abilities, saw 403 everywhere, and the only way to fix that was
 * Bouncer::allow() in tinker.
 *
 * Thin on purpose — every rule that decides who may change what lives in
 * AdminAbilityService, because they are the security boundary and belong
 * somewhere testable without an HTTP request.
 */
class AdminRoleController extends Controller
{
    public function __construct(private readonly AdminAbilityService $abilities)
    {
    }

    /** GET admin/admin-roles */
    public function index(Request $request): View
    {
        $actor = $request->user();

        $admins = $this->abilities->manageableAdmins()->map(fn (User $admin) => [
            'user' => $admin,
            'is_super' => $this->abilities->isSuperAdmin($admin),
            'abilities' => $this->abilities->abilitiesOf($admin),
            'block_reason' => $this->abilities->blockReason($actor, $admin),
        ]);

        return view('admin-v2.admin-roles.index', [
            'admins' => $admins,
            'labels' => AdminAbility::labels(),
        ]);
    }

    /** GET admin/admin-roles/{user}/edit */
    public function edit(Request $request, User $user): View|RedirectResponse
    {
        $actor = $request->user();

        if ($reason = $this->abilities->blockReason($actor, $user)) {
            return redirect()->route('admin.admin-roles.index')->withErrors(['user' => $reason]);
        }

        return view('admin-v2.admin-roles.edit', [
            'target' => $user,
            'held' => $this->abilities->abilitiesOf($user),
            // Anything the actor does not hold is shown, but locked: hiding it
            // would make the screen look different to different admins and
            // quietly hide what the account could have.
            'grantable' => $this->abilities->grantableBy($actor),
            'labels' => AdminAbility::labels(),
            'hints' => AdminAbility::hints(),
        ]);
    }

    /** PUT admin/admin-roles/{user} */
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'abilities' => ['array'],
            'abilities.*' => ['string', 'in:' . implode(',', AdminAbility::ALL)],
        ]);

        try {
            $this->abilities->sync($request->user(), $user, $data['abilities'] ?? []);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('admin.admin-roles.index')
                ->withErrors(['abilities' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.admin-roles.index')
            ->with('success', 'تم تحديث صلاحيات «' . $user->name . '».');
    }
}
