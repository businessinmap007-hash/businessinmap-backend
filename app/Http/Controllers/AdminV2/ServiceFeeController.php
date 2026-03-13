<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\PlatformService;
use App\Models\ServiceFee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServiceFeeController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    private function normalizePerPage($perPage): int
    {
        $perPage = (int) $perPage;

        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 50;
    }

    private function keepQs(Request $request): array
    {
        return $request->only([
            'q',
            'business_id',
            'service_id',
            'fee_code',
            'is_active',
            'per_page',
        ]);
    }

    public function index(Request $request)
    {
        $q          = trim((string) $request->get('q', ''));
        $businessId = (string) $request->get('business_id', '');
        $serviceId  = (string) $request->get('service_id', '');
        $feeCode    = (string) $request->get('fee_code', '');
        $isActive   = (string) $request->get('is_active', '');
        $perPage    = $this->normalizePerPage($request->get('per_page', 50));

        $rows = ServiceFee::query()
            ->select([
                'business_id',
                'service_id',
                'fee_code',
                DB::raw('MAX(id) as id'),
                DB::raw('MAX(updated_at) as updated_at'),
                DB::raw('COUNT(*) as rows_count'),
                DB::raw('SUM(CASE WHEN payer = "business" THEN 1 ELSE 0 END) as has_business_fee'),
                DB::raw('SUM(CASE WHEN payer = "client" THEN 1 ELSE 0 END) as has_client_fee'),
                DB::raw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count'),
            ])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('fee_code', 'like', "%{$q}%")
                        ->orWhere('currency', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%");
                });
            })
            ->when($businessId !== '', function ($qq) use ($businessId) {
                if ($businessId === 'global') {
                    $qq->whereNull('business_id');
                } else {
                    $qq->where('business_id', $businessId);
                }
            })
            ->when($serviceId !== '', function ($qq) use ($serviceId) {
                if ($serviceId === 'global') {
                    $qq->whereNull('service_id');
                } else {
                    $qq->where('service_id', $serviceId);
                }
            })
            ->when($feeCode !== '', fn ($qq) => $qq->where('fee_code', $feeCode))
            ->when($isActive !== '', function ($qq) use ($isActive) {
                if ($isActive === '1') {
                    $qq->where('is_active', 1);
                } elseif ($isActive === '0') {
                    $qq->where('is_active', 0);
                }
            })
            ->groupBy('business_id', 'service_id', 'fee_code')
            ->orderByDesc(DB::raw('MAX(id)'))
            ->paginate($perPage)
            ->appends($this->keepQs($request));

        $businesses = $this->businesses();
        $services   = $this->services();

        return view('admin-v2.service-fees.index', [
            'rows' => $rows,
            'q' => $q,
            'businessId' => $businessId,
            'serviceId' => $serviceId,
            'feeCode' => $feeCode,
            'isActive' => $isActive,
            'perPage' => $perPage,
            'businesses' => $businesses,
            'services' => $services,
            'feeCodeOptions' => $this->feeCodeOptions(),
        ]);
    }

    public function create()
    {
        $businesses = $this->businesses();
        $services   = $this->services();
        $defaults   = $this->defaultFeeData();

        return view('admin-v2.service-fees.create', [
            'businesses' => $businesses,
            'services' => $services,
            'feeCodeOptions' => $this->feeCodeOptions(),
            'feeTypeOptions' => $this->feeTypeOptions(),
            'calcTypeOptions' => $this->calcTypeOptions(),
            'businessFee' => $defaults['business'],
            'clientFee' => $defaults['client'],
            'mode' => 'create',
            'groupKey' => null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateSetup($request);

        DB::transaction(function () use ($data) {
            $this->upsertPayerFee('business', $data);
            $this->upsertPayerFee('client', $data);
        });

        return redirect()
            ->route('admin.service-fees.index')
            ->with('success', 'تم حفظ إعدادات الرسوم للطرفين بنجاح');
    }

    public function show(Request $request)
    {
        [$businessId, $serviceId, $feeCode] = $this->resolveGroupKey($request);

        $fees = ServiceFee::query()
            ->where(function ($q) use ($businessId) {
                if ($businessId === null) {
                    $q->whereNull('business_id');
                } else {
                    $q->where('business_id', $businessId);
                }
            })
            ->where(function ($q) use ($serviceId) {
                if ($serviceId === null) {
                    $q->whereNull('service_id');
                } else {
                    $q->where('service_id', $serviceId);
                }
            })
            ->where('fee_code', $feeCode)
            ->get()
            ->keyBy('payer');

        abort_if($fees->isEmpty(), 404);

        $businessFee = $fees->get('business');
        $clientFee   = $fees->get('client');

        $business = $businessId ? User::find($businessId) : null;
        $service  = $serviceId ? PlatformService::find($serviceId) : null;

        return view('admin-v2.service-fees.show', [
           'businessFee' => $businessFee,
    'clientFee' => $clientFee,
    'business' => $business,
    'service' => $service,
    'feeCode' => $feeCode,
    'groupKey' => $this->buildGroupKey($businessId, $serviceId, $feeCode),
    'services' => $this->services(),
        ]);
    }

    public function edit(Request $request)
    {
        [$businessId, $serviceId, $feeCode] = $this->resolveGroupKey($request);

        $fees = ServiceFee::query()
            ->where(function ($q) use ($businessId) {
                if ($businessId === null) {
                    $q->whereNull('business_id');
                } else {
                    $q->where('business_id', $businessId);
                }
            })
            ->where(function ($q) use ($serviceId) {
                if ($serviceId === null) {
                    $q->whereNull('service_id');
                } else {
                    $q->where('service_id', $serviceId);
                }
            })
            ->where('fee_code', $feeCode)
            ->get()
            ->keyBy('payer');

        abort_if($fees->isEmpty(), 404);

        $businesses = $this->businesses();
        $services   = $this->services();

        return view('admin-v2.service-fees.edit', [
            'businesses' => $businesses,
            'services' => $services,
            'feeCodeOptions' => $this->feeCodeOptions(),
            'feeTypeOptions' => $this->feeTypeOptions(),
            'calcTypeOptions' => $this->calcTypeOptions(),
            'businessFee' => $fees->get('business') ?: $this->defaultSingleFee('business'),
            'clientFee' => $fees->get('client') ?: $this->defaultSingleFee('client'),
            'mode' => 'edit',
            'groupKey' => $this->buildGroupKey($businessId, $serviceId, $feeCode),
        ]);
    }

    public function update(Request $request)
    {
        $data = $this->validateSetup($request);

        DB::transaction(function () use ($data) {
            $this->upsertPayerFee('business', $data);
            $this->upsertPayerFee('client', $data);
        });

        return redirect()
            ->route('admin.service-fees.show', [
                'business_id' => $data['business_id'],
                'service_id' => $data['service_id'],
                'fee_code' => $data['fee_code'],
            ])
            ->with('success', 'تم تحديث إعدادات الرسوم للطرفين بنجاح');
    }

    public function destroy(Request $request)
    {
        [$businessId, $serviceId, $feeCode] = $this->resolveGroupKey($request);

        ServiceFee::query()
            ->where(function ($q) use ($businessId) {
                if ($businessId === null) {
                    $q->whereNull('business_id');
                } else {
                    $q->where('business_id', $businessId);
                }
            })
            ->where(function ($q) use ($serviceId) {
                if ($serviceId === null) {
                    $q->whereNull('service_id');
                } else {
                    $q->where('service_id', $serviceId);
                }
            })
            ->where('fee_code', $feeCode)
            ->delete();

        return redirect()
            ->route('admin.service-fees.index')
            ->with('success', 'تم حذف إعداد الرسوم بالكامل');
    }

    public function toggleActive(Request $request)
    {
        [$businessId, $serviceId, $feeCode] = $this->resolveGroupKey($request);

        $fees = ServiceFee::query()
            ->where(function ($q) use ($businessId) {
                if ($businessId === null) {
                    $q->whereNull('business_id');
                } else {
                    $q->where('business_id', $businessId);
                }
            })
            ->where(function ($q) use ($serviceId) {
                if ($serviceId === null) {
                    $q->whereNull('service_id');
                } else {
                    $q->where('service_id', $serviceId);
                }
            })
            ->where('fee_code', $feeCode)
            ->get();

        abort_if($fees->isEmpty(), 404);

        $newState = ! $fees->every(fn ($row) => (bool) $row->is_active);

        foreach ($fees as $row) {
            $row->update(['is_active' => $newState]);
        }

        return back()->with('success', 'تم تحديث حالة الإعداد');
    }

    private function validateSetup(Request $request): array
    {
        $data = $request->validate([
            'business_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_id'  => ['nullable', 'integer', 'exists:platform_services,id'],
            'fee_code'    => ['required', 'string', 'max:100', Rule::in(array_keys($this->feeCodeOptions()))],

            'business.fee_type'   => ['required', 'string', Rule::in(array_keys($this->feeTypeOptions()))],
            'business.calc_type'  => ['required', 'string', Rule::in(array_keys($this->calcTypeOptions()))],
            'business.amount'     => ['nullable', 'numeric', 'min:0'],
            'business.min_amount' => ['nullable', 'numeric', 'min:0'],
            'business.max_amount' => ['nullable', 'numeric', 'min:0'],
            'business.currency'   => ['nullable', 'string', 'size:3'],
            'business.priority'   => ['nullable', 'integer', 'min:0'],
            'business.is_active'  => ['nullable'],
            'business.notes'      => ['nullable', 'string'],
            'business.rules'      => ['nullable', 'string'],

            'client.fee_type'   => ['required', 'string', Rule::in(array_keys($this->feeTypeOptions()))],
            'client.calc_type'  => ['required', 'string', Rule::in(array_keys($this->calcTypeOptions()))],
            'client.amount'     => ['nullable', 'numeric', 'min:0'],
            'client.min_amount' => ['nullable', 'numeric', 'min:0'],
            'client.max_amount' => ['nullable', 'numeric', 'min:0'],
            'client.currency'   => ['nullable', 'string', 'size:3'],
            'client.priority'   => ['nullable', 'integer', 'min:0'],
            'client.is_active'  => ['nullable'],
            'client.notes'      => ['nullable', 'string'],
            'client.rules'      => ['nullable', 'string'],
        ], [], [
            'business_id' => 'البزنس',
            'service_id' => 'الخدمة',
            'fee_code' => 'كود الرسم',
            'business.amount' => 'قيمة رسوم البزنس',
            'client.amount' => 'قيمة رسوم العميل',
        ]);

        $data['business']['is_active'] = $request->boolean('business.is_active');
        $data['client']['is_active']   = $request->boolean('client.is_active');
        $data['business']['rules']     = $this->decodeRules($request->input('business.rules'));
        $data['client']['rules']       = $this->decodeRules($request->input('client.rules'));

        return $data;
    }

    private function upsertPayerFee(string $payer, array $data): void
    {
        $payload = $data[$payer];

        $businessId = $data['business_id'] ?: null;
        $serviceId  = $data['service_id'] ?: null;
        $feeCode    = $data['fee_code'];

        $row = ServiceFee::query()
            ->where('payer', $payer)
            ->where('fee_code', $feeCode)
            ->where(function ($q) use ($businessId) {
                if ($businessId === null) {
                    $q->whereNull('business_id');
                } else {
                    $q->where('business_id', $businessId);
                }
            })
            ->where(function ($q) use ($serviceId) {
                if ($serviceId === null) {
                    $q->whereNull('service_id');
                } else {
                    $q->where('service_id', $serviceId);
                }
            })
            ->first();

        $values = [
            'fee_code'    => $feeCode,
            'business_id' => $businessId,
            'service_id'  => $serviceId,
            'payer'       => $payer,
            'fee_type'    => $payload['fee_type'],
            'calc_type'   => $payload['calc_type'],
            'amount'      => $payload['amount'] ?? 0,
            'min_amount'  => $payload['min_amount'] ?? null,
            'max_amount'  => $payload['max_amount'] ?? null,
            'currency'    => strtoupper($payload['currency'] ?? 'EGP'),
            'priority'    => $payload['priority'] ?? 100,
            'is_active'   => $payload['is_active'] ?? true,
            'notes'       => $payload['notes'] ?? null,
            'rules'       => $payload['rules'] ?? null,
        ];

        if ($row) {
            $row->update($values);
            return;
        }

        ServiceFee::create($values);
    }

    private function decodeRules($value): ?array
    {
        if (blank($value)) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function resolveGroupKey(Request $request): array
    {
        $businessId = $request->get('business_id');
        $serviceId  = $request->get('service_id');
        $feeCode    = $request->get('fee_code');

        abort_unless($feeCode, 404);

        return [
            ($businessId !== '' && $businessId !== null) ? (int) $businessId : null,
            ($serviceId !== '' && $serviceId !== null) ? (int) $serviceId : null,
            $feeCode,
        ];
    }

    private function buildGroupKey($businessId, $serviceId, $feeCode): array
    {
        return [
            'business_id' => $businessId,
            'service_id' => $serviceId,
            'fee_code' => $feeCode,
        ];
    }

    private function defaultFeeData(): array
    {
        return [
            'business' => $this->defaultSingleFee('business'),
            'client' => $this->defaultSingleFee('client'),
        ];
    }

    private function defaultSingleFee(string $payer): ServiceFee
    {
        $row = new ServiceFee();
        $row->payer = $payer;
        $row->fee_code = 'booking_execution';
        $row->fee_type = 'platform_fee';
        $row->calc_type = 'fixed';
        $row->currency = 'EGP';
        $row->priority = 100;
        $row->is_active = 1;
        $row->amount = 0;

        return $row;
    }

    private function businesses()
    {
        return User::query()
            ->select(['id', 'name', 'code'])
            ->where('type', 'business')
            ->orderByDesc('id')
            ->limit(500)
            ->get();
    }

    private function services()
    {
        return PlatformService::query()
            ->select(['id', 'name_ar', 'name_en', 'key', 'is_active'])
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->get();
    }

    private function feeCodeOptions(): array
    {
        return [
            'booking_execution' => 'booking_execution',
            'booking_deposit' => 'booking_deposit',
            'booking_cancel' => 'booking_cancel',
            'dispute_freeze' => 'dispute_freeze',
            'dispute_resolution' => 'dispute_resolution',
            'wallet_recharge' => 'wallet_recharge',
            'wallet_withdraw' => 'wallet_withdraw',
        ];
    }

    private function feeTypeOptions(): array
    {
        return [
            'platform_fee' => 'Platform Fee',
            'business_fee' => 'Business Fee',
            'client_fee' => 'Client Fee',
        ];
    }

    private function calcTypeOptions(): array
    {
        return [
            'fixed' => 'Fixed',
            'percent' => 'Percent',
        ];
    }
}