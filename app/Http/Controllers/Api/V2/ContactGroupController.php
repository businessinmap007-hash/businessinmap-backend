<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ContactGroup;
use App\Models\ContactGroupMember;
use App\Support\UserLookup;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A user's own named contact groups ("العائلة", "أصدقاء دمياط") — a reusable
 * address book of already-registered friends, so sharing a cart with the
 * same circle repeatedly doesn't mean re-typing a phone/email every time.
 * Purely personal: nothing here is visible to anyone but the owner, and
 * membership never implies consent — inviting a group to a cart
 * (SharedCartController::inviteGroup) still goes through the same
 * notify-then-join flow as inviting one friend at a time.
 */
final class ContactGroupController extends Controller
{
    public function index(Request $request)
    {
        $groups = $request->user()->contactGroups()
            ->withCount('members')
            ->with('members.user:id,name')
            ->latest()
            ->get()
            ->map(fn (ContactGroup $g) => $this->serialize($g));

        return response()->json(['success' => true, 'data' => ['groups' => $groups]]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ], [], ['name' => __('اسم المجموعة')]);

        $group = $request->user()->contactGroups()->create(['name' => $data['name']]);

        return response()->json(['success' => true, 'data' => ['group' => $this->serialize($group)]], 201);
    }

    public function update(Request $request, int $group)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ], [], ['name' => __('اسم المجموعة')]);

        $model = $this->ownedOrFail($request, $group);
        $model->update(['name' => $data['name']]);

        return response()->json(['success' => true, 'data' => ['group' => $this->serialize($model->fresh(['members.user:id,name'])->loadCount('members'))]]);
    }

    public function destroy(Request $request, int $group)
    {
        $this->ownedOrFail($request, $group)->delete();

        return response()->json(['success' => true]);
    }

    /** Adds an already-registered user (found by phone or email) to the group. */
    public function addMember(Request $request, int $group)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:190'],
        ], [], ['identifier' => __('رقم الهاتف أو الإيميل')]);

        $model = $this->ownedOrFail($request, $group);

        $friend = UserLookup::byIdentifier($data['identifier']);
        if (! $friend) {
            throw ValidationException::withMessages(['identifier' => [__('لم يُعثر على مستخدم بهذا الرقم أو الإيميل.')]]);
        }
        if ((int) $friend->id === (int) $request->user()->id) {
            throw ValidationException::withMessages(['identifier' => [__('لا يمكنك إضافة نفسك.')]]);
        }
        if ($model->members()->where('user_id', $friend->id)->exists()) {
            throw ValidationException::withMessages(['identifier' => [__('الشخص ده موجود بالفعل في المجموعة.')]]);
        }

        $model->members()->create(['user_id' => $friend->id]);

        return response()->json(['success' => true, 'data' => ['group' => $this->serialize($model->fresh(['members.user:id,name'])->loadCount('members'))]], 201);
    }

    public function removeMember(Request $request, int $group, int $member)
    {
        $model = $this->ownedOrFail($request, $group);
        $model->members()->whereKey($member)->delete();

        return response()->json(['success' => true, 'data' => ['group' => $this->serialize($model->fresh(['members.user:id,name'])->loadCount('members'))]]);
    }

    private function ownedOrFail(Request $request, int $groupId): ContactGroup
    {
        $model = $request->user()->contactGroups()->find($groupId);
        if (! $model) {
            abort(404, __('المجموعة غير موجودة.'));
        }

        return $model;
    }

    private function serialize(ContactGroup $group): array
    {
        return [
            'id' => (int) $group->id,
            'name' => (string) $group->name,
            'members_count' => (int) ($group->members_count ?? $group->members()->count()),
            'members' => $group->relationLoaded('members')
                ? $group->members->map(fn (ContactGroupMember $m) => [
                    'id' => (int) $m->id,
                    'user_id' => (int) $m->user_id,
                    'name' => (string) ($m->user->name ?? ''),
                ])->values()
                : [],
        ];
    }
}
