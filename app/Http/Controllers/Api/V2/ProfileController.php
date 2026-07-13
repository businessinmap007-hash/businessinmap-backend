<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\AccountResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * v2 profile — the authenticated user reads and edits their own account
 * (replaces the legacy Api\V1 ProfileController without its social/options/
 * device coupling). Auth is a Sanctum token on the auth:sanctum group.
 */
final class ProfileController extends Controller
{
    /** GET /api/v2/profile */
    public function show(Request $request)
    {
        return response()->json(['success' => true, 'data' => new AccountResource($request->user())]);
    }

    /** PATCH /api/v2/profile — edit basic account fields. */
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'phone' => ['sometimes', 'string', 'max:15', Rule::unique('users', 'phone')->ignore($user->id)],
            'about' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'category_child_id' => ['sometimes', 'nullable', 'integer', 'exists:category_children,id'],
        ]);

        if (! empty($data)) {
            $user->fill($data)->save();
        }

        return response()->json(['success' => true, 'data' => new AccountResource($user->fresh())]);
    }

    /** POST /api/v2/profile/password — change password (revokes other tokens). */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['كلمة المرور الحالية غير صحيحة.']]);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        // Keep the current token; drop the rest for safety.
        $currentId = optional($user->currentAccessToken())->id;
        $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->delete();

        return response()->json(['success' => true]);
    }
}
