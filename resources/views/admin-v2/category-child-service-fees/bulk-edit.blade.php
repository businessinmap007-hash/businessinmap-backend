@extends('admin-v2.layouts.master')

@section('title', 'الخدمات ورسومها للمحدد')
@section('body_class', 'admin-v2-category-child-service-fees-bulk')

@section('content')
@php
    $parentName = $parent->name_ar ?: ($parent->name_en ?: ('#' . $parent->id));
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">الخدمات ورسومها للمحدد</h1>
            <div class="a2-page-subtitle">
                <div><strong>القسم الرئيسي:</strong> {{ $parentName }}</div>
                <div class="a2-mt-8"><strong>عدد الأبناء المحددين:</strong> {{ $children->count() }}</div>
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.categories.index', ['root_id' => $parentId]) }}"
               class="a2-btn a2-btn-ghost">
                رجوع
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            <ul style="margin:0;padding-inline-start:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="a2-card a2-card--soft a2-mb-16">
        <div class="a2-section-title">الأبناء المحددون</div>
        <div class="a2-section-subtitle">ستُطبّق الخدمات ورسومها على الأبناء المختارين فقط</div>

        <div class="a2-page-actions">
            @foreach($children as $child)
                <span class="a2-pill a2-pill-gray">
                    {{ $child->name_ar ?: ($child->name_en ?: ('#' . $child->id)) }}
                </span>
            @endforeach
        </div>
    </div>

    <form method="POST" action="{{ route('admin.category-child-service-fees.bulk.update') }}" class="a2-card">
        @csrf

        <input type="hidden" name="parent_id" value="{{ $parentId }}">

        @foreach($childIds as $childId)
            <input type="hidden" name="child_ids[]" value="{{ $childId }}">
        @endforeach

        <div class="a2-card a2-card--soft a2-mb-16">
            <div class="a2-section-title">إدارة الخدمات والرسوم من نفس الصفحة</div>
            <div class="a2-section-subtitle">
                لم نعد نعتمد على اختيار الخدمة من الأعلى. الآن كل صف يتحكم بنفسه:
                <strong>مطلوبة</strong> = ربط الخدمة بهذا الابن + حفظ الرسوم الخاصة بها.
            </div>
        </div>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th style="min-width:220px;">القسم الفرعي / الخدمة</th>
                        <th style="min-width:120px;">ربط الخدمة بهذا الابن</th>
                        <th style="min-width:110px;">مفعلة</th>
                        <th style="min-width:120px;">رسوم البزنس</th>
                        <th style="min-width:130px;">قيمة رسوم البزنس</th>
                        <th style="min-width:120px;">رسوم المستخدم</th>
                        <th style="min-width:130px;">قيمة رسوم المستخدم</th>
                        <th style="min-width:90px;">العملة</th>
                        <th style="min-width:90px;">الترتيب</th>
                        <th style="min-width:240px;">ملاحظات</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($children as $child)
                        @php
                            $childName = $child->name_ar ?: ($child->name_en ?: ('#' . $child->id));
                            $childActiveServices = $activeChildServiceMap[$child->id] ?? [];
                        @endphp

                        @foreach($services as $service)
                            @php
                                $serviceId = (int) $service->id;
                                $key = $child->id . ':' . $serviceId;
                                $fee = optional(($existingFees[$key] ?? collect())->first());

                                $serviceName = $service->name_ar ?: ($service->name_en ?: ($service->key ?: ('#' . $serviceId)));
                                $oldBase = 'rows.' . $child->id . '.' . $serviceId . '.';

                                $childHasServiceAlready = in_array($serviceId, $childActiveServices, true);

                                $rowEnabled = old(
                                    $oldBase . 'row_enabled',
                                    $childHasServiceAlready ? 1 : 0
                                );

                                $isActive = old(
                                    $oldBase . 'is_active',
                                    isset($fee->is_active) ? (int) $fee->is_active : ((int) $rowEnabled === 1 ? 1 : 0)
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

                                $rowIsEnabled = ((int) $rowEnabled === 1);
                            @endphp

                            <tr class="js-service-row" data-service-id="{{ $serviceId }}">
                                <td class="a2-text-right">
                                    <div class="a2-fw-900">{{ $childName }}</div>
                                    <div class="a2-muted a2-mt-8">{{ $serviceName }}</div>
                                    <div class="a2-muted a2-mt-8" dir="ltr">{{ $service->key ?: '—' }}</div>

                                    @if(isset($service->supports_deposit))
                                        <div class="a2-mt-8">
                                            @if($service->supports_deposit)
                                                <span class="a2-pill a2-pill-success">Deposit ON</span>
                                                <span class="a2-pill a2-pill-gray">Max: {{ (int) $service->max_deposit_percent }}%</span>
                                            @else
                                                <span class="a2-pill a2-pill-gray">Deposit OFF</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    <label class="a2-check" style="justify-content:center;">
                                        <input
                                            type="checkbox"
                                            class="js-row-enabled"
                                            name="rows[{{ $child->id }}][{{ $serviceId }}][row_enabled]"
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
                                            name="rows[{{ $child->id }}][{{ $serviceId }}][is_active]"
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
                                            name="rows[{{ $child->id }}][{{ $serviceId }}][business_fee_enabled]"
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
                                        name="rows[{{ $child->id }}][{{ $serviceId }}][business_fee_amount]"
                                        value="{{ $businessFeeAmount }}"
                                        placeholder="0.00"
                                        @disabled(! $rowIsEnabled)
                                    >
                                </td>

                                <td>
                                    <label class="a2-check" style="justify-content:center;">
                                        <input
                                            type="checkbox"
                                            class="js-row-field"
                                            name="rows[{{ $child->id }}][{{ $serviceId }}][client_fee_enabled]"
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
                                        name="rows[{{ $child->id }}][{{ $serviceId }}][client_fee_amount]"
                                        value="{{ $clientFeeAmount }}"
                                        placeholder="0.00"
                                        @disabled(! $rowIsEnabled)
                                    >
                                </td>

                                <td>
                                    <input
                                        type="text"
                                        class="a2-input js-row-field"
                                        name="rows[{{ $child->id }}][{{ $serviceId }}][currency]"
                                        value="{{ $currency }}"
                                        maxlength="3"
                                        placeholder="EGP"
                                        style="text-transform:uppercase;"
                                        @disabled(! $rowIsEnabled)
                                    >
                                </td>

                                <td>
                                    <input
                                        type="number"
                                        min="0"
                                        class="a2-input js-row-field"
                                        name="rows[{{ $child->id }}][{{ $serviceId }}][sort_order]"
                                        value="{{ $sortOrder }}"
                                        placeholder="0"
                                        @disabled(! $rowIsEnabled)
                                    >
                                </td>

                                <td>
                                    <textarea
                                        class="a2-textarea js-row-field"
                                        name="rows[{{ $child->id }}][{{ $serviceId }}][notes]"
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

        <div class="a2-page-actions a2-mt-16">
            <button type="submit" class="a2-btn a2-btn-primary">
                حفظ الخدمات والرسوم
            </button>

            <a href="{{ route('admin.categories.index', ['root_id' => $parentId]) }}"
               class="a2-btn a2-btn-ghost">
                رجوع
            </a>
        </div>
    </form>

    <div class="a2-card a2-card--soft a2-mt-16">
        <div class="a2-section-title">ملاحظة تشغيلية</div>
        <div class="a2-section-subtitle">
            إذا دخل الحجز حالة <span dir="ltr">in_progress</span> ثم أُلغي بعد ذلك، فلا يتم رد رسوم الخدمة التي تم خصمها عند التنفيذ.
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

    refreshRows();
});
</script>
@endpush