<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * v2 account deletion (BIM-15.1) — the user's own "delete my account".
 *
 * Three endpoints on purpose: the app must be able to SHOW why deletion is
 * refused before the user taps it, so eligibility is a first-class read rather
 * than an error the user discovers by failing.
 *
 * Cancelling is the odd one: the account is soft-deleted, so its tokens are
 * gone and there is nobody to authenticate. It therefore takes credentials, not
 * a token — that is the only way back in during the grace window.
 */
final class AccountDeletionController extends Controller
{
    public function __construct(private readonly AccountDeletionService $deletion) {}

    /** GET /api/v2/account/deletion — may I delete, and if not, why not? */
    public function eligibility(Request $request)
    {
        $user = $request->user();
        $blockers = $this->deletion->blockers($user);

        return response()->json([
            'success' => true,
            'data' => [
                'can_delete' => $blockers === [],
                'blockers' => $blockers,
                'grace_days' => (int) config('bim.account_deletion.grace_days', 30),
                'balance_transfer' => $this->deletion->balanceTransferGate($user),
                'pending_deletion' => $user->isPendingDeletion(),
            ],
        ]);
    }

    /**
     * POST /api/v2/account/deletion — request deletion.
     *
     * Password-confirmed: this logs out every device and starts a clock on the
     * user's money, so a borrowed unlocked phone must not be enough.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('كلمة المرور غير صحيحة.'),
            ]);
        }

        try {
            $this->deletion->request($user, $data['reason'] ?? null);
        } catch (\RuntimeException $e) {
            // The blockers are the answer here, not a bare message: the app
            // needs the list to tell the user what to finish first.
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'blockers' => $this->deletion->blockers($user),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('تم استلام طلب حذف الحساب. يمكنك استعادته خلال ')
                . (int) config('bim.account_deletion.grace_days', 30) . __(' يومًا.'),
            'data' => [
                'deletion_requested_at' => $user->deletion_requested_at?->toIso8601String(),
                'restorable_until' => $user->deletion_scheduled_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/v2/account/deletion/cancel — restore inside the grace window.
     *
     * Unauthenticated by necessity (the tokens were revoked), so it verifies the
     * password itself and is throttled — this is a login in everything but name.
     */
    public function cancel(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::onlyTrashed()->where('email', $data['email'])->first();

        // One message for "no such account", "wrong password" and "already
        // anonymized": distinguishing them would turn this into an oracle for
        // which addresses exist.
        $invalid = ValidationException::withMessages([
            'email' => __('لا يوجد حساب محذوف بهذه البيانات، أو انتهت مهلة الاسترجاع.'),
        ]);

        if (! $user || $user->anonymized_at !== null || ! Hash::check($data['password'], $user->password)) {
            throw $invalid;
        }

        if ($user->deletion_requested_at === null) {
            // Soft-deleted by an admin, not by the user: not theirs to undo.
            throw $invalid;
        }

        $this->deletion->restore($user);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => __('تم استعادة الحساب.'),
            'token' => $token,
        ]);
    }
}
