@extends('admin-v2.layouts.master')

@section('title', __('الخدمات ورسومها للمحدد'))
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
    $feeGroups = collect($feeGroups ?? []);
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('الخدمات ورسومها للمحدد') }}</h1>

            <div class="a2-page-subtitle">
                <div>
                    <strong>{{ __('القسم الرئيسي:') }}</strong>
                    {{ $parentName }}
                    <span class="a2-muted">#{{ $parentIdInt }}</span>
                </div>

                <div class="a2-mt-8">
                    <strong>{{ __('عدد الأقسام الفرعية المحددة:') }}</strong>
                    {{ $children->count() }}
                </div>
            </div>
        </div>

        <div class="a2-page-actions">
            <button type="button" class="a2-btn a2-btn-ghost js-bulk-check-all">
                {{ __('تحديد كل الخدمات') }}
            </button>

            <button type="button" class="a2-btn a2-btn-ghost js-bulk-uncheck-all">
                {{ __('إلغاء الكل') }}
            </button>

            <a
                href="{{ route('admin.categories.index', ['root_id' => $parentIdInt]) }}"
                class="a2-btn a2-btn-ghost"
            >
                {{ __('رجوع إلى الأقسام') }}
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
            <div class="a2-fw-900 a2-mb-8">{{ __('يوجد بعض الأخطاء، راجع البيانات التالية:') }}</div>

            <ul style="margin:0;padding-inline-start:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="a2-card a2-card--soft a2-mb-16">
        <div class="a2-section-title">{{ __('طريقة العمل') }}</div>

        <div class="a2-kv-grid a2-kv-grid-3 a2-mt-12">
            <div class="a2-kv-box">
                <span>{{ __('ربط الخدمة') }}</span>
                <strong>
                    {{ __('يعني تفعيل الخدمة داخل') }}
                    <span dir="ltr">category_platform_services</span>.
                </strong>
            </div>

            <div class="a2-kv-box">
                <span>{{ __('إلغاء الربط') }}</span>
                <strong>
                    {{ __('يعني تعطيل الخدمة لهذا القسم الفرعي.') }}
                </strong>
            </div>

            <div class="a2-kv-box">
                <span>{{ __('رسم الابن') }}</span>
                <strong>
                    {{ __('رسمٌ واحدٌ لكل خدماته — لا رسمٌ منفصل لكل خدمة. يُعطَّل تلقائيًا إن لم يعُد يعرض أى خدمة.') }}
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
                {{ __('لا توجد خدمات مفعلة في النظام حاليًا.') }}
            </div>
        @else
            @foreach($children as $child)
                @php
                    $childId = (int) $child->id;
                    $childName = $child->name_ar ?: ($child->name_en ?: ('#' . $childId));
                    $childActiveServices = collect($activeChildServiceMap->get($childId, []))
                        ->map(fn($id) => (int) $id)->all();

                    $fee = $existingFees->get($childId);
                    $feeBase = 'rows.' . $childId . '.fee.';

                    $isActive = old($feeBase . 'is_active', $fee->is_active ?? 0);
                    $feeGroupId = old($feeBase . 'fee_group_id', $fee->fee_group_id ?? '');
                    $businessFeeEnabled = old($feeBase . 'business_fee_enabled', $fee->business_fee_enabled ?? 0);
                    $businessFeeType = old($feeBase . 'business_fee_type', $fee->business_fee_type ?? 'fixed');
                    $businessFeeAmount = old($feeBase . 'business_fee_amount', isset($fee->business_fee_amount) ? (string) $fee->business_fee_amount : '0.00');
                    $clientFeeEnabled = old($feeBase . 'client_fee_enabled', $fee->client_fee_enabled ?? 0);
                    $clientFeeType = old($feeBase . 'client_fee_type', $fee->client_fee_type ?? 'fixed');
                    $clientFeeAmount = old($feeBase . 'client_fee_amount', isset($fee->client_fee_amount) ? (string) $fee->client_fee_amount : '0.00');
                    $currency = old($feeBase . 'currency', $fee->currency ?? 'EGP');
                @endphp

                <div class="a2-card a2-card--soft a2-mb-16 js-child-card">
                    <div class="a2-card-head">
                        <div class="a2-card-title">{{ $childName }} <span class="a2-muted">#{{ $childId }}</span></div>
                        @if($fee)<span class="a2-muted">{{ __('صف الرسم') }}: {{ $fee->id }}</span>@endif
                    </div>

                    <div class="a2-section-title a2-mt-12">{{ __('الخدمات') }}</div>
                    <div class="a2-page-actions" style="flex-wrap:wrap;">
                        @foreach($services as $service)
                            @php
                                $serviceId = (int) $service->id;
                                $serviceName = $service->name_ar ?: ($service->name_en ?: ($service->key ?: ('#' . $serviceId)));
                                $rowEnabled = old("rows.{$childId}.services.{$serviceId}.row_enabled", in_array($serviceId, $childActiveServices, true) ? 1 : 0);
                            @endphp
                            <label class="a2-check js-row-enabled-label">
                                <input type="checkbox" class="js-row-enabled"
                                       name="rows[{{ $childId }}][services][{{ $serviceId }}][row_enabled]"
                                       value="1" @checked((int) $rowEnabled === 1)>
                                <span>{{ $serviceName }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="a2-section-title a2-mt-12">{{ __('الرسم') }}</div>
                    <div class="a2-form-grid">
                        <label class="a2-check">
                            <input type="checkbox" name="rows[{{ $childId }}][fee][is_active]" value="1" @checked((int) $isActive === 1)>
                            <span>{{ __('مفعّل') }}</span>
                        </label>

                        <div class="a2-form-group">
                            <label class="a2-label">{{ __('مجموعة الرسوم') }}</label>
                            <select class="a2-select" name="rows[{{ $childId }}][fee][fee_group_id]">
                                <option value="">{{ __('— رسم فردى —') }}</option>
                                @foreach($feeGroups as $group)
                                    <option value="{{ $group->id }}" @selected((string) $feeGroupId === (string) $group->id)>{{ $group->name_ar }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="a2-form-group">
                            <label class="a2-check">
                                <input type="checkbox" name="rows[{{ $childId }}][fee][business_fee_enabled]" value="1" @checked((int) $businessFeeEnabled === 1)>
                                <span>{{ __('على التاجر') }}</span>
                            </label>
                            <select class="a2-select" name="rows[{{ $childId }}][fee][business_fee_type]">
                                <option value="fixed" @selected($businessFeeType === 'fixed')>{{ __('مبلغ ثابت') }}</option>
                                <option value="percent" @selected($businessFeeType === 'percent')>{{ __('نسبة %') }}</option>
                            </select>
                            <input class="a2-input" type="number" step="0.01" min="0" dir="ltr"
                                   name="rows[{{ $childId }}][fee][business_fee_amount]" value="{{ $businessFeeAmount }}">
                        </div>

                        <div class="a2-form-group">
                            <label class="a2-check">
                                <input type="checkbox" name="rows[{{ $childId }}][fee][client_fee_enabled]" value="1" @checked((int) $clientFeeEnabled === 1)>
                                <span>{{ __('على العميل') }}</span>
                            </label>
                            <select class="a2-select" name="rows[{{ $childId }}][fee][client_fee_type]">
                                <option value="fixed" @selected($clientFeeType === 'fixed')>{{ __('مبلغ ثابت') }}</option>
                                <option value="percent" @selected($clientFeeType === 'percent')>{{ __('نسبة %') }}</option>
                            </select>
                            <input class="a2-input" type="number" step="0.01" min="0" dir="ltr"
                                   name="rows[{{ $childId }}][fee][client_fee_amount]" value="{{ $clientFeeAmount }}">
                        </div>

                        <div class="a2-form-group">
                            <label class="a2-label">{{ __('العملة') }}</label>
                            <input class="a2-input" name="rows[{{ $childId }}][fee][currency]" maxlength="3"
                                   value="{{ $currency }}" dir="ltr" style="max-width:90px;text-transform:uppercase;">
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="a2-page-actions a2-mt-16">
                <button type="submit" class="a2-btn a2-btn-primary">
                    {{ __('حفظ الخدمات والرسوم') }}
                </button>

                <a
                    href="{{ route('admin.categories.index', ['root_id' => $parentIdInt]) }}"
                    class="a2-btn a2-btn-ghost"
                >
                    {{ __('رجوع') }}
                </a>
            </div>
        @endif
    </form>

    <div class="a2-card a2-card--soft a2-mt-16">
        <div class="a2-section-title">{{ __('ملاحظة تشغيلية') }}</div>
        <div class="a2-section-subtitle">
            {{ __('إذا دخل الحجز حالة') }}
            <span dir="ltr">in_progress</span>
            {{ __('وتم خصم رسوم الخدمة، فلا يتم رد هذه الرسوم تلقائيًا عند إلغاء الحجز لاحقًا. أي رد لاحق يجب أن يتم عبر سياسة نزاع أو إجراء مالي مستقل.') }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelector('.js-bulk-check-all')?.addEventListener('click', function () {
        document.querySelectorAll('.js-row-enabled').forEach(function (el) { el.checked = true; });
    });

    document.querySelector('.js-bulk-uncheck-all')?.addEventListener('click', function () {
        if (!confirm('هل تريد إلغاء ربط كل الخدمات المعروضة؟')) {
            return;
        }
        document.querySelectorAll('.js-row-enabled').forEach(function (el) { el.checked = false; });
    });
});
</script>
@endpush
