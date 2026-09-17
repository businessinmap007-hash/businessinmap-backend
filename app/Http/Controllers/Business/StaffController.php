<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessStaff;
use App\Models\StaffActivityLog;
use App\Models\User;
use App\Services\Business\BusinessAccessService;
use App\Support\BusinessCapability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The business owner's staff-delegation screen: grant an employee (a clinic
 * secretary, a shop/restaurant worker) a set of capabilities from the one
 * shared services registry, then edit or revoke it. Scoped to the logged-in
 * owner (business_id === Auth::id()), mirroring the API in BusinessStaffController.
 */
class StaffController extends Controller
{
    public function __construct(private readonly BusinessAccessService $access)
    {
    }

    /** النشاط الذى تُدار صلاحياته — تصنيفُه هو ما يحدّ ما يستطيع تفويضه. */
    private function actingBusiness(): ?User
    {
        return \App\Support\BusinessContext::business(request()) ?: Auth::user();
    }

    private function businessId(): int
    {
        return (int) Auth::id();
    }

    public function index(): View
    {
        return view('business.staff.index', [
            'staff' => $this->access->roster($this->businessId()),
            'capabilities' => BusinessCapability::forBusiness($this->actingBusiness()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'required_without:user_id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:phone'],
            'title' => ['nullable', 'string', 'max:120'],
            'capabilities' => ['required', 'array', 'min:1'],
            'capabilities.*' => ['string'],
        ], [], [
            'phone' => 'الهاتف',
            'capabilities' => 'الصلاحيات',
        ]);

        $user = ! empty($data['user_id'])
            ? User::query()->find((int) $data['user_id'])
            : User::query()->where('phone', trim((string) $data['phone']))->first();

        if (! $user) {
            return back()->withInput()->withErrors(['phone' => 'لم يُعثر على مستخدم بهذا الهاتف.']);
        }
        if ((int) $user->id === $this->businessId()) {
            return back()->withInput()->withErrors(['phone' => 'لا يمكنك تعيين نفسك موظفًا.']);
        }

        $this->access->upsert(
            $this->businessId(),
            (int) $user->id,
            $data['title'] ?? null,
            // إخفاءُ المربّع لا يكفى: الحفظ يُرسَل، ومن يرسله بيده يمنح
            // موظّفه صلاحيةً لا يملكها هو.
            BusinessCapability::sanitizeFor($this->actingBusiness(), $data['capabilities']),
            true,
        );

        return redirect()->route('business.staff.index')->with('success', 'تم منح الموظف صلاحيات إدارة النشاط.');
    }

    public function update(Request $request, int $user): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
        ], [], ['capabilities' => 'الصلاحيات']);

        $existing = BusinessStaff::query()
            ->where('business_id', $this->businessId())
            ->where('user_id', $user)
            ->firstOrFail();

        $this->access->upsert(
            $this->businessId(),
            $user,
            $data['title'] ?? $existing->title,
            BusinessCapability::sanitizeFor($this->actingBusiness(), $data['capabilities'] ?? []),
            $request->boolean('is_active'),
        );

        return redirect()->route('business.staff.index')->with('success', 'تم تحديث صلاحيات الموظف.');
    }

    public function destroy(int $user): RedirectResponse
    {
        $this->access->remove($this->businessId(), $user);

        return redirect()->route('business.staff.index')->with('success', 'تمت إزالة الموظف.');
    }

    /**
     * "من عمل ماذا" — the end-of-shift review: every staff-attributed action
     * (StaffActivityLogger) on this business, newest first, filterable by
     * staff member and date range. Defaults to today, matching how an owner
     * actually uses this — closing out a shift, not auditing history.
     */
    public function activity(Request $request): View
    {
        $businessId = $this->businessId();

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
            ->with(['user:id,name,phone'])
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        // Distinct orders/bookings touched, not raw log rows - one order
        // that went accepted -> preparing -> ready wrote 3 rows but is one
        // operation, not three (the list above still shows every row).
        $counts = (clone $base)
            ->selectRaw("user_id, COUNT(DISTINCT CONCAT(subject_type, '|', subject_id)) as total")
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        // The roster (+ the owner themselves, who can also act directly) for
        // the filter dropdown and the per-staff operation-count summary.
        $actors = $this->access->roster($businessId)
            ->pluck('user')
            ->filter()
            ->push(Auth::user())
            ->unique('id')
            ->values();

        $summary = $actors->map(fn (User $actor) => [
            'user' => $actor,
            'count' => (int) ($counts[$actor->id] ?? 0),
        ])->sortByDesc('count')->values();

        return view('business.staff.activity', [
            'rows' => $rows,
            'actors' => $actors,
            'summary' => $summary,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'selectedUserId' => $userId,
        ]);
    }
}
