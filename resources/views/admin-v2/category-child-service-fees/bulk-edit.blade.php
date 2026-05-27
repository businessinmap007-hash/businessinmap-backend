@extends('admin-v2.layouts.master')

@section('title', 'الخدمات ورسومها للمحدد')
@section('body_class', 'admin-v2 admin-v2-category-child-service-fees-bulk')

@section('content')
@php
    $parentIdInt = (int) ($parentId ?? 0);

    $parentName = $parent
        ? ($parent->name_ar ?: ($parent->name_en ?: ('#' . $parent->id)))
        : '—';

    $children = collect($children ?? []);
    $childIds = collect($childIds ?? [])->map(fn($id) => (int) $id)->filter()->values();
    $services = collect($services ?? []);
    $existingFees = collect($existingFees ?? []);
    $activeChildServiceMap = collect($activeChildServiceMap ?? []);
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">الخدمات ورسومها للمحدد</h1>

            <div class="a2-page-subtitle">
                <div>
                    <strong>القسم الرئيسي:</strong>
                    {{ $parentName }}
                    <span class="a2-muted">#{{ $parentIdInt }}</span>
                </div>

                <div class="a2-mt-8">
                    <strong>عدد الأقسام الفرعية المحددة:</strong>
                    {{ $children->count() }}
                </div>
            </div>
        </div>

        <div class="a2-page-actions">
            <button type="button" class="a2-btn a2-btn-ghost js-bulk-check-all">
                تحديد كل الخدمات
            </button>

            <button type="button" class="a2-btn a2-btn-ghost js-bulk-uncheck-all">
                إلغاء الكل
            </button>

            <a
                href="{{ route('admin.categories.index', ['root_id' => $parentIdInt]) }}"
                class="a2-btn a2-btn-ghost"
            >
                رجوع إلى الأقسام
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="a2-alert a2-alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            <div class="a2-fw-900 a2-mb-8">يوجد بعض الأخطاء، راجع البيانات التالية:</div>

            <ul style="margin:0;padding-inline-start:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="a2-card a2-card--soft a2-mb-16">
        <div class="a2-section-title">الأقسام الفرعية المحددة</div>
        <div class="a2-section-subtitle">
            سيتم تطبيق الربط والرسوم على الأقسام الفرعية التالية فقط.
        </div>

        <div class="a2-page-actions a2-mt-12">
            @foreach($children as $child)
                <span class="a2-pill a2-pill-gray">
                    {{ $child->name_ar ?: ($child->name_en ?: ('#' . $child->id)) }}
                    #{{ $child->id }}
                </span>
            @endforeach
        </div>
    </div>

    <div class="a2-card a2-card--soft a2-mb-16">
        <div class="a2-section-title">طريقة العمل</div>

        <div class="a2-kv-grid a2-kv-grid-3 a2-mt-12">
            <div class="a2-kv-box">
                <span>ربط الخدمة</span>
                <strong>
                    يعني تفعيل الخدمة داخل
                    <span dir="ltr">category_platform_services</span>.
                </strong>
            </div>

            <div class="a2-kv-box">
                <span>إلغاء الربط</span>
                <strong>
                    يعني تعطيل الخدمة لهذا القسم الفرعي وتعطيل رسومها وتصفير رسوم البزنس والمستخدم.
                </strong>
            </div>

            <div class="a2-kv-box">
                <span>رسوم الخدمة</span>
                <strong>
                    يتم حفظها داخل
                    <span dir="ltr">category_child_service_fees</span>
                    وتستخدم عند
                    <span dir="ltr">in_progress</span>.
                </strong>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.category-child-service-fees.bulk.update') }}" class="a2-card">
        @csrf

        <input type="hidden" name="parent_id" value="{{ $parentIdInt }}">

        @foreach($childIds as $childId)
            <input type="hidden" name="child_ids[]" value="{{ $childId }}">
        @endforeach

        @if($services->isEmpty())
            <div class="a2-empty-cell">
                لا توجد خدمات مفعلة في النظام حاليًا.
            </div>

            <div class="a2-page-actions a2-mt-16">
                <a
                    href="{{ route('admin.categories.index', ['root_id' => $parentIdInt]) }}"
                    class="a2-btn a2-btn-ghost"
                >
                    رجوع
                </a>
            </div>
        @else
            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                    <tr>
                        <th style="min-width:270px;">القسم الفرعي / الخدمة</th>
                        <th style="min-width:130px;">ربط الخدمة</th>
                        <th style="min-width:120px;">تفعيل الرسوم</th>

                        <th style="min-width:120px;">رسوم البزنس</th>
                        <th style="min-width:140px;">قيمة البزنس</th>

                        <th style="min-width:120px;">رسوم المستخدم</th>
                        <th style="min-width:140px;">قيمة المستخدم</th>

                        <th style="min-width:90px;">العملة</th>
                        <th style="min-width:90px;">الترتيب</th>
                        <th style="min-width:280px;">ملاحظات</th>
                    </tr>
                    </thead>

                    <tbody>
                    @foreach($children as $child)
                        @php
                            $childId = (int) $child->id;
                            $childName = $child->name_ar ?: ($child->name_en ?: ('#' . $childId));
                            $childActiveServices = $activeChildServiceMap->get($childId, []);
                            $childActiveServices = is_array($childActiveServices)
                                ? $childActiveServices
                                : collect($childActiveServices)->map(fn($id) => (int) $id)->all();
                        @endphp

                        @foreach($services as $service)
                            @php
                                $serviceId = (int) $service->id;
                                $key = $childId . ':' . $serviceId;

                                $feeCollection = $existingFees->get($key, collect());
                                $fee = $feeCollection instanceof \Illuminate\Support\Collection
                                    ? $feeCollection->first()
                                    : null;

                                $serviceName = $service->name_ar
                                    ?: ($service->name_en ?: ($service->key ?: ('#' . $serviceId)));

                                $oldBase = 'rows.' . $childId . '.' . $serviceId . '.';

                                $childHasServiceAlready = in_array($serviceId, $childActiveServices, true);

                                $rowEnabled = old(
                                    $oldBase . 'row_enabled',
                                    $childHasServiceAlready ? 1 : 0
                                );

                                $rowIsEnabled = ((int) $rowEnabled === 1);

                                $isActive = old(
                                    $oldBase . 'is_active',
                                    isset($fee->is_active) ? (int) $fee->is_active : ($rowIsEnabled ? 1 : 0)
                                );

                                $businessFeeEnabled = old(
                                    $oldBase . 'business_fee_enabled',
                                    isset($fee->business_fee_enabled) ? (int) $fee->business_fee_enabled : 0
                                );

                                $clientFeeEnabled = old(
                                    $oldBase . 'client_fee_enabled',
                                    isset($fee->client_fee_enabled) ? (int) $fee->client_fee_enabled : 0
                                );

                                $businessFeeAmount = old(
                                    $oldBase . 'business_fee_amount',
                                    isset($fee->business_fee_amount) ? (string) $fee->business_fee_amount : '0.00'
                                );

                                $clientFeeAmount = old(
                                    $oldBase . 'client_fee_amount',
                                    isset($fee->client_fee_amount) ? (string) $fee->client_fee_amount : '0.00'
                                );

                                $currency = old(
                                    $oldBase . 'currency',
                                    isset($fee->currency) ? (string) $fee->currency : 'EGP'
                                );

                                $sortOrder = old(
                                    $oldBase . 'sort_order',
                                    isset($fee->sort_order) ? (int) $fee->sort_order : 0
                                );

                                $notes = old(
                                    $oldBase . 'notes',
                                    isset($fee->notes) ? (string) $fee->notes : ''
                                );

                                $hasAnyFee = ((int) $businessFeeEnabled === 1 && (float) $businessFeeAmount > 0)
                                    || ((int) $clientFeeEnabled === 1 && (float) $clientFeeAmount > 0);

                                $feesOn = $hasAnyFee && ((int) $isActive === 1);
                            @endphp

                            <tr class="js-service-row {{ $rowIsEnabled ? '' : 'is-disabled-row' }}" data-service-id="{{ $serviceId }}">
                                <td class="a2-text-right">
                                    <div class="a2-fw-900">
                                        {{ $childName }}
                                        <span class="a2-muted">#{{ $childId }}</span>
                                    </div>

                                    <div class="a2-muted a2-mt-8">
                                        {{ $serviceName }}
                                        <span class="a2-muted">#{{ $serviceId }}</span>
                                    </div>

                                    <div class="a2-muted a2-mt-8" dir="ltr">
                                        {{ $service->key ?: '—' }}
                                    </div>

                                    @if($fee)
                                        <div class="a2-muted a2-mt-8">
                                            Fee Row: {{ $fee->id }}
                                        </div>
                                    @endif

                                    <div class="a2-mt-8 a2-inline-actions">
                                        @if($rowIsEnabled)
                                            <span class="a2-pill a2-pill-success">Service ON</span>
                                        @else
                                            <span class="a2-pill a2-pill-gray">Service OFF</span>
                                        @endif

                                        @if($feesOn)
                                            <span class="a2-pill a2-pill-success">Fees ON</span>
                                        @else
                                            <span class="a2-pill a2-pill-gray">Fees OFF</span>
                                        @endif

                                        @if(isset($service->supports_deposit))
                                            @if($service->supports_deposit)
                                                <span class="a2-pill a2-pill-success">Deposit ON</span>
                                                <span class="a2-pill a2-pill-gray">
                                                    Max: {{ (int) $service->max_deposit_percent }}%
                                                </span>
                                            @else
                                                <span class="a2-pill a2-pill-gray">Deposit OFF</span>
                                            @endif
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    <label class="a2-check" style="justify-content:center;">
                                        <input
                                            type="checkbox"
                                            class="js-row-enabled"
                                            name="rows[{{ $childId }}][{{ $serviceId }}][row_enabled]"
                                            value="1"
                                            @checked($rowIsEnabled)
                                        >
                                        <span>مطلوبة</span>
                                    </label>
                                </td>

                                <td>
                                    <label class="a2-check" style="justify-content:center;">
                                        <input
                                            type="checkbox"
                                            class="js-row-field"
                                            name="rows[{{ $childId }}][{{ $serviceId }}][is_active]"
                                            value="1"
                                            @checked((int) $isActive === 1)
                                            @disabled(! $rowIsEnabled)
                                        >
                                        <span>مفعل</span>
                                    </label>
                                </td>

                                <td>
                                    <label class="a2-check" style="justify-content:center;">
                                        <input
                                            type="checkbox"
                                            class="js-row-field"
                                            name="rows[{{ $childId }}][{{ $serviceId }}][business_fee_enabled]"
                                            value="1"
                                            @checked((int) $businessFeeEnabled === 1)
                                            @disabled(! $rowIsEnabled)
                                        >
                                        <span>تشغيل</span>
                                    </label>
                                </td>

                                <td>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="a2-input js-row-field"
                                        name="rows[{{ $childId }}][{{ $serviceId }}][business_fee_amount]"
                                        value="{{ $businessFeeAmount }}"
                                        placeholder="0.00"
                                        dir="ltr"
                                        @disabled(! $rowIsEnabled)
                                    >
                                </td>

                                <td>
                                    <label class="a2-check" style="justify-content:center;">
                                        <input
                                            type="checkbox"
                                            class="js-row-field"
                                            name="rows[{{ $childId }}][{{ $serviceId }}][client_fee_enabled]"
                                            value="1"
                                            @checked((int) $clientFeeEnabled === 1)
                                            @disabled(! $rowIsEnabled)
                                        >
                                        <span>تشغيل</span>
                                    </label>
                                </td>

                                <td>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="a2-input js-row-field"
                                        name="rows[{{ $childId }}][{{ $serviceId }}][client_fee_amount]"
                                        value="{{ $clientFeeAmount }}"
                                        placeholder="0.00"
                                        dir="ltr"
                                        @disabled(! $rowIsEnabled)
                                    >
                                </td>

                                <td>
                                    <input
                                        type="text"
                                        class="a2-input js-row-field"
                                        name="rows[{{ $childId }}][{{ $serviceId }}][currency]"
                                        value="{{ $currency }}"
                                        maxlength="3"
                                        placeholder="EGP"
                                        dir="ltr"
                                        style="text-transform:uppercase;"
                                        @disabled(! $rowIsEnabled)
                                    >
                                </td>

                                <td>
                                    <input
                                        type="number"
                                        min="0"
                                        class="a2-input js-row-field"
                                        name="rows[{{ $childId }}][{{ $serviceId }}][sort_order]"
                                        value="{{ $sortOrder }}"
                                        placeholder="0"
                                        dir="ltr"
                                        @disabled(! $rowIsEnabled)
                                    >
                                </td>

                                <td>
                                    <textarea
                                        class="a2-textarea js-row-field"
                                        name="rows[{{ $childId }}][{{ $serviceId }}][notes]"
                                        rows="2"
                                        placeholder="ملاحظات اختيارية"
                                        style="min-height:80px;"
                                        @disabled(! $rowIsEnabled)
                                    >{{ $notes }}</textarea>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="a2-card a2-card--soft a2-card--tight a2-mt-16">
                <div class="a2-section-title">ملاحظات تشغيلية</div>
                <div class="a2-section-subtitle">
                    عند إلغاء ربط خدمة، سيتم تعطيل صف الخدمة داخل
                    <span dir="ltr">category_platform_services</span>
                    وتعطيل الرسوم المرتبطة بها داخل
                    <span dir="ltr">category_child_service_fees</span>.
                    ولو كانت قيمة رسوم البزنس أو المستخدم صفرًا سيتم تعطيل رسوم هذا الطرف تلقائيًا.
                </div>
            </div>

            <div class="a2-page-actions a2-mt-16">
                <button type="submit" class="a2-btn a2-btn-primary">
                    حفظ الخدمات والرسوم
                </button>

                <a
                    href="{{ route('admin.categories.index', ['root_id' => $parentIdInt]) }}"
                    class="a2-btn a2-btn-ghost"
                >
                    رجوع
                </a>
            </div>
        @endif
    </form>

    <div class="a2-card a2-card--soft a2-mt-16">
        <div class="a2-section-title">ملاحظة تشغيلية</div>
        <div class="a2-section-subtitle">
            إذا دخل الحجز حالة
            <span dir="ltr">in_progress</span>
            وتم خصم رسوم الخدمة، فلا يتم رد هذه الرسوم تلقائيًا عند إلغاء الحجز لاحقًا.
            أي رد لاحق يجب أن يتم عبر سياسة نزاع أو إجراء مالي مستقل.
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = Array.from(document.querySelectorAll('.js-service-row'));

    function refreshRows() {
        rows.forEach(function (row) {
            const rowEnabledInput = row.querySelector('.js-row-enabled');
            const enabled = !!(rowEnabledInput && rowEnabledInput.checked);

            row.classList.toggle('is-disabled-row', !enabled);
            row.style.opacity = enabled ? '1' : '.45';
            row.style.background = enabled ? '' : '#fafafa';

            row.querySelectorAll('.js-row-field').forEach(function (field) {
                const isCheckbox = field.type === 'checkbox';

                field.disabled = !enabled;

                if (!enabled && isCheckbox) {
                    field.checked = false;
                }
            });
        });
    }

    document.querySelectorAll('.js-row-enabled').forEach(function (el) {
        el.addEventListener('change', refreshRows);
    });

    document.querySelector('.js-bulk-check-all')?.addEventListener('click', function () {
        document.querySelectorAll('.js-row-enabled').forEach(function (el) {
            el.checked = true;
        });
        refreshRows();
    });

    document.querySelector('.js-bulk-uncheck-all')?.addEventListener('click', function () {
        if (!confirm('هل تريد إلغاء ربط كل الخدمات المعروضة؟')) {
            return;
        }

        document.querySelectorAll('.js-row-enabled').forEach(function (el) {
            el.checked = false;
        });
        refreshRows();
    });

    refreshRows();
});
</script>
@endpush