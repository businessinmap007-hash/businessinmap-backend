<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\AccountResource;
use App\Models\BlockedIdentity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * v2 authentication — the mobile app's token entry point (replaces the legacy
 * Api\V1 Login/Registration). Issues Sanctum personal-access tokens; the app
 * sends them as `Authorization: Bearer <token>` to the auth:sanctum group.
 *
 * The legacy non-null unique `users.api_token` column is filled with a random
 * value on register (it is NOT the auth token — Sanctum is the source of truth).
 */
final class AuthController extends Controller
{
    /** POST /api/v2/auth/register */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:15', 'unique:users,phone'],
            'password' => \App\Support\PasswordPolicy::rules(),
            'type' => ['nullable', Rule::in([User::TYPE_CLIENT, User::TYPE_BUSINESS])],
            // A business is defined by its category_child: it decides which
            // platform services (booking/menu/retail/…) the owner may sell, via
            // CategoryPlatformService (see ResolvesOwnerCatalog). Without it the
            // merchant panel is empty, so it is required for the business path.
            // The child lives in category_children_master (NOT category_children,
            // which does not exist — that was a silently-broken exists rule).
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category_child_id' => [
                'required_if:type,' . User::TYPE_BUSINESS,
                'nullable', 'integer', 'exists:category_children_master,id',
            ],
        ]);

        // A ban is on the identity, not on the row: without this, a banned user
        // registers again with the same email and phone and the ban means
        // nothing. The list is hashed, so this is a membership test only.
        if (BlockedIdentity::isBlocked($data['email'], $data['phone'])) {
            throw ValidationException::withMessages([
                'email' => [__('لا يمكن إنشاء حساب بهذه البيانات.')],
            ]);
        }

        $user = DB::transaction(function () use ($data) {
            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'type' => $data['type'] ?? User::TYPE_CLIENT,
                'category_id' => $data['category_id'] ?? null,
                'category_child_id' => $data['category_child_id'] ?? null,
                'api_token' => $this->freshApiToken(),
            ]);
        });

        // Record acceptance of the current terms + privacy (audit trail); the app
        // presents the documents + consent gate before calling register.
        app(\App\Services\LegalConsentService::class)->recordSignupConsent($user, $request->ip());

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => new AccountResource($user),
            'token' => $token,
        ], 201);
    }

    /** POST /api/v2/auth/login */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // The SoftDeletes global scope already hides an account that requested
        // deletion — it cannot log in during the grace window. Cancelling is the
        // way back (POST /api/v2/account/deletion/cancel).
        $user = User::query()->where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('البريد الإلكتروني أو كلمة المرور غير صحيحة.')],
            ]);
        }

        // Told plainly, and only after the password checked out: a ban is not a
        // secret from its owner, but it must not leak to someone guessing.
        if ($user->isBanned()) {
            throw ValidationException::withMessages([
                'email' => [__('تم إيقاف هذا الحساب نهائيًا.')],
            ]);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => new AccountResource($user),
            'token' => $token,
        ]);
    }

    /** GET /api/v2/auth/me — the current authenticated account. */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => new AccountResource($request->user()),
        ]);
    }

    /** POST /api/v2/auth/logout — revoke the token used for this request. */
    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json(['success' => true]);
    }

    /** POST /api/v2/auth/logout-all — revoke every token for this account. */
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['success' => true]);
    }

    /** A random value for the legacy non-null unique users.api_token column. */
    private function freshApiToken(): string
    {
        do {
            $token = Str::random(80);
        } while (User::query()->where('api_token', $token)->exists());

        return $token;
    }
}
