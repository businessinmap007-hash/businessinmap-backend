<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\FeeGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «مجموعات الرسوم» — a shared platform-fee rate several children can point at
 * instead of each carrying its own. See `App\Models\FeeGroup` and
 * `CategoryChildServiceFee::effectiveFeeSource()`.
 */
class FeeGroupController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));

        $rows = FeeGroup::query()
            ->withCount('members')
            ->when($q !== '', fn ($query) => $query->where('name_ar', 'like', '%' . $q . '%'))
            ->orderBy('name_ar')
            ->paginate(50)
            ->withQueryString();

        return view('admin-v2.fee-groups.index', ['rows' => $rows, 'q' => $q]);
    }

    public function create(): View
    {
        return view('admin-v2.fee-groups.create', ['row' => new FeeGroup(['is_active' => 1])]);
    }

    public function store(Request $request): RedirectResponse
    {
        FeeGroup::create($this->validated($request));

        return redirect()->route('admin.fee-groups.index')->with('success', __('تم إنشاء مجموعة الرسوم.'));
    }

    public function edit(FeeGroup $feeGroup): View
    {
        return view('admin-v2.fee-groups.edit', ['row' => $feeGroup]);
    }

    public function update(Request $request, FeeGroup $feeGroup): RedirectResponse
    {
        $feeGroup->update($this->validated($request));

        return back()->with('success', __('تم حفظ مجموعة الرسوم.'));
    }

    /** Refused while any child still points at this group — see FeeGroup::members(). */
    public function destroy(FeeGroup $feeGroup): RedirectResponse
    {
        $count = $feeGroup->members()->count();

        if ($count > 0) {
            return back()->with('error', __('هذه المجموعة تُستخدم من :count ابن — انقلهم إلى مجموعة أخرى أو رسمٍ فردى أولًا.', ['count' => $count]));
        }

        $feeGroup->delete();

        return redirect()->route('admin.fee-groups.index')->with('success', __('تم حذف مجموعة الرسوم.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:191'],
            'business_fee_enabled' => ['nullable'],
            'business_fee_type' => ['required', Rule::in(['fixed', 'percent'])],
            'business_fee_amount' => ['required', 'numeric', 'min:0'],
            'client_fee_enabled' => ['nullable'],
            'client_fee_type' => ['required', Rule::in(['fixed', 'percent'])],
            'client_fee_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'is_active' => ['nullable'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], [
            'name_ar' => __('الاسم'),
            'business_fee_amount' => __('قيمة رسوم البزنس'),
            'client_fee_amount' => __('قيمة رسوم العميل'),
        ]);

        return [
            'name_ar' => trim($data['name_ar']),
            'business_fee_enabled' => $request->boolean('business_fee_enabled'),
            'business_fee_type' => $data['business_fee_type'],
            'business_fee_amount' => $data['business_fee_amount'],
            'client_fee_enabled' => $request->boolean('client_fee_enabled'),
            'client_fee_type' => $data['client_fee_type'],
            'client_fee_amount' => $data['client_fee_amount'],
            'currency' => strtoupper(trim((string) ($data['currency'] ?? ''))) ?: 'EGP',
            'is_active' => $request->boolean('is_active'),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }
}
