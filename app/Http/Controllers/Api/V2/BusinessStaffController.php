<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\BusinessStaff;
use App\Models\Order;
use App\Models\StaffActivityLog;
use App\Models\User;
use App\Services\Business\BusinessAccessService;
use App\Services\Business\StaffAttendanceService;
use App\Services\DeliveryDispatchService;
use App\Services\Notifications\NotificationDispatcherService;
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
        private readonly NotificationDispatcherService $notifications,
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

        // A person needs to confirm their own invitation before it activates
        // (see resolveContext()'s status gate) - notify only while it's
        // still pending, never on a re-save of an already-accepted grant.
        if ($staff->status === BusinessStaff::STATUS_PENDING) {
            $this->notifyInvited($request->user(), $staff);
        }

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
            // A staff row with zero capabilities is invisible on the Staff
            // Groups screen (it groups people BY capability) while staying
            // fully active everywhere else - min:1 matches store()'s own
            // rule so an edit can never leave a member in that state.
            'capabilities' => ['nullable', 'array', 'min:1'],
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

    /** GET /api/v2/staff/invitations — invitations I haven't answered yet. */
    public function invitations(Request $request)
    {
        $rows = $this->access->pendingInvitationsFor((int) $request->user()->id)
            ->map(fn (BusinessStaff $s) => [
                'business_id' => (int) $s->business_id,
                'business' => $s->business ? [
                    'id' => (int) $s->business->id,
                    'name' => $s->business->name,
                    'phone' => $s->business->phone,
                    'logo' => $s->business->logo,
                ] : null,
                'title' => $s->title,
                'capabilities' => BusinessCapability::sanitize((array) $s->capabilities),
            ]);

        return response()->json(['success' => true, 'data' => ['invitations' => $rows]]);
    }

    /** POST /api/v2/staff/invitations/{business}/accept — I accept the grant. */
    public function accept(Request $request, int $business)
    {
        $staff = $this->access->accept($business, (int) $request->user()->id);

        abort_if(! $staff, 404);

        $this->notifyResponse($request->user(), $staff, accepted: true);
        $this->resolveInviteNotification((int) $request->user()->id, $business, accepted: true);

        return response()->json([
            'success' => true,
            'message' => __('تم قبول الدعوة.'),
            'data' => ['staff' => $this->serialize($staff->fresh('user'))],
        ]);
    }

    /** POST /api/v2/staff/invitations/{business}/decline — I decline it. */
    public function decline(Request $request, int $business)
    {
        $staff = $this->access->decline($business, (int) $request->user()->id);

        abort_if(! $staff, 404);

        $this->notifyResponse($request->user(), $staff, accepted: false);
        $this->resolveInviteNotification((int) $request->user()->id, $business, accepted: false);

        return response()->json([
            'success' => true,
            'message' => __('تم رفض الدعوة.'),
            'data' => ['staff' => $this->serialize($staff->fresh('user'))],
        ]);
    }

    /**
     * Rewrites the invited person's OWN "you've been invited" notification
     * in place once they respond — its action_type moves off
     * `open_staff_invitation` so tapping it again never re-opens the
     * accept/decline popup, and its title/body say what was decided instead
     * of still reading like an open invitation.
     */
    private function resolveInviteNotification(int $staffUserId, int $businessId, bool $accepted): void
    {
        try {
            $businessName = trim((string) (User::query()->find($businessId)?->name ?? ''));

            AppNotification::query()
                ->where('user_id', $staffUserId)
                ->where('action_type', 'open_staff_invitation')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $businessId)
                ->latest('id')
                ->limit(1)
                ->update([
                    'title_ar' => $accepted ? 'تم قبول دعوة العمل' : 'تم رفض دعوة العمل',
                    'title_en' => $accepted ? 'Work invitation accepted' : 'Work invitation declined',
                    'body_ar' => trim(($accepted ? 'قبلت دعوة العمل من ' : 'رفضت دعوة العمل من ') . $businessName . '.'),
                    'body_en' => trim(($accepted ? 'You accepted the work invitation from ' : 'You declined the work invitation from ') . $businessName . '.'),
                    'action_type' => 'open_business',
                ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Tells the invited person a grant is waiting — tapping opens the business's page. */
    private function notifyInvited(User $business, BusinessStaff $staff): void
    {
        try {
            $businessName = trim((string) ($business->name ?? ''));

            $this->notifications->dispatch('staff_invited', (int) $staff->user_id, [
                'type' => AppNotification::TYPE_SYSTEM,
                'actor_id' => (int) $business->id,
                'title_ar' => 'دعوة للانضمام كموظف',
                'title_en' => 'Staff invitation',
                'body_ar' => trim(($businessName !== '' ? $businessName . ' ' : '') . 'يدعوك للانضمام كموظف لديه.'),
                'body_en' => trim(($businessName !== '' ? $businessName . ': ' : '') . 'invited you to join as staff.'),
                'action_type' => 'open_staff_invitation',
                'notifiable_type' => User::class,
                'notifiable_id' => (int) $business->id,
                'source_type' => 'staff_invited',
                'source_id' => (int) $staff->id,
                'meta' => ['business_id' => (int) $business->id],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Tells the business owner whether the invitation was accepted or declined. */
    private function notifyResponse(User $staffUser, BusinessStaff $staff, bool $accepted): void
    {
        try {
            $name = trim((string) ($staffUser->name ?? ''));

            $this->notifications->dispatch($accepted ? 'staff_accepted' : 'staff_declined', (int) $staff->business_id, [
                'type' => AppNotification::TYPE_SYSTEM,
                'actor_id' => (int) $staffUser->id,
                'title_ar' => $accepted ? 'تم قبول دعوة الموظف' : 'تم رفض دعوة الموظف',
                'title_en' => $accepted ? 'Staff invitation accepted' : 'Staff invitation declined',
                'body_ar' => trim(($name !== '' ? $name . ' ' : '') . ($accepted ? 'قبل الانضمام كموظف.' : 'رفض الانضمام كموظف.')),
                'body_en' => trim(($name !== '' ? $name . ' ' : '') . ($accepted ? 'accepted the staff invitation.' : 'declined the staff invitation.')),
                'action_type' => 'open_staff_member',
                'notifiable_type' => User::class,
                'notifiable_id' => (int) $staffUser->id,
                'source_type' => $accepted ? 'staff_accepted' : 'staff_declined',
                'source_id' => (int) $staff->id,
                'meta' => ['user_id' => (int) $staffUser->id],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
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

        $cardFor = function (BusinessStaff $s) use ($opsToday, $attendanceToday, $driverStatus) {
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

            if (in_array(BusinessCapability::DRIVERS, (array) $s->capabilities, true) && isset($driverStatus[(int) $user->id])) {
                $card['delivery_status'] = $driverStatus[(int) $user->id];
            }

            return $card;
        };

        $groups = [];
        $seenUserIds = [];
        foreach (BusinessCapability::registry() as $key => [$nameAr, $nameEn]) {
            $members = $roster->filter(fn (BusinessStaff $s) => in_array($key, (array) $s->capabilities, true));
            if ($members->isEmpty()) {
                continue;
            }

            foreach ($members as $member) {
                $seenUserIds[(int) $member->user_id] = true;
            }

            $groups[] = [
                'capability' => $key,
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'staff' => $members->map($cardFor)->values(),
            ];
        }

        // A staff row whose capabilities are empty, or name a capability key
        // that no longer exists in the registry (e.g. after a taxonomy
        // rename), matches no group above and used to vanish from this
        // screen entirely - fully active and checkable-in, but invisible
        // here. Surface them instead of losing them silently.
        $uncategorized = $roster->reject(fn (BusinessStaff $s) => isset($seenUserIds[(int) $s->user_id]));
        if ($uncategorized->isNotEmpty()) {
            $groups[] = [
                'capability' => null,
                'name_ar' => 'غير مصنف',
                'name_en' => 'Uncategorized',
                'staff' => $uncategorized->map($cardFor)->values(),
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
                // Tells the app whether checking in for THIS membership needs
                // a QR scan + GPS first (StaffAttendanceController::checkIn).
                'attendance_verification_enabled' => (bool) ($s->business?->attendance_verification_enabled ?? false),
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
            'status' => (string) $s->status,
        ];
    }
}
