<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BusinessGroup;
use App\Models\BusinessGroupMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A business's own named groups of OTHER businesses ("محلات الخضار",
 * "مصانع الأثاث") — a reusable target list for wholesale/retail offers.
 * Mirrors ContactGroupController; members here are added by business id
 * directly (no phone/email lookup needed — the app already knows the id,
 * either from search or from that business's own profile page), and the
 * app expands a chosen group into individual `audience_business_ids` when
 * saving a restricted retail listing, so the listing/audience layer itself
 * carries no notion of "groups" at all — see [[retail-wholesale-visibility]].
 */
final class BusinessGroupController extends Controller
{
    public function index(Request $request)
    {
        $groups = $request->user()->businessGroups()
            ->withCount('members')
            ->with('members.business:id,name,name_en,logo')
            ->latest()
            ->get()
            ->map(fn (BusinessGroup $g) => $this->serialize($g));

        return response()->json(['success' => true, 'data' => ['groups' => $groups]]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ], [], ['name' => __('اسم المجموعة')]);

        $group = $request->user()->businessGroups()->create(['name' => $data['name']]);

        return response()->json(['success' => true, 'data' => ['group' => $this->serialize($group)]], 201);
    }

    public function update(Request $request, int $group)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ], [], ['name' => __('اسم المجموعة')]);

        $model = $this->ownedOrFail($request, $group);
        $model->update(['name' => $data['name']]);

        return response()->json(['success' => true, 'data' => ['group' => $this->serialize($model->fresh(['members.business:id,name,name_en,logo'])->loadCount('members'))]]);
    }

    public function destroy(Request $request, int $group)
    {
        $this->ownedOrFail($request, $group)->delete();

        return response()->json(['success' => true]);
    }

    /** Adds a business (by id) to the group — either from search or from that business's own profile page. */
    public function addMember(Request $request, int $group)
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer'],
        ], [], ['business_id' => __('النشاط')]);

        $model = $this->ownedOrFail($request, $group);

        $business = User::query()->where('type', 'business')->find($data['business_id']);
        if (! $business) {
            throw ValidationException::withMessages(['business_id' => [__('النشاط غير موجود.')]]);
        }
        if ((int) $business->id === (int) $request->user()->id) {
            throw ValidationException::withMessages(['business_id' => [__('لا يمكنك إضافة نشاطك الخاص.')]]);
        }
        if ($model->members()->where('business_id', $business->id)->exists()) {
            throw ValidationException::withMessages(['business_id' => [__('هذا النشاط موجود بالفعل في المجموعة.')]]);
        }

        $model->members()->create(['business_id' => $business->id]);

        return response()->json(['success' => true, 'data' => ['group' => $this->serialize($model->fresh(['members.business:id,name,name_en,logo'])->loadCount('members'))]], 201);
    }

    public function removeMember(Request $request, int $group, int $member)
    {
        $model = $this->ownedOrFail($request, $group);
        $model->members()->whereKey($member)->delete();

        return response()->json(['success' => true, 'data' => ['group' => $this->serialize($model->fresh(['members.business:id,name,name_en,logo'])->loadCount('members'))]]);
    }

    private function ownedOrFail(Request $request, int $groupId): BusinessGroup
    {
        $model = $request->user()->businessGroups()->find($groupId);
        if (! $model) {
            abort(404, __('المجموعة غير موجودة.'));
        }

        return $model;
    }

    private function serialize(BusinessGroup $group): array
    {
        return [
            'id' => (int) $group->id,
            'name' => (string) $group->name,
            'members_count' => (int) ($group->members_count ?? $group->members()->count()),
            'members' => $group->relationLoaded('members')
                ? $group->members->map(fn (BusinessGroupMember $m) => [
                    'id' => (int) $m->id,
                    'business_id' => (int) $m->business_id,
                    'name' => (string) ($m->business?->displayName() ?? ''),
                    'logo' => $m->business->logo ?? null,
                ])->values()
                : [],
        ];
    }
}
