<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\AccountResource;
use App\Models\CategoryChild;
use App\Services\Media\ImageUploadService;
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
    public function __construct(private readonly ImageUploadService $uploads) {}

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
            // Business only: a customer's single box already takes either script.
            'name_en' => ['sometimes', 'nullable', 'string', 'max:191'],
            'phone' => ['sometimes', 'string', 'max:15', Rule::unique('users', 'phone')->ignore($user->id)],
            'about' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            // `category_children` does not exist — the table is
            // `category_children_master`. The rule threw a QueryException, so
            // every profile update carrying a category_child_id answered 500.
            // The same mistake was already found and fixed in AuthController.
            'category_child_id' => ['sometimes', 'nullable', 'integer', 'exists:category_children_master,id'],
        ]);

        // A customer has one name box, so an English name posted to a client
        // account is dropped rather than stored where nothing will read it.
        if (! $user->isBusiness()) {
            unset($data['name_en']);
        }

        if (! empty($data)) {
            $user->fill($data)->save();
        }

        return response()->json(['success' => true, 'data' => new AccountResource($user->fresh())]);
    }

    /**
     * POST /api/v2/profile/image — set or remove the account photo.
     * POST (not PUT/PATCH) because a file upload needs multipart, which PHP
     * cannot decode on those verbs. Send `remove=1` with no file to clear it.
     */
    public function updateImage(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'image' => ['required_without:remove', ...ImageUploadService::validationRules()],
            'remove' => ['sometimes', 'boolean'],
        ]);

        $previous = $user->image;

        if ($request->hasFile('image')) {
            $user->image = $this->uploads->store($request->file('image'));
        } elseif ($request->boolean('remove')) {
            $user->image = null;
        }

        $user->save();
        $this->uploads->delete($previous);

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

        // Scoped to the business's ROOT as well as its child: the same child
        // carries a different attribute set under a different root (a furniture
        // factory is not asked what a furniture showroom is asked), and
        // category_child_option.category_id = 0 means "under every root".
        $allowed = DB::table('category_child_option')
            ->where('child_id', (int) $user->category_child_id)
            ->when(
                (int) ($user->category_id ?? 0) > 0,
                fn ($q) => $q->whereIn('category_id', [0, (int) $user->category_id])
            )
            ->pluck('option_id')
            ->all();

        $invalid = array_diff($optionIds, $allowed);

        if ($invalid) {
            throw ValidationException::withMessages([
                'option_ids' => [__('هذه الخصائص لا تنتمي لتخصص نشاطك المُختار: ') . implode(', ', $invalid)],
            ]);
        }

        $user->options()->sync($optionIds);

        return response()->json(['success' => true, 'data' => $this->optionsPayload($user->fresh())]);
    }

    /** The option catalog for a business's specialty, marked with its current picks. */
    private function optionsPayload($user): array
    {
        $childId = (int) ($user->category_child_id ?? 0);
        $rootId = (int) ($user->category_id ?? 0);
        $selected = $user->options()->pluck('options.id')->all();

        $options = $childId
            ? (CategoryChild::query()->find($childId)?->activeOptionsForParent($rootId)->with('group')->get() ?? collect())
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
            'password' => \App\Support\PasswordPolicy::rules(),
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => [__('كلمة المرور الحالية غير صحيحة.')]]);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        // Keep the current token; drop the rest for safety.
        $currentId = optional($user->currentAccessToken())->id;
        $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->delete();

        return response()->json(['success' => true]);
    }
}
