<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessStaff;
use App\Models\User;
use App\Services\Business\BusinessAccessService;
use App\Support\BusinessCapability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    private function businessId(): int
    {
        return (int) Auth::id();
    }

    public function index(): View
    {
        return view('business.staff.index', [
            'staff' => $this->access->roster($this->businessId()),
            'capabilities' => BusinessCapability::registry(),
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
            $data['capabilities'],
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
            $data['capabilities'] ?? [],
            $request->boolean('is_active'),
        );

        return redirect()->route('business.staff.index')->with('success', 'تم تحديث صلاحيات الموظف.');
    }

    public function destroy(int $user): RedirectResponse
    {
        $this->access->remove($this->businessId(), $user);

        return redirect()->route('business.staff.index')->with('success', 'تمت إزالة الموظف.');
    }
}
