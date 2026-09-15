<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BusinessStaff;
use App\Models\Order;
use App\Models\StaffActivityLog;
use App\Models\User;
use App\Services\Business\BusinessAccessService;
use App\Services\Business\StaffAttendanceService;
use App\Services\DeliveryDispatchService;
use App\Support\BusinessCapability;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * A business owner delegates management of its page to staff (a clinic
 * secretary, a shop/restaurant employee), each limited to a set of capabilities
 * drawn from the one shared services registry. Roster management is owner-only
 * (staff cannot manage staff); the memberships endpoint lets a delegate list the
 * businesses they may act for.
 */
class BusinessStaffController extends Controller
{
    public function __construct(
        private readonly BusinessAccessService $access,
        private readonly StaffAttendanceService $attendance,
        private readonly DeliveryDispatchService $delivery,
    ) {
    }

    /** GET /api/v2/business/capabilities — ما يستطيع هذا النشاط تفويضه. */
    public function capabilities(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['capabilities' => BusinessCapability::catalogFor($request->user())]]);
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

        // Hiding the checkbox isn't enough: the request is sent by hand as
        // easily as the app sends it, and whoever sends it grants their
        // employee a permission they themselves don't have -- see the web
        // Business\StaffController::store()'s own note on this.
        $staff = $this->access->upsert(
            $businessId,
            (int) $user->id,
            $data['title'] ?? null,
            BusinessCapability::sanitizeFor($request->user(), $data['capabilities']),
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
            BusinessCapability::sanitizeFor($request->user(), $data['capabilities'] ?? (array) $existing->capabilities),
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

    /**
     * GET /api/v2/business/staff-activity — the end-of-shift review, as JSON
     * for the app. Owner-only, mirrors Business\StaffController::activity().
     * Defaults to today; also returns a per-staff operation count for the
     * selected period so the owner can see who did the most at a glance.
     */
    public function activity(Request $request)
    {
        $businessId = (int) $request->user()->id;

        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : Carbon::today();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : Carbon::now();
        $userId = $request->filled('user_id') ? (int) $request->input('user_id') : null;

        $base = StaffActivityLog::query()
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [$from, $to]);

        $rows = (clone $base)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->with('user:id,name,phone')
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (StaffActivityLog $log) => [
                'id' => (int) $log->id,
                'created_at' => $log->created_at?->toIso8601String(),
                'user_id' => (int) $log->user_id,
                'user_name' => optional($log->user)->name,
                'is_owner' => (int) $log->user_id === $businessId,
                'capability' => $log->capability,
                'action' => $log->action,
                'subject_type' => $log->subject_type === Order::class ? 'order' : 'booking',
                'subject_id' => (int) $log->subject_id,
            ]);

        $counts = (clone $base)->selectRaw('user_id, count(*) as total')->groupBy('user_id')->pluck('total', 'user_id');

        $summary = $this->access->roster($businessId)->pluck('user')->filter()
            ->push($request->user())->unique('id')->values()
            ->map(fn (User $actor) => [
                'user_id' => (int) $actor->id,
                'name' => $actor->name,
                'is_owner' => (int) $actor->id === $businessId,
                'count' => (int) ($counts[$actor->id] ?? 0),
            ])
            ->sortByDesc('count')->values();

        return response()->json([
            'success' => true,
            'data' => [
                'rows' => $rows,
                'summary' => $summary,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'selected_user_id' => $userId,
            ],
        ]);
    }

    /**
     * GET /api/v2/business/staff/groups — the roster grouped by capability,
     * each card carrying what the owner needs at a glance: today's operation
     * count (StaffActivityLog), today's attendance (StaffAttendanceService),
     * and — drivers only — the live delivery workload
     * (DeliveryDispatchService::businessRoster). A staff member with several
     * capabilities appears once per group, not once overall: the group IS
     * "what they're doing here", so it can differ by role.
     */
    public function groups(Request $request)
    {
        $businessId = (int) $request->user()->id;
        $roster = $this->access->roster($businessId)->filter(fn (BusinessStaff $s) => $s->user);

        $userIds = $roster->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $opsToday = StaffActivityLog::query()
            ->where('business_id', $businessId)
            ->whereDate('created_at', Carbon::today())
            ->whereIn('user_id', $userIds)
            ->selectRaw('user_id, count(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $attendanceToday = $this->attendance->todayForMany($businessId, $userIds);

        $needsDriverStatus = $roster->contains(
            fn (BusinessStaff $s) => in_array(BusinessCapability::DRIVERS, (array) $s->capabilities, true)
        );
        $driverStatus = [];
        if ($needsDriverStatus) {
            foreach ($this->delivery->businessRoster($businessId) as $d) {
                $driverStatus[(int) $d['user_id']] = [
                    'busy' => (bool) $d['busy'],
                    'active_order_count' => (int) $d['active_order_count'],
                ];
            }
        }

        $groups = [];
        foreach (BusinessCapability::registry() as $key => [$nameAr, $nameEn]) {
            $members = $roster->filter(fn (BusinessStaff $s) => in_array($key, (array) $s->capabilities, true));
            if ($members->isEmpty()) {
                continue;
            }

            $groups[] = [
                'capability' => $key,
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'staff' => $members->map(function (BusinessStaff $s) use ($opsToday, $attendanceToday, $driverStatus, $key) {
                    $user = $s->user;
                    $att = $attendanceToday->get((int) $user->id);

                    $card = [
                        'user_id' => (int) $user->id,
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'logo' => $user->logo,
                        'title' => $s->title,
                        'is_active' => (bool) $s->is_active,
                        'operations_today' => (int) ($opsToday[$user->id] ?? 0),
                        'attendance' => [
                            'checked_in_at' => optional($att?->checked_in_at)->toIso8601String(),
                            'checked_out_at' => optional($att?->checked_out_at)->toIso8601String(),
                            'is_present' => $att?->isPresent() ?? false,
                        ],
                    ];

                    if ($key === BusinessCapability::DRIVERS && isset($driverStatus[(int) $user->id])) {
                        $card['delivery_status'] = $driverStatus[(int) $user->id];
                    }

                    return $card;
                })->values(),
            ];
        }

        return response()->json(['success' => true, 'data' => ['groups' => $groups]]);
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
