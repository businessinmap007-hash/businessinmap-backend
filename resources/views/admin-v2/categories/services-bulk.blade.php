@extends('admin-v2.layouts.master')

@section('title', 'Bulk Services + Fees')
@section('body_class', 'admin-v2 admin-v2-services-bulk')

@section('content')
<style>
    .a2-branch-block { border: 1px solid var(--a2-border, #e5e7eb); border-radius: 10px; padding: 14px; background: var(--a2-soft-bg, #fafafa); }
    .a2-branch-list { display: flex; flex-direction: column; gap: 8px; }
    .a2-branch { border: 1px solid var(--a2-border, #e5e7eb); border-radius: 8px; padding: 8px 10px; background: #fff; }
    .a2-branch-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .a2-branch-ungrouped-label { color: var(--a2-muted, #6b7280); }
    .a2-branch-types { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 6px; margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--a2-border, #e5e7eb); }
    .a2-check-sm { font-size: 13px; display: flex; align-items: center; gap: 6px; }
    .a2-check-sm small { color: var(--a2-muted, #9ca3af); }
    .a2-btn-sm { padding: 2px 10px; font-size: 12px; }
    .a2-branch.is-selected { border-color: var(--a2-primary, #2563eb); box-shadow: 0 0 0 1px var(--a2-primary, #2563eb) inset; }
    .a2-branch-types label input:disabled + span { opacity: .55; }
</style>
@php
    $rootsSafe = collect($roots ?? []);
    $servicesSafe = collect($services ?? []);
    $rootIdInt = (int) ($rootId ?? 0);

    $activeServiceCountsSafe = $activeServiceCounts ?? [];
    $activeChildrenCountInt = (int) ($activeChildrenCount ?? 0);
    $feeMatrixSafe = $feeMatrix ?? [];
    $serviceBranchesSafe = $serviceBranches ?? [];
    $configMatrixSafe = $configMatrix ?? [];
    $hasOldInput = count(old()) > 0;

    $nameOf = function ($item) {
        $ar = (string) ($item->name_ar ?? '');
        $en = (string) ($item->name_en ?? '');

        return $ar !== '' ? $ar : ($en !== '' ? $en : ('#' . ($item->id ?? '')));
    };

    $activeRoot = $rootIdInt > 0
        ? $rootsSafe->firstWhere('id', $rootIdInt)
        : $rootsSafe->first();

    if (! $activeRoot) {
        $activeRoot = $rootsSafe->first();
    }

    $activeRootId = (int) optional($activeRoot)->id;
    $activeChildren = collect($activeRoot->children ?? []);
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">Bulk Services + Fees</h1>
            <div class="a2-page-subtitle">
                {{ __('صفحة موحدة لربط خدمات الأقسام الفرعية، ضبط إعدادات الخدمة، وتطبيق رسوم البزنس والعميل دفعة واحدة.') }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.categories.index', $activeRootId > 0 ? ['root_id' => $activeRootId] : []) }}"
               class="a2-btn a2-btn-ghost">
                {{ __('رجوع إلى الأقسام') }}
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="a2-alert a2-alert-danger">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.categories.services-bulk.apply') }}" id="servicesBulkForm">
        @csrf

        <input type="hidden" name="root_id" id="bulk_root_id" value="{{ $activeRootId }}">

        <div class="a2-card a2-mb-16">
            <div class="a2-flex-between">
                <div>
                    <h2 class="a2-section-title">{{ __('1) التصنيف الرئيسي') }}</h2>
                    <div class="a2-section-subtitle">
                        {{ __('اختر الروت الذي سيتم عرض فروعه. عند تغيير الروت سيتم إعادة تحميل الصفحة بنفس السياق.') }}
                    </div>
                </div>
            </div>

            @if($rootsSafe->isEmpty())
                <div class="a2-muted">{{ __('لا توجد تصنيفات رئيسية بها فروع.') }}</div>
            @else
                <div class="a2-actionsbar">
                    @foreach($rootsSafe as $root)
                        @php
                            $rid = (int) $root->id;
                            $isActive = $rid === $activeRootId;
                            $childrenCount = collect($root->children ?? [])->count();
                        @endphp

                        <a
                            href="{{ route('admin.categories.services-bulk.index', ['root_id' => $rid]) }}"
                            class="a2-btn {{ $isActive ? 'a2-btn-primary' : 'a2-btn-ghost' }}"
                        >
                            {{ $nameOf($root) }}
                            <span class="a2-pill a2-pill-gray">{{ $childrenCount }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="a2-card a2-mb-16">
            <div class="a2-flex-between">
                <div>
                    <h2 class="a2-section-title">{{ __('2) الأقسام الفرعية') }}</h2>
                    <div class="a2-section-subtitle">
                        {{ __('اختر الفروع التي سيتم تطبيق الربط والرسوم عليها داخل:') }}
                        <strong>{{ $activeRoot ? $nameOf($activeRoot) : '—' }}</strong>
                    </div>
                </div>

                <div class="a2-page-actions">
                    <button type="button" class="a2-btn a2-btn-ghost" id="checkVisibleChildren">{{ __('تحديد الكل') }}</button>
                    <button type="button" class="a2-btn a2-btn-ghost" id="uncheckVisibleChildren">{{ __('إلغاء الكل') }}</button>
                </div>
            </div>

            @if($activeChildren->isEmpty())
                <div class="a2-muted">{{ __('لا توجد فروع داخل هذا التصنيف.') }}</div>
            @else
                <div class="a2-check-grid a2-mt-16">
                    @foreach($activeChildren as $child)
                        <label class="a2-check-card">
                            <span>
                                <strong>{{ $nameOf($child) }}</strong>
                                <small>Child #{{ (int) $child->id }}</small>
                            </span>

                            <input
                                type="checkbox"
                                name="category_ids[]"
                                value="{{ $child->id }}"
                                class="js-child-checkbox"
                                checked
                            >
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="a2-card a2-mb-16">
            <div class="a2-flex-between">
                <div>
                    <h2 class="a2-section-title">{{ __('3) الخدمات') }}</h2>
                    <div class="a2-section-subtitle">
                        {{ __('اختر الخدمات المطلوب ربطها أو تعطيلها للفروع المختارة.') }}
                    </div>
                </div>

                <div class="a2-page-actions">
                    <button type="button" class="a2-btn a2-btn-ghost" id="checkServices">{{ __('تحديد كل الخدمات') }}</button>
                    <button type="button" class="a2-btn a2-btn-ghost" id="uncheckServices">{{ __('إلغاء كل الخدمات') }}</button>
                </div>
            </div>

            @if($servicesSafe->isEmpty())
                <div class="a2-muted">{{ __('لا توجد خدمات مفعلة.') }}</div>
            @else
                <div class="a2-service-check-grid a2-mt-16">
                    @foreach($servicesSafe as $service)
                        @php
                            $serviceId = (int) $service->id;
                            $activeCount = (int) ($activeServiceCountsSafe[$serviceId] ?? 0);
                            $isFullyActive = $activeChildrenCountInt > 0 && $activeCount >= $activeChildrenCountInt;
                            $isPartialActive = $activeCount > 0 && ! $isFullyActive;
                        @endphp

                        <label class="a2-service-check">
                            <input
                                type="checkbox"
                                name="platform_service_ids[]"
                                value="{{ $serviceId }}"
                                class="js-service-checkbox"
                                data-active-count="{{ $activeCount }}"
                                data-total-count="{{ $activeChildrenCountInt }}"
                                @checked($isFullyActive)
                            >

                            <span class="a2-service-check-box">
                                <strong>{{ $nameOf($service) }}</strong>
                                <small dir="ltr">{{ $service->key }}</small>

                                @if($activeChildrenCountInt > 0)
                                    <span class="a2-pill a2-pill-gray">
                                        {{ $activeCount }}/{{ $activeChildrenCountInt }}
                                    </span>
                                @endif

                                @if($isPartialActive)
                                    <span class="a2-pill a2-pill-warning">{{ __('مفعلة جزئيًا') }}</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="a2-card a2-mb-16">
            <h2 class="a2-section-title">{{ __('4) طريقة التطبيق') }}</h2>
            <div class="a2-section-subtitle">
                {{ __('اختر هل تريد إضافة/تحديث الخدمات، استبدالها، أو تعطيل الخدمات المختارة.') }}
            </div>

            <div class="a2-check-grid a2-mt-16">
                <label class="a2-check-card">
                    <span>
                        <strong>{{ __('إضافة / تحديث') }}</strong>
                        <small>{{ __('يضيف الخدمات المختارة للفروع، ويحدث إعداداتها ورسومها بدون تعطيل الخدمات الأخرى.') }}</small>
                    </span>

                    <input type="radio" name="mode" value="append" checked>
                </label>

                <label class="a2-check-card">
                    <span>
                        <strong>{{ __('استبدال خدمات الفروع المختارة') }}</strong>
                        <small>{{ __('يجعل الخدمات المختارة هي الخدمات النشطة للفروع المحددة، ويعطل غير المختار.') }}</small>
                    </span>

                    <input type="radio" name="mode" value="replace">
                </label>

                <label class="a2-check-card">
                    <span>
                        <strong>{{ __('تعطيل الخدمات المختارة') }}</strong>
                        <small>{{ __('يعطل الخدمات المختارة ورسومها للفروع المحددة فقط.') }}</small>
                    </span>

                    <input type="radio" name="mode" value="remove">
                </label>
            </div>
        </div>

        <div class="a2-card a2-mb-16" id="feesSection">
            <div class="a2-flex-between">
                <div>
                    <h2 class="a2-section-title">{{ __('5) رسوم الخدمات المختارة') }}</h2>
                    <div class="a2-section-subtitle">
                        {{ __('تظهر هنا فقط الخدمات التي تم اختيارها. عند الحفظ سيتم تطبيق القيم على كل الفروع المحددة.') }}
                    </div>
                </div>
            </div>

            @if($servicesSafe->isEmpty())
                <div class="a2-muted">{{ __('لا توجد خدمات مفعلة.') }}</div>
            @else
                <div class="a2-alert a2-alert-info" id="feesHelpBox">
                    {{ __('اختر خدمة واحدة أو أكثر من قسم الخدمات بالأعلى، وستظهر إعدادات رسوم كل خدمة هنا.') }}
                </div>

                <div class="a2-alert a2-alert-warning" id="removeModeFeesNote" hidden>
                    {{ __('وضع التعطيل لا يحتاج ضبط رسوم. سيتم تعطيل الربط والرسوم للخدمات المختارة.') }}
                </div>

                <div id="feesLayout" hidden>
                    <div class="a2-tabs" id="feeTabs">
                        @foreach($servicesSafe as $service)
                            @php
                                $serviceId = (int) $service->id;
                            @endphp

                            <button
                                type="button"
                                class="a2-tab js-fee-tab"
                                data-service-id="{{ $serviceId }}"
                                hidden
                            >
                                {{ $nameOf($service) }}
                                <span class="a2-pill a2-pill-gray" dir="ltr">{{ $service->key }}</span>
                            </button>
                        @endforeach
                    </div>

                    @foreach($servicesSafe as $service)
                        @php
                            $serviceId = (int) $service->id;
                            $serviceTitle = $nameOf($service);
                        @endphp

                        <div
                            class="js-service-fee-card"
                            data-service-id="{{ $serviceId }}"
                            hidden
                        >
                            <div class="a2-card a2-card--soft">
                                <div class="a2-flex-between">
                                    <div>
                                        <h3 class="a2-section-title">
                                            {{ $serviceTitle }}
                                            <span class="a2-pill a2-pill-gray" dir="ltr">{{ $service->key }}</span>
                                        </h3>
                                        <div class="a2-section-subtitle">
                                            {{ __('Override خاص بهذه الخدمة فقط. القيم هنا تطبق على الفروع المحددة.') }}
                                        </div>

                                        <div class="a2-alert a2-alert-warning js-fee-mixed-warning" hidden>
                                            {{ __('الفروع المختارة تحتوي قيم رسوم مختلفة لهذه الخدمة. سيتم عرض أول قيمة موجودة، وأي حفظ جديد سيطبق القيمة الجديدة على كل الفروع المختارة.') }}
                                        </div>
                                    </div>
                                </div>

                                @php
                                    $branchData = $serviceBranchesSafe[$serviceId] ?? ['branches' => [], 'ungrouped' => []];
                                    $oldGroups = collect(old("item_groups.$serviceId", []))->map(fn ($v) => (int) $v)->all();
                                    $oldTypes = collect(old("allowed_item_types.$serviceId", []))->map(fn ($v) => (string) $v)->all();
                                @endphp

                                @if(! empty($branchData['branches']) || ! empty($branchData['ungrouped']))
                                    <div class="a2-branch-block a2-mt-16" data-service-id="{{ $serviceId }}">
                                        <h4 class="a2-section-title">{{ __('الفروع والأنواع المسموحة') }}</h4>
                                        <div class="a2-section-subtitle">
                                            {{ __('اختر الفروع المناسبة لهذا التصنيف — صاحب الحساب سيختار مما تحدده هنا فقط. اترك الكل فارغًا للسماح بجميع الأنواع. تُطبّق على كل الفروع المحددة.') }}
                                        </div>

                                        <div class="a2-alert a2-alert-warning js-branch-mixed-warning" hidden>
                                            {{ __('الأقسام الفرعية المختارة تحتوي اختيارات مختلفة لهذه الخدمة. يُعرض أول اختيار، وأي حفظ جديد سيطبّق الاختيار الحالي على كل المحدد.') }}
                                        </div>

                                        <div class="a2-branch-list a2-mt-12">
                                            @foreach($branchData['branches'] as $branch)
                                                <div class="a2-branch">
                                                    <div class="a2-branch-head">
                                                        <label class="a2-check">
                                                            <input
                                                                type="checkbox"
                                                                class="js-branch-checkbox"
                                                                name="item_groups[{{ $serviceId }}][]"
                                                                value="{{ $branch['id'] }}"
                                                                data-service-id="{{ $serviceId }}"
                                                                data-group-id="{{ $branch['id'] }}"
                                                                @checked(in_array((int) $branch['id'], $oldGroups, true))
                                                            >
                                                            <span>
                                                                <strong>{{ $branch['name'] }}</strong>
                                                                <span class="a2-pill a2-pill-gray">{{ count($branch['types']) }}</span>
                                                            </span>
                                                        </label>

                                                        <button type="button" class="a2-btn a2-btn-ghost a2-btn-sm js-branch-toggle">
                                                            {{ __('الأنواع') }}
                                                        </button>
                                                    </div>

                                                    <div class="a2-branch-types" hidden>
                                                        @foreach($branch['types'] as $type)
                                                            <label class="a2-check a2-check-sm">
                                                                <input
                                                                    type="checkbox"
                                                                    class="js-type-checkbox"
                                                                    name="allowed_item_types[{{ $serviceId }}][]"
                                                                    value="{{ $type['key'] }}"
                                                                    data-service-id="{{ $serviceId }}"
                                                                    data-group-id="{{ $branch['id'] }}"
                                                                    @checked(in_array((string) $type['key'], $oldTypes, true))
                                                                >
                                                                <span>{{ $type['name'] }} <small dir="ltr">{{ $type['key'] }}</small></span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach

                                            @if(! empty($branchData['ungrouped']))
                                                <div class="a2-branch">
                                                    <div class="a2-branch-head">
                                                        <span class="a2-branch-ungrouped-label"><strong>{{ __('بدون فرع') }}</strong></span>
                                                        <button type="button" class="a2-btn a2-btn-ghost a2-btn-sm js-branch-toggle">
                                                            {{ __('الأنواع') }}
                                                        </button>
                                                    </div>

                                                    <div class="a2-branch-types" hidden>
                                                        @foreach($branchData['ungrouped'] as $type)
                                                            <label class="a2-check a2-check-sm">
                                                                <input
                                                                    type="checkbox"
                                                                    class="js-type-checkbox"
                                                                    name="allowed_item_types[{{ $serviceId }}][]"
                                                                    value="{{ $type['key'] }}"
                                                                    data-service-id="{{ $serviceId }}"
                                                                    data-group-id="0"
                                                                    @checked(in_array((string) $type['key'], $oldTypes, true))
                                                                >
                                                                <span>{{ $type['name'] }} <small dir="ltr">{{ $type['key'] }}</small></span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                <div class="a2-form-grid a2-mt-16">
                                    <div class="a2-form-group">
                                        <label class="a2-label">{{ __('العملة') }}</label>
                                        <input
                                            class="a2-input"
                                            name="service_fees[{{ $serviceId }}][currency]"
                                            value="{{ old("service_fees.$serviceId.currency", 'EGP') }}"
                                            maxlength="3"
                                            dir="ltr"
                                        >
                                    </div>

                                    <div class="a2-form-group">
                                        <label class="a2-label">{{ __('ملاحظات') }}</label>
                                        <input
                                            class="a2-input"
                                            name="service_fees[{{ $serviceId }}][fee_notes]"
                                            value="{{ old("service_fees.$serviceId.fee_notes") }}"
                                            placeholder="{{ __('اختياري') }}"
                                        >
                                    </div>
                                </div>

                                <div class="a2-card-grid-2 a2-mt-16">
                                    <div class="a2-card-muted">
                                        <h4 class="a2-section-title">{{ __('رسوم البزنس') }}</h4>

                                        <label class="a2-check">
                                            <input
                                                type="checkbox"
                                                name="service_fees[{{ $serviceId }}][business_fee_enabled]"
                                                value="1"
                                                @checked(old("service_fees.$serviceId.business_fee_enabled"))
                                            >
                                            <span>{{ __('تفعيل رسوم البزنس') }}</span>
                                        </label>

                                        <div class="a2-form-grid a2-mt-12">
                                            <div class="a2-form-group">
                                                <label class="a2-label">{{ __('نوع الرسوم') }}</label>
                                                <select
                                                    class="a2-select"
                                                    name="service_fees[{{ $serviceId }}][business_fee_type]"
                                                >
                                                    <option value="fixed" @selected(old("service_fees.$serviceId.business_fee_type", 'fixed') === 'fixed')>
                                                        {{ __('مبلغ ثابت') }}
                                                    </option>
                                                    <option value="percent" @selected(old("service_fees.$serviceId.business_fee_type") === 'percent')>
                                                        {{ __('نسبة %') }}
                                                    </option>
                                                </select>
                                            </div>

                                            <div class="a2-form-group">
                                                <label class="a2-label">{{ __('القيمة') }}</label>
                                                <input
                                                    class="a2-input"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    name="service_fees[{{ $serviceId }}][business_fee_amount]"
                                                    value="{{ old("service_fees.$serviceId.business_fee_amount") }}"
                                                    placeholder="{{ __('مثال: 10') }}"
                                                >
                                            </div>
                                        </div>
                                    </div>

                                    <div class="a2-card-muted">
                                        <h4 class="a2-section-title">{{ __('رسوم العميل') }}</h4>

                                        <label class="a2-check">
                                            <input
                                                type="checkbox"
                                                name="service_fees[{{ $serviceId }}][client_fee_enabled]"
                                                value="1"
                                                @checked(old("service_fees.$serviceId.client_fee_enabled"))
                                            >
                                            <span>{{ __('تفعيل رسوم العميل') }}</span>
                                        </label>

                                        <div class="a2-form-grid a2-mt-12">
                                            <div class="a2-form-group">
                                                <label class="a2-label">{{ __('نوع الرسوم') }}</label>
                                                <select
                                                    class="a2-select"
                                                    name="service_fees[{{ $serviceId }}][client_fee_type]"
                                                >
                                                    <option value="fixed" @selected(old("service_fees.$serviceId.client_fee_type", 'fixed') === 'fixed')>
                                                        {{ __('مبلغ ثابت') }}
                                                    </option>
                                                    <option value="percent" @selected(old("service_fees.$serviceId.client_fee_type") === 'percent')>
                                                        {{ __('نسبة %') }}
                                                    </option>
                                                </select>
                                            </div>

                                            <div class="a2-form-group">
                                                <label class="a2-label">{{ __('القيمة') }}</label>
                                                <input
                                                    class="a2-input"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    name="service_fees[{{ $serviceId }}][client_fee_amount]"
                                                    value="{{ old("service_fees.$serviceId.client_fee_amount") }}"
                                                    placeholder="{{ __('مثال: 2') }}"
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="a2-card">
            <div class="a2-flex-between">
                <div class="a2-actionsbar">
                    <span class="a2-pill a2-pill-gray">
                        Root: {{ $activeRoot ? $nameOf($activeRoot) : '—' }}
                    </span>
                    <span class="a2-pill a2-pill-gray">
                        {{ __('الفروع المختارة:') }}  435@php
    $rootsSafe = collect($roots ?? []);
    $servicesSafe = collect($services ?? []);
    $rootIdInt = (int) ($rootId ?? 0);

    $activeServiceCountsSafe = $activeServiceCounts ?? [];
    $activeChildrenCountInt = (int) ($activeChildrenCount ?? 0);
    $feeMatrixSafe = $feeMatrix ?? [];
    $serviceBranchesSafe = $serviceBranches ?? [];
    $configMatrixSafe = $configMatrix ?? [];
    $hasOldInput = count(old()) > 0;

    $nameOf = function ($item) {
        $ar = (string) ($item->name_ar ?? '');
        $en = (string) ($item->name_en ?? '');

        return $ar !== '' ? $ar : ($en !== '' ? $en : ('#' . ($item->id ?? '')));
    };

    $activeRoot = $rootIdInt > 0
        ? $rootsSafe->firstWhere('id', $rootIdInt)
        : $rootsSafe->first();

    if (! $activeRoot) {
        $activeRoot = $rootsSafe->first();
    }

    $activeRootId = (int) optional($activeRoot)->id;
    $activeChildren = collect($activeRoot->children ?? []);
@endphp436 
                    </span>
                    <span class="a2-pill a2-pill-gray">
                        {{ __('الخدمات المختارة:') }}  439@php
    $rootsSafe = collect($roots ?? []);
    $servicesSafe = collect($services ?? []);
    $rootIdInt = (int) ($rootId ?? 0);

    $activeServiceCountsSafe = $activeServiceCounts ?? [];
    $activeChildrenCountInt = (int) ($activeChildrenCount ?? 0);
    $feeMatrixSafe = $feeMatrix ?? [];
    $serviceBranchesSafe = $serviceBranches ?? [];
    $configMatrixSafe = $configMatrix ?? [];
    $hasOldInput = count(old()) > 0;

    $nameOf = function ($item) {
        $ar = (string) ($item->name_ar ?? '');
        $en = (string) ($item->name_en ?? '');

        return $ar !== '' ? $ar : ($en !== '' ? $en : ('#' . ($item->id ?? '')));
    };

    $activeRoot = $rootIdInt > 0
        ? $rootsSafe->firstWhere('id', $rootIdInt)
        : $rootsSafe->first();

    if (! $activeRoot) {
        $activeRoot = $rootsSafe->first();
    }

    $activeRootId = (int) optional($activeRoot)->id;
    $activeChildren = collect($activeRoot->children ?? []);
@endphp440 
                    </span>
                    <span class="a2-pill a2-pill-gray">
                        {{ __('الوضع:') }} <strong id="selectedModeLabel">{{ __('إضافة / تحديث') }}</strong>
                    </span>
                </div>

                <button type="submit" class="a2-btn a2-btn-primary" id="submitBulkBtn">
                    {{ __('تطبيق الخدمات والرسوم') }}
                </button>
            </div>
        </div>
    </form>
</div>

<script>
window.BIM_SERVICE_FEE_MATRIX = @json($feeMatrixSafe ?? []);
window.BIM_SERVICE_CONFIG_MATRIX = @json($configMatrixSafe ?? []);
window.BIM_HAS_OLD_INPUT = @json($hasOldInput);
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const serviceInputs = document.querySelectorAll('.js-service-checkbox');
    const childInputs = document.querySelectorAll('.js-child-checkbox');
    const serviceFeeCards = document.querySelectorAll('.js-service-fee-card');
    const feeTabs = document.querySelectorAll('.js-fee-tab');

    const feesHelpBox = document.getElementById('feesHelpBox');
    const feesLayout = document.getElementById('feesLayout');
    const removeModeFeesNote = document.getElementById('removeModeFeesNote');

    const selectedChildrenCount = document.getElementById('selectedChildrenCount');
    const selectedServicesCount = document.getElementById('selectedServicesCount');
    const selectedModeLabel = document.getElementById('selectedModeLabel');

    const feeMatrix = window.BIM_SERVICE_FEE_MATRIX || {};
    const configMatrix = window.BIM_SERVICE_CONFIG_MATRIX || {};
    const hasOldInput = !!window.BIM_HAS_OLD_INPUT;
    const serviceDefaults = {};

    const modeLabels = {
        append: 'إضافة / تحديث',
        replace: 'استبدال',
        remove: 'تعطيل'
    };

    function fieldName(serviceId, field) {
        return 'service_fees[' + serviceId + '][' + field + ']';
    }

    function fieldSelector(serviceId, field) {
        return '[name="' + fieldName(serviceId, field) + '"]';
    }

    function getField(card, serviceId, field) {
        return card.querySelector(fieldSelector(serviceId, field));
    }

    function getFieldValue(card, serviceId, field) {
        const input = getField(card, serviceId, field);
        return input ? input.value : '';
    }

    function getCheckboxValue(card, serviceId, field) {
        const input = getField(card, serviceId, field);
        return !!(input && input.checked);
    }

    function setFieldValue(card, serviceId, field, value) {
        const input = getField(card, serviceId, field);

        if (!input || input.dataset.userEdited === '1') {
            return;
        }

        input.value = value ?? '';
    }

    function setCheckboxValue(card, serviceId, field, checked) {
        const input = getField(card, serviceId, field);

        if (!input || input.dataset.userEdited === '1') {
            return;
        }

        input.checked = !!checked;
    }

    function normalizeFeeValue(value) {
        if (value === null || value === undefined || value === '') {
            return '';
        }

        const numberValue = parseFloat(value);

        if (Number.isNaN(numberValue)) {
            return '';
        }

        return numberValue.toFixed(2);
    }

    function getSelectedChildIds() {
        return Array.from(document.querySelectorAll('.js-child-checkbox:checked'))
            .map(function (input) {
                return String(input.value);
            });
    }

    function getSelectedServiceIds() {
        return Array.from(document.querySelectorAll('.js-service-checkbox:checked'))
            .map(function (input) {
                return String(input.value);
            });
    }

    function getActiveMode() {
        const checked = document.querySelector('input[name="mode"]:checked');
        return checked ? checked.value : 'append';
    }

    function setServiceFeeCardState(card, isVisible, shouldSubmit) {
        card.hidden = !isVisible;

        card.querySelectorAll('input, select, textarea').forEach(function (field) {
            field.disabled = !shouldSubmit;
        });
    }

    function setActiveFeeCard(serviceId) {
        let firstVisibleId = null;
        const selectedIds = getSelectedServiceIds();
        const activeMode = getActiveMode();
        const feesDisabled = activeMode === 'remove';

        feeTabs.forEach(function (tab) {
            if (!tab.hidden && firstVisibleId === null) {
                firstVisibleId = String(tab.dataset.serviceId || '');
            }
        });

        const targetId = String(serviceId || firstVisibleId || '');

        feeTabs.forEach(function (tab) {
            const tabServiceId = String(tab.dataset.serviceId || '');
            tab.classList.toggle('is-active', tabServiceId === targetId);
        });

        serviceFeeCards.forEach(function (card) {
            const cardServiceId = String(card.dataset.serviceId || '');
            const isSelected = selectedIds.includes(cardServiceId) && !feesDisabled;
            const isVisible = isSelected && cardServiceId === targetId;

            /*
            |--------------------------------------------------------------------------
            | مهم
            |--------------------------------------------------------------------------
            | نخفي كارت التاب غير المفتوح فقط، لكن لا نعطل حقول الخدمة المختارة.
            | لأن الحقول disabled لا يتم إرسالها في POST، وهذا كان يصفّر رسوم
            | الخدمات الأخرى عند تعديل خدمة واحدة فقط.
            |--------------------------------------------------------------------------
            */
            setServiceFeeCardState(card, isVisible, isSelected);
        });
    }

    serviceFeeCards.forEach(function (card) {
        const serviceId = String(card.dataset.serviceId || '');

        serviceDefaults[serviceId] = {
            currency: getFieldValue(card, serviceId, 'currency') || 'EGP',
            fee_notes: getFieldValue(card, serviceId, 'fee_notes') || '',

            business_fee_enabled: getCheckboxValue(card, serviceId, 'business_fee_enabled'),
            business_fee_type: getFieldValue(card, serviceId, 'business_fee_type') || 'fixed',
            business_fee_amount: getFieldValue(card, serviceId, 'business_fee_amount') || '',

            client_fee_enabled: getCheckboxValue(card, serviceId, 'client_fee_enabled'),
            client_fee_type: getFieldValue(card, serviceId, 'client_fee_type') || 'fixed',
            client_fee_amount: getFieldValue(card, serviceId, 'client_fee_amount') || ''
        };
    });

    document.querySelectorAll('.js-service-fee-card input, .js-service-fee-card select, .js-service-fee-card textarea')
        .forEach(function (field) {
            field.addEventListener('input', function () {
                field.dataset.userEdited = '1';
            });

            field.addEventListener('change', function () {
                field.dataset.userEdited = '1';
            });
        });

    function resetServiceFeeCardToDefault(card, serviceId) {
        const defaults = serviceDefaults[String(serviceId)] || {};

        setFieldValue(card, serviceId, 'currency', defaults.currency || 'EGP');
        setFieldValue(card, serviceId, 'fee_notes', defaults.fee_notes || '');

        setCheckboxValue(card, serviceId, 'business_fee_enabled', !!defaults.business_fee_enabled);
        setFieldValue(card, serviceId, 'business_fee_type', defaults.business_fee_type || 'fixed');
        setFieldValue(card, serviceId, 'business_fee_amount', defaults.business_fee_amount || '');

        setCheckboxValue(card, serviceId, 'client_fee_enabled', !!defaults.client_fee_enabled);
        setFieldValue(card, serviceId, 'client_fee_type', defaults.client_fee_type || 'fixed');
        setFieldValue(card, serviceId, 'client_fee_amount', defaults.client_fee_amount || '');
    }

    function getCommonServiceFee(serviceId) {
        const selectedChildIds = getSelectedChildIds();

        let first = null;
        let mixed = false;
        let rowsFound = 0;

        selectedChildIds.forEach(function (childId) {
            const row = feeMatrix[String(childId)] && feeMatrix[String(childId)][String(serviceId)]
                ? feeMatrix[String(childId)][String(serviceId)]
                : null;

            if (!row) {
                return;
            }

            rowsFound++;

            if (!first) {
                first = row;
                return;
            }

            [
                'business_fee_enabled',
                'business_fee_type',
                'business_fee_amount',
                'client_fee_enabled',
                'client_fee_type',
                'client_fee_amount',
                'currency',
                'fee_notes'
            ].forEach(function (key) {
                if (String(first[key] ?? '') !== String(row[key] ?? '')) {
                    mixed = true;
                }
            });
        });

        if (first && selectedChildIds.length > 0 && rowsFound < selectedChildIds.length) {
            mixed = true;
        }

        return {
            row: first,
            mixed: mixed
        };
    }

    function fillServiceFeeCard(serviceId) {
        const card = document.querySelector('.js-service-fee-card[data-service-id="' + serviceId + '"]');

        if (!card) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Old input safety
        |--------------------------------------------------------------------------
        | عند الرجوع من validation error لا نعيد ملء الحقول من feeMatrix حتى لا
        | نضيع القيم التي أدخلها المستخدم قبل الإرسال.
        |--------------------------------------------------------------------------
        */
        if (hasOldInput) {
            return;
        }

        resetServiceFeeCardToDefault(card, serviceId);

        const result = getCommonServiceFee(serviceId);
        const row = result.row;
        const mixedBox = card.querySelector('.js-fee-mixed-warning');

        if (mixedBox) {
            mixedBox.hidden = !result.mixed;
        }

        if (!row) {
            return;
        }

        setCheckboxValue(card, serviceId, 'business_fee_enabled', !!row.business_fee_enabled);
        setFieldValue(card, serviceId, 'business_fee_type', row.business_fee_type || 'fixed');
        setFieldValue(card, serviceId, 'business_fee_amount', normalizeFeeValue(row.business_fee_amount));

        setCheckboxValue(card, serviceId, 'client_fee_enabled', !!row.client_fee_enabled);
        setFieldValue(card, serviceId, 'client_fee_type', row.client_fee_type || 'fixed');
        setFieldValue(card, serviceId, 'client_fee_amount', normalizeFeeValue(row.client_fee_amount));

        setFieldValue(card, serviceId, 'currency', row.currency || 'EGP');
        setFieldValue(card, serviceId, 'fee_notes', row.fee_notes || '');
    }

    function fillVisibleServiceFeeCards() {
        getSelectedServiceIds().forEach(function (serviceId) {
            fillServiceFeeCard(serviceId);
            applyBranchSelection(serviceId);
        });
    }

    /* ----- Branch (allowed types) picker — services-bulk §4 ----- */

    function branchBlock(serviceId) {
        return document.querySelector('.a2-branch-block[data-service-id="' + serviceId + '"]');
    }

    function setBranchNestedTypes(branchCheckbox, checked) {
        const branchEl = branchCheckbox.closest('.a2-branch');

        if (!branchEl) {
            return;
        }

        branchEl.querySelectorAll('.js-type-checkbox').forEach(function (type) {
            type.checked = checked;
        });

        branchEl.classList.toggle('is-selected', checked);
    }

    function sameAsSet(a, b) {
        const sa = (a || []).map(String).sort();
        const sb = (b || []).map(String).sort();
        return JSON.stringify(sa) === JSON.stringify(sb);
    }

    function getCommonServiceConfig(serviceId) {
        const selectedChildIds = getSelectedChildIds();

        let first = null;
        let mixed = false;
        let rowsFound = 0;

        selectedChildIds.forEach(function (childId) {
            const row = configMatrix[String(childId)] && configMatrix[String(childId)][String(serviceId)]
                ? configMatrix[String(childId)][String(serviceId)]
                : null;

            if (!row) {
                return;
            }

            rowsFound++;

            if (!first) {
                first = row;
                return;
            }

            if (!sameAsSet(first.item_groups, row.item_groups)
                || !sameAsSet(first.allowed_item_types, row.allowed_item_types)) {
                mixed = true;
            }
        });

        if (first && selectedChildIds.length > 0 && rowsFound < selectedChildIds.length) {
            mixed = true;
        }

        return { row: first, mixed: mixed };
    }

    function applyBranchSelection(serviceId) {
        const block = branchBlock(serviceId);

        if (!block) {
            return;
        }

        /*
        | Returning from a validation error: keep the checkbox state the server
        | re-rendered from old(), only refresh the selected-branch visuals.
        */
        if (hasOldInput) {
            block.querySelectorAll('.js-branch-checkbox').forEach(function (branchCb) {
                const branchEl = branchCb.closest('.a2-branch');
                if (branchEl) {
                    branchEl.classList.toggle('is-selected', branchCb.checked);
                }
            });
            return;
        }

        block.querySelectorAll('.js-branch-checkbox, .js-type-checkbox').forEach(function (cb) {
            cb.checked = false;
        });
        block.querySelectorAll('.a2-branch').forEach(function (branchEl) {
            branchEl.classList.remove('is-selected');
        });

        const result = getCommonServiceConfig(serviceId);
        const mixedBox = block.querySelector('.js-branch-mixed-warning');

        if (mixedBox) {
            mixedBox.hidden = !result.mixed;
        }

        const row = result.row;

        if (!row) {
            return;
        }

        const groupIds = (row.item_groups || []).map(String);
        const allowedTypes = (row.allowed_item_types || []).map(String);
        const coveredKeys = new Set();

        block.querySelectorAll('.js-branch-checkbox').forEach(function (branchCb) {
            if (groupIds.indexOf(String(branchCb.dataset.groupId)) === -1) {
                return;
            }

            branchCb.checked = true;
            setBranchNestedTypes(branchCb, true);

            const branchEl = branchCb.closest('.a2-branch');
            if (branchEl) {
                branchEl.querySelectorAll('.js-type-checkbox').forEach(function (type) {
                    coveredKeys.add(String(type.value));
                });
            }
        });

        allowedTypes.forEach(function (key) {
            if (coveredKeys.has(String(key))) {
                return;
            }

            const typeCb = Array.prototype.find.call(
                block.querySelectorAll('.js-type-checkbox'),
                function (cb) {
                    return String(cb.value) === String(key) && !cb.checked;
                }
            );

            if (typeCb) {
                typeCb.checked = true;
            }
        });
    }

    function markServicePartialStates() {
        serviceInputs.forEach(function (input) {
            const activeCount = parseInt(input.dataset.activeCount || '0', 10);
            const totalCount = parseInt(input.dataset.totalCount || '0', 10);

            input.indeterminate = activeCount > 0 && totalCount > 0 && activeCount < totalCount && !input.checked;
        });
    }

    function syncFeeTabsAndCards() {
        const selectedIds = getSelectedServiceIds();
        const activeMode = getActiveMode();
        const feesDisabled = activeMode === 'remove';

        let visibleCount = 0;
        let currentActiveVisible = false;

        feeTabs.forEach(function (tab) {
            const serviceId = String(tab.dataset.serviceId || '');
            const visible = selectedIds.includes(serviceId) && !feesDisabled;

            tab.hidden = !visible;

            if (visible) {
                visibleCount++;

                if (tab.classList.contains('is-active')) {
                    currentActiveVisible = true;
                }
            }
        });

        serviceFeeCards.forEach(function (card) {
            const serviceId = String(card.dataset.serviceId || '');
            const isSelected = selectedIds.includes(serviceId) && !feesDisabled;

            if (isSelected) {
                fillServiceFeeCard(serviceId);
                applyBranchSelection(serviceId);
            } else {
                setServiceFeeCardState(card, false, false);
            }
        });

        if (!currentActiveVisible) {
            setActiveFeeCard(selectedIds[0] || null);
        } else {
            const activeTab = document.querySelector('.js-fee-tab.is-active:not([hidden])');
            setActiveFeeCard(activeTab ? activeTab.dataset.serviceId : selectedIds[0]);
        }

        if (feesHelpBox) {
            feesHelpBox.hidden = visibleCount > 0 || feesDisabled;
        }

        if (feesLayout) {
            feesLayout.hidden = !(visibleCount > 0 && !feesDisabled);
        }

        if (removeModeFeesNote) {
            removeModeFeesNote.hidden = !feesDisabled;
        }
    }

    function updateSummary() {
        const childrenCount = getSelectedChildIds().length;
        const servicesCount = getSelectedServiceIds().length;
        const mode = getActiveMode();

        if (selectedChildrenCount) {
            selectedChildrenCount.textContent = childrenCount;
        }

        if (selectedServicesCount) {
            selectedServicesCount.textContent = servicesCount;
        }

        if (selectedModeLabel) {
            selectedModeLabel.textContent = modeLabels[mode] || mode;
        }
    }

    function syncAll() {
        markServicePartialStates();
        syncFeeTabsAndCards();
        updateSummary();
    }

    document.getElementById('checkVisibleChildren')?.addEventListener('click', function () {
        childInputs.forEach(function (input) {
            input.checked = true;
        });

        fillVisibleServiceFeeCards();
        syncAll();
    });

    document.getElementById('uncheckVisibleChildren')?.addEventListener('click', function () {
        childInputs.forEach(function (input) {
            input.checked = false;
        });

        fillVisibleServiceFeeCards();
        syncAll();
    });

    document.getElementById('checkServices')?.addEventListener('click', function () {
        serviceInputs.forEach(function (input) {
            input.checked = true;
            input.indeterminate = false;
        });

        syncAll();
    });

    document.getElementById('uncheckServices')?.addEventListener('click', function () {
        serviceInputs.forEach(function (input) {
            input.checked = false;
            input.indeterminate = false;
        });

        syncAll();
    });

    childInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            fillVisibleServiceFeeCards();
            syncAll();
        });
    });

    serviceInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            input.indeterminate = false;
            syncAll();
        });
    });

    feeTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            setActiveFeeCard(tab.dataset.serviceId);
        });
    });

    document.querySelectorAll('input[name="mode"]').forEach(function (input) {
        input.addEventListener('change', function () {
            syncAll();
        });
    });

    document.querySelectorAll('.js-branch-checkbox').forEach(function (branchCb) {
        branchCb.addEventListener('change', function () {
            setBranchNestedTypes(branchCb, branchCb.checked);
        });
    });

    document.querySelectorAll('.js-type-checkbox').forEach(function (typeCb) {
        typeCb.addEventListener('change', function () {
            /* Unticking any type of a fully-selected branch drops that branch to
               explicit fine-tuned mode (the remaining ticked types still apply). */
            if (typeCb.checked) {
                return;
            }

            const branchEl = typeCb.closest('.a2-branch');
            const branchCb = branchEl ? branchEl.querySelector('.js-branch-checkbox') : null;

            if (branchCb && branchCb.checked) {
                branchCb.checked = false;
                branchEl.classList.remove('is-selected');
            }
        });
    });

    document.querySelectorAll('.js-branch-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const types = btn.closest('.a2-branch').querySelector('.a2-branch-types');
            if (types) {
                types.hidden = !types.hidden;
            }
        });
    });

    document.getElementById('servicesBulkForm')?.addEventListener('submit', function (event) {
        if (getSelectedChildIds().length === 0) {
            event.preventDefault();
            alert('اختر قسمًا فرعيًا واحدًا على الأقل.');
            return;
        }

        if (getSelectedServiceIds().length === 0) {
            event.preventDefault();
            alert('اختر خدمة واحدة على الأقل.');
            return;
        }

        const selectedServiceIds = getSelectedServiceIds();
        const activeMode = getActiveMode();

        /*
        |--------------------------------------------------------------------------
        | مهم قبل الإرسال
        |--------------------------------------------------------------------------
        | أي خدمة مختارة يجب أن تكون حقول الرسوم الخاصة بها enabled
        | حتى لو ليست التاب المفتوح حاليًا.
        |--------------------------------------------------------------------------
        */
        if (activeMode !== 'remove') {
            serviceFeeCards.forEach(function (card) {
                const serviceId = String(card.dataset.serviceId || '');
                const isSelected = selectedServiceIds.includes(serviceId);

                card.querySelectorAll('input, select, textarea').forEach(function (field) {
                    field.disabled = !isSelected;
                });
            });
        }
    });

    syncAll();
});
</script>
@endsection
