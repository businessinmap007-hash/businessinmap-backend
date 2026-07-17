<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\AccountResource;
use App\Models\CategoryChild;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /**
     * GET /api/v2/profile/options — the attributes catalog for the business's
     * own specialty (category_child_id), with the ones it currently carries
     * marked selected. Attributes describe the BUSINESS, never priced alone.
     */
    public function showOptions(Request $request)
    {
        $user = $request->user();

        if ($user->type !== 'business') {
            abort(403, 'Only a business account has attributes.');
        }

        return response()->json(['success' => true, 'data' => $this->optionsPayload($user)]);
    }

    /** PATCH /api/v2/profile/options — a business sets which attributes describe it. */
    public function updateOptions(Request $request)
    {
        $user = $request->user();

        if ($user->type !== 'business') {
            abort(403, 'Only a business account has attributes to set.');
        }

        $data = $request->validate([
            'option_ids' => ['present', 'array'],
            'option_ids.*' => ['integer', 'min:1'],
        ]);

        $optionIds = array_values(array_unique(array_map('intval', $data['option_ids'])));

        $allowed = DB::table('category_child_option')
            ->where('child_id', (int) $user->category_child_id)
            ->pluck('option_id')
            ->all();

        $invalid = array_diff($optionIds, $allowed);

        if ($invalid) {
            throw ValidationException::withMessages([
                'option_ids' => ['هذه الخصائص لا تنتمي لتخصص نشاطك المُختار: ' . implode(', ', $invalid)],
            ]);
        }

        $user->options()->sync($optionIds);

        return response()->json(['success' => true, 'data' => $this->optionsPayload($user->fresh())]);
    }

    /** The option catalog for a business's specialty, marked with its current picks. */
    private function optionsPayload($user): array
    {
        $childId = (int) ($user->category_child_id ?? 0);
        $selected = $user->options()->pluck('options.id')->all();

        $options = $childId
            ? (CategoryChild::query()->find($childId)?->activeOptions()->with('group')->get() ?? collect())
            : collect();

        $groups = [];
        foreach ($options as $o) {
            $gid = (int) ($o->group_id ?? 0);
            $groups[$gid] ??= [
                'id' => $gid ?: null,
                'name' => $o->group?->displayName ?? '',
                'options' => [],
            ];
            $groups[$gid]['options'][] = [
                'id' => (int) $o->id,
                'name' => $o->displayName,
                'selected' => in_array((int) $o->id, $selected, true),
            ];
        }

        return [
            'child_id' => $childId ?: null,
            'groups' => array_values($groups),
            'selected_ids' => $selected,
        ];
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
