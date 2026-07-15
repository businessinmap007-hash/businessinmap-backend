<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\GuaranteeLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class GuaranteeLevelAdminController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $targetType = trim((string) $request->get('target_type', ''));
        $status = trim((string) $request->get('status', ''));
        $perPage = $this->normalizePerPage($request->get('per_page', 50));

        $query = GuaranteeLevel::query()
            ->withCount([
                'userGuarantees as purchased_guarantees_count',
                'effectiveUserGuarantees as effective_guarantees_count',
            ]);

        if ($q !== '') {
            $query->where(function (Builder $w) use ($q) {
                $w->where('id', $q)
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('name_ar', 'like', "%{$q}%")
                    ->orWhere('name_en', 'like', "%{$q}%");
            });
        }

        if (in_array($targetType, [GuaranteeLevel::TARGET_CLIENT, GuaranteeLevel::TARGET_BUSINESS], true)) {
            $query->where('target_type', $targetType);
        }

        if ($status === 'active') {
            $query->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0);
        }

        $levels = $query
            ->orderBy('target_type')
            ->orderByDesc('priority')
            ->orderBy('required_locked_amount')
            ->paginate($perPage)
            ->withQueryString();

        $totals = [
            'count' => GuaranteeLevel::query()->count(),
            'active' => GuaranteeLevel::query()->where('is_active', 1)->count(),
            'client' => GuaranteeLevel::query()->where('target_type', GuaranteeLevel::TARGET_CLIENT)->count(),
            'business' => GuaranteeLevel::query()->where('target_type', GuaranteeLevel::TARGET_BUSINESS)->count(),
        ];

        return view('admin-v2.guarantee-levels.index', [
            'levels' => $levels,
            'q' => $q,
            'targetType' => $targetType,
            'status' => $status,
            'perPage' => $perPage,
            'totals' => $totals,
        ]);
    }

    public function create()
    {
        $level = new GuaranteeLevel([
            'target_type' => GuaranteeLevel::TARGET_CLIENT,
            'required_locked_amount' => 0,
            'pending_coverage_amount' => 0,
            'active_coverage_amount' => 0,
            'required_completed_operations' => 0,
            'required_trust_score' => 0,
            'priority' => 0,
            'is_active' => true,
        ]);

        return view('admin-v2.guarantee-levels.create', [
            'level' => $level,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        GuaranteeLevel::create($data);

        return redirect()
            ->route('admin.guarantee-levels.index')
            ->with('success', 'تم إنشاء مستوى الضمان بنجاح.');
    }

    public function edit(GuaranteeLevel $guaranteeLevel)
    {
        return view('admin-v2.guarantee-levels.edit', [
            'level' => $guaranteeLevel,
        ]);
    }

    public function update(Request $request, GuaranteeLevel $guaranteeLevel)
    {
        $data = $this->validatedData($request, $guaranteeLevel);

        $guaranteeLevel->update($data);

        return redirect()
            ->route('admin.guarantee-levels.edit', $guaranteeLevel->id)
            ->with('success', 'تم تحديث مستوى الضمان بنجاح.');
    }

    public function destroy(GuaranteeLevel $guaranteeLevel)
    {
        if ($guaranteeLevel->userGuarantees()->exists() || $guaranteeLevel->effectiveUserGuarantees()->exists()) {
            return back()->withErrors('لا يمكن حذف مستوى مرتبط بضمانات مستخدمين. يمكنك تعطيله بدلًا من الحذف.');
        }

        $guaranteeLevel->delete();

        return redirect()
            ->route('admin.guarantee-levels.index')
            ->with('success', 'تم حذف مستوى الضمان.');
    }

    public function toggle(GuaranteeLevel $guaranteeLevel)
    {
        $guaranteeLevel->update([
            'is_active' => ! (bool) $guaranteeLevel->is_active,
        ]);

        return back()->with('success', 'تم تغيير حالة مستوى الضمان.');
    }

    private function validatedData(Request $request, ?GuaranteeLevel $level = null): array
    {
        $levelId = $level ? (int) $level->id : null;

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('guarantee_levels', 'code')->ignore($levelId),
            ],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'target_type' => ['required', Rule::in([GuaranteeLevel::TARGET_CLIENT, GuaranteeLevel::TARGET_BUSINESS])],
            'required_locked_amount' => ['required', 'numeric', 'min:0'],
            'pending_coverage_amount' => ['required', 'numeric', 'min:0'],
            'active_coverage_amount' => ['required', 'numeric', 'min:0'],
            'boost_coverage_amount' => ['nullable', 'numeric', 'min:0', 'gte:active_coverage_amount'],
            'boost_min_operations' => ['nullable', 'integer', 'min:0'],
            'boost_min_success_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'boost_max_dispute_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'required_completed_operations' => ['required', 'integer', 'min:0'],
            'required_trust_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_lost_disputes' => ['nullable', 'integer', 'min:0'],
            'max_late_cancellations' => ['nullable', 'integer', 'min:0'],
            'priority' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'meta_json' => ['nullable', 'string'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['max_lost_disputes'] = $data['max_lost_disputes'] === null || $data['max_lost_disputes'] === '' ? null : (int) $data['max_lost_disputes'];
        $data['max_late_cancellations'] = $data['max_late_cancellations'] === null || $data['max_late_cancellations'] === '' ? null : (int) $data['max_late_cancellations'];

        // Boost is optional: a blank coverage means "no reputation boost on this level".
        $data['boost_coverage_amount'] = ($data['boost_coverage_amount'] ?? '') === '' ? null : round((float) $data['boost_coverage_amount'], 2);
        $data['boost_min_operations'] = ($data['boost_min_operations'] ?? '') === '' ? null : (int) $data['boost_min_operations'];
        $data['boost_min_success_rate'] = ($data['boost_min_success_rate'] ?? '') === '' ? null : round((float) $data['boost_min_success_rate'], 2);
        $data['boost_max_dispute_rate'] = ($data['boost_max_dispute_rate'] ?? '') === '' ? null : round((float) $data['boost_max_dispute_rate'], 2);
        $data['meta'] = $this->decodeMeta($data['meta_json'] ?? null);
        unset($data['meta_json']);

        return $data;
    }

    private function decodeMeta(?string $metaJson): ?array
    {
        $metaJson = trim((string) $metaJson);

        if ($metaJson === '') {
            return null;
        }

        $decoded = json_decode($metaJson, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            abort(422, 'Meta JSON غير صالح.');
        }

        return $decoded;
    }

    private function normalizePerPage($perPage): int
    {
        $perPage = (int) $perPage;

        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 50;
    }
}
