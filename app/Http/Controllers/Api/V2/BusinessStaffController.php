<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BusinessStaff;
use App\Models\User;
use App\Services\Business\BusinessAccessService;
use App\Support\BusinessCapability;
use Illuminate\Http\Request;

/**
 * A business owner delegates management of its page to staff (a clinic
 * secretary, a shop/restaurant employee), each limited to a set of capabilities
 * drawn from the one shared services registry. Roster management is owner-only
 * (staff cannot manage staff); the memberships endpoint lets a delegate list the
 * businesses they may act for.
 */
class BusinessStaffController extends Controller
{
    public function __construct(private readonly BusinessAccessService $access)
    {
    }

    /** GET /api/v2/business/capabilities — the one place: all manageable services. */
    public function capabilities()
    {
        return response()->json(['success' => true, 'data' => ['capabilities' => BusinessCapability::catalog()]]);
    }

    /** GET /api/v2/business/staff — my delegated staff. */
    public function index(Request $request)
    {
        $rows = $this->access->roster((int) $request->user()->id)
            ->map(fn (BusinessStaff $s) => $this->serialize($s));

        return response()->json(['success' => true, 'data' => ['staff' => $rows]]);
    }

    /** POST /api/v2/business/staff — grant (or update) a staff member. */
    public function store(Request $request)
    {
        $businessId = (int) $request->user()->id;

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'required_without:user_id'],
            'title' => ['nullable', 'string', 'max:120'],
            'capabilities' => ['required', 'array', 'min:1'],
            'capabilities.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = $this->resolveUser($data, $businessId);

        $staff = $this->access->upsert(
            $businessId,
            (int) $user->id,
            $data['title'] ?? null,
            $data['capabilities'],
            (bool) ($data['is_active'] ?? true),
        );

        return response()->json([
            'success' => true,
            'message' => __('تم منح الموظف صلاحيات إدارة النشاط.'),
            'data' => ['staff' => $this->serialize($staff->fresh('user'))],
        ], 201);
    }

    /** PATCH /api/v2/business/staff/{user} — change a staff member's grant. */
    public function update(Request $request, int $user)
    {
        $businessId = (int) $request->user()->id;

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $existing = BusinessStaff::query()
            ->where('business_id', $businessId)
            ->where('user_id', $user)
            ->firstOrFail();

        $staff = $this->access->upsert(
            $businessId,
            $user,
            array_key_exists('title', $data) ? $data['title'] : $existing->title,
            $data['capabilities'] ?? (array) $existing->capabilities,
            array_key_exists('is_active', $data) ? (bool) $data['is_active'] : (bool) $existing->is_active,
        );

        return response()->json([
            'success' => true,
            'message' => __('تم تحديث صلاحيات الموظف.'),
            'data' => ['staff' => $this->serialize($staff->fresh('user'))],
        ]);
    }

    /** DELETE /api/v2/business/staff/{user} — revoke a staff member. */
    public function destroy(Request $request, int $user)
    {
        $this->access->remove((int) $request->user()->id, $user);

        return response()->json(['success' => true, 'message' => __('تمت إزالة الموظف.')]);
    }

    /** GET /api/v2/business/memberships — businesses I may manage as staff. */
    public function memberships(Request $request)
    {
        $rows = $this->access->membershipsFor((int) $request->user()->id)
            ->map(fn (BusinessStaff $s) => [
                'business' => $s->business ? [
                    'id' => (int) $s->business->id,
                    'name' => $s->business->name,
                    'logo' => $s->business->logo,
                ] : ['id' => (int) $s->business_id],
                'title' => $s->title,
                'capabilities' => BusinessCapability::sanitize((array) $s->capabilities),
            ]);

        return response()->json(['success' => true, 'data' => ['memberships' => $rows]]);
    }

    private function resolveUser(array $data, int $businessId): User
    {
        $user = ! empty($data['user_id'])
            ? User::query()->find((int) $data['user_id'])
            : User::query()->where('phone', trim((string) $data['phone']))->first();

        abort_unless($user, 422, __('لم يُعثر على المستخدم.'));
        abort_if((int) $user->id === $businessId, 422, __('لا يمكن للنشاط تعيين نفسه موظفًا.'));

        return $user;
    }

    private function serialize(BusinessStaff $s): array
    {
        return [
            'user' => $s->user ? [
                'id' => (int) $s->user->id,
                'name' => $s->user->name,
                'phone' => $s->user->phone,
                'logo' => $s->user->logo,
            ] : ['id' => (int) $s->user_id],
            'title' => $s->title,
            'capabilities' => BusinessCapability::sanitize((array) $s->capabilities),
            'is_active' => (bool) $s->is_active,
        ];
    }
}
