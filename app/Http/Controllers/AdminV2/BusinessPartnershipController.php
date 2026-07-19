<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BusinessPartnership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessPartnershipController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));
        $relationshipType = trim((string) $request->get('relationship_type', ''));
        $perPage = (int) $request->get('per_page', 50);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 50;

        $query = BusinessPartnership::query()
            ->with(['ownerBusiness:id,name,type,category_id,category_child_id,logo', 'partnerBusiness:id,name,type,category_id,category_child_id,logo'])
            ->withCount('allocations');

        if ($q !== '') {
            $query->where(function (Builder $w) use ($q) {
                if (is_numeric($q)) {
                    $w->orWhere('id', (int) $q)
                        ->orWhere('owner_business_id', (int) $q)
                        ->orWhere('partner_business_id', (int) $q);
                }

                $w->orWhereHas('ownerBusiness', function (Builder $b) use ($q) {
                    $b->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                })->orWhereHas('partnerBusiness', function (Builder $b) use ($q) {
                    $b->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            });
        }

        if ($status !== '' && array_key_exists($status, BusinessPartnership::statuses())) {
            $query->where('status', $status);
        }

        if ($relationshipType !== '' && array_key_exists($relationshipType, BusinessPartnership::relationshipTypes())) {
            $query->where('relationship_type', $relationshipType);
        }

        $rows = $query
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $totals = [
            'all' => BusinessPartnership::query()->count(),
            'active' => BusinessPartnership::query()->where('status', BusinessPartnership::STATUS_ACTIVE)->count(),
            'pending' => BusinessPartnership::query()->where('status', BusinessPartnership::STATUS_PENDING)->count(),
            'paused' => BusinessPartnership::query()->where('status', BusinessPartnership::STATUS_PAUSED)->count(),
        ];

        return view('admin-v2.business-partnerships.index', compact(
            'rows',
            'q',
            'status',
            'relationshipType',
            'perPage',
            'totals'
        ));
    }

    public function create()
    {
        return view('admin-v2.business-partnerships.create', [
            'partnership' => new BusinessPartnership([
                'relationship_type' => BusinessPartnership::TYPE_HOTEL_ALLOTMENT,
                'status' => BusinessPartnership::STATUS_PENDING,
                'approval_required' => true,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data['created_by'] = auth()->id();

        if (($data['status'] ?? null) === BusinessPartnership::STATUS_ACTIVE) {
            $data['approved_by'] = auth()->id();
            $data['approved_at'] = now();
        }

        $partnership = BusinessPartnership::create($data);

        return redirect()
            ->route('admin.business-partnerships.edit', $partnership->id)
            ->with('success', __('تم إنشاء الشراكة بنجاح.'));
    }

    public function edit(BusinessPartnership $businessPartnership)
    {
        $businessPartnership->load(['ownerBusiness', 'partnerBusiness']);

        return view('admin-v2.business-partnerships.edit', [
            'partnership' => $businessPartnership,
        ]);
    }

    public function update(Request $request, BusinessPartnership $businessPartnership)
    {
        $data = $this->validatedData($request, $businessPartnership);

        if (($data['status'] ?? null) === BusinessPartnership::STATUS_ACTIVE && ! $businessPartnership->approved_at) {
            $data['approved_by'] = auth()->id();
            $data['approved_at'] = now();
        }

        $businessPartnership->update($data);

        return redirect()
            ->route('admin.business-partnerships.edit', $businessPartnership->id)
            ->with('success', __('تم تحديث الشراكة بنجاح.'));
    }

    public function destroy(BusinessPartnership $businessPartnership)
    {
        if ($businessPartnership->allocations()->exists()) {
            return back()->withErrors(__('لا يمكن حذف شراكة لديها حصص Allocation. يمكن إيقافها بدلًا من الحذف.'));
        }

        $businessPartnership->delete();

        return redirect()
            ->route('admin.business-partnerships.index')
            ->with('success', __('تم حذف الشراكة.'));
    }

    public function activate(BusinessPartnership $businessPartnership)
    {
        $businessPartnership->update([
            'status' => BusinessPartnership::STATUS_ACTIVE,
            'approved_by' => auth()->id(),
            'approved_at' => $businessPartnership->approved_at ?: now(),
        ]);

        return back()->with('success', __('تم تفعيل الشراكة.'));
    }

    public function pause(BusinessPartnership $businessPartnership)
    {
        $businessPartnership->update([
            'status' => BusinessPartnership::STATUS_PAUSED,
        ]);

        return back()->with('success', __('تم إيقاف الشراكة مؤقتًا.'));
    }

    private function validatedData(Request $request, ?BusinessPartnership $partnership = null): array
    {
        $data = $request->validate([
            'owner_business_id' => ['required', 'integer', 'exists:users,id'],
            'partner_business_id' => ['required', 'integer', 'exists:users,id', 'different:owner_business_id'],
            'relationship_type' => ['required', Rule::in(array_keys(BusinessPartnership::relationshipTypes()))],
            'status' => ['required', Rule::in(array_keys(BusinessPartnership::statuses()))],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'approval_required' => ['nullable', 'boolean'],
            'terms_json' => ['nullable', 'string'],
            'meta_json' => ['nullable', 'string'],
        ]);

        $owner = User::query()->where('id', (int) $data['owner_business_id'])->where('type', User::TYPE_BUSINESS)->exists();
        $partner = User::query()->where('id', (int) $data['partner_business_id'])->where('type', User::TYPE_BUSINESS)->exists();

        if (! $owner || ! $partner) {
            abort(422, __('المالك والشريك يجب أن يكونا من نوع business.'));
        }

        $data['approval_required'] = $request->boolean('approval_required');
        $data['terms'] = $this->decodeJson($data['terms_json'] ?? null);
        $data['meta'] = $this->decodeJson($data['meta_json'] ?? null);
        unset($data['terms_json'], $data['meta_json']);

        return $data;
    }

    private function decodeJson(?string $json): ?array
    {
        $json = trim((string) $json);

        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            abort(422, __('JSON غير صالح.'));
        }

        return $decoded;
    }
}
