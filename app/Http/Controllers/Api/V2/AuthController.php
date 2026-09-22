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
            // A CLIENT writes one name in whichever script he likes — a person's
            // name is not a translation of itself, and nobody searches for
            // customers. A BUSINESS writes both, because 35% of them carried a
            // Latin-only name and were invisible to every customer typing
            // Arabic. `name` holds the Arabic; `name_en` the English.
            'name' => ['required', 'string', 'max:191'],
            'name_en' => [
                'required_if:type,' . User::TYPE_BUSINESS,
                'nullable', 'string', 'max:191',
            ],
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
            // «د.» / «أ.د.» / «استشاري» before the name, and which of the
            // medical specialties describe this practice — only meaningful
            // (and only accepted) for an individual doctor's own clinic
            // account, never a hospital/medical-center's own name.
            'medical_title' => [
                Rule::requiredIf(($request->input('category_child_id')) == User::DOCTOR_OWN_CLINIC_CHILD_ID),
                Rule::prohibitedIf(($request->input('category_child_id')) != User::DOCTOR_OWN_CLINIC_CHILD_ID),
                'nullable', Rule::in(User::MEDICAL_TITLES),
            ],
            'specialty_option_ids' => ['nullable', 'array', 'max:10'],
            'specialty_option_ids.*' => ['integer', 'distinct'],
            // Where the account is. Everything location-aware hangs on this - which
            // shops a customer sees, delivery vs governorate shipping, which
            // shipping companies a merchant can pick - so it is not optional.
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'address_line' => ['required', 'string', 'min:5', 'max:191'],
            // Optional GPS point taken at signup ("use my location").
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            // No consent, no account: registration is cancelled if the terms are
            // not accepted. `accepted` also fails when the field is absent.
            'terms_accepted' => ['accepted'],
        ], [
            'terms_accepted.accepted' => __('يجب الموافقة على الشروط والأحكام لإنشاء الحساب.'),
        ]);

        $specialtyIds = [];
        if (! empty($data['specialty_option_ids'])) {
            abort_unless(
                (int) ($data['category_child_id'] ?? 0) === User::DOCTOR_OWN_CLINIC_CHILD_ID,
                422,
                __('التخصص الطبي متاح لحسابات العيادات فقط.')
            );

            // Only real options this specific child actually offers — never
            // trust a bare id list straight from the client.
            $specialtyIds = \App\Models\CategoryChild::query()
                ->find((int) $data['category_child_id'])
                ?->activeOptionsForParent((int) ($data['category_id'] ?? 0))
                ->whereIn('options.id', $data['specialty_option_ids'])
                ->where('options.group_id', User::MEDICAL_SPECIALTY_GROUP_ID)
                ->pluck('options.id')
                ->all() ?? [];

            abort_if(
                count($specialtyIds) !== count(array_unique($data['specialty_option_ids'])),
                422,
                __('اختر تخصصاً طبياً صحيحاً.')
            );
        }

        if (! \App\Models\City::query()->whereKey((int) $data['city_id'])->where('governorate_id', (int) $data['governorate_id'])->exists()) {
            throw ValidationException::withMessages(['city_id' => [__('المدينة المختارة لا تتبع المحافظة المختارة.')]]);
        }

        // A ban is on the identity, not on the row: without this, a banned user
        // registers again with the same email and phone and the ban means
        // nothing. The list is hashed, so this is a membership test only.
        if (BlockedIdentity::isBlocked($data['email'], $data['phone'])) {
            throw ValidationException::withMessages([
                'email' => [__('لا يمكن إنشاء حساب بهذه البيانات.')],
            ]);
        }

        $user = DB::transaction(function () use ($data, $specialtyIds) {
            $user = User::create([
                'name' => $data['name'],
                'name_en' => $data['name_en'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'type' => $data['type'] ?? User::TYPE_CLIENT,
                'category_id' => $data['category_id'] ?? null,
                'category_child_id' => $data['category_child_id'] ?? null,
                'medical_title' => $data['medical_title'] ?? null,
                'governorate_id' => (int) $data['governorate_id'],
                'city_id' => (int) $data['city_id'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'api_token' => $this->freshApiToken(),
            ]);

            if ($specialtyIds) {
                $user->options()->syncWithoutDetaching($specialtyIds);
            }

            // The first address of the address book (customer AND business).
            $user->addresses()->create([
                'governorate_id' => (int) $data['governorate_id'],
                'city_id' => (int) $data['city_id'],
                'address_line' => $data['address_line'],
                'lat' => $data['latitude'] ?? null,
                'lng' => $data['longitude'] ?? null,
                'is_primary' => true,
            ]);

            return $user;
        });

        // Consent was just validated as accepted → record it against the current
        // terms + privacy versions (audit trail).
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
