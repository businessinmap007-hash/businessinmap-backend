@extends('admin-v2.layouts.master')

@section('title', 'Bulk Services + Fees')
@section('body_class', 'admin-v2 admin-v2-services-bulk')

@section('content')
@php
    $rootsSafe = collect($roots ?? []);
    $servicesSafe = collect($services ?? []);
    $rootIdInt = (int) ($rootId ?? 0);

    $activeServiceCountsSafe = $activeServiceCounts ?? [];
    $activeChildrenCountInt = (int) ($activeChildrenCount ?? 0);
    $feeMatrixSafe = $feeMatrix ?? [];

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
                صفحة موحدة لربط خدمات الأقسام الفرعية، ضبط إعدادات الخدمة، وتطبيق رسوم البزنس والعميل دفعة واحدة.
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.categories.index', $activeRootId > 0 ? ['root_id' => $activeRootId] : []) }}"
               class="a2-btn a2-btn-ghost">
                رجوع إلى الأقسام
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
                    <h2 class="a2-section-title">1) التصنيف الرئيسي</h2>
                    <div class="a2-section-subtitle">
                        اختر الروت الذي سيتم عرض فروعه. عند تغيير الروت سيتم إعادة تحميل الصفحة بنفس السياق.
                    </div>
                </div>
            </div>

            @if($rootsSafe->isEmpty())
                <div class="a2-muted">لا توجد تصنيفات رئيسية بها فروع.</div>
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
                    <h2 class="a2-section-title">2) الأقسام الفرعية</h2>
                    <div class="a2-section-subtitle">
                        اختر الفروع التي سيتم تطبيق الربط والرسوم عليها داخل:
                        <strong>{{ $activeRoot ? $nameOf($activeRoot) : '—' }}</strong>
                    </div>
                </div>

                <div class="a2-page-actions">
                    <button type="button" class="a2-btn a2-btn-ghost" id="checkVisibleChildren">تحديد الكل</button>
                    <button type="button" class="a2-btn a2-btn-ghost" id="uncheckVisibleChildren">إلغاء الكل</button>
                </div>
            </div>

            @if($activeChildren->isEmpty())
                <div class="a2-muted">لا توجد فروع داخل هذا التصنيف.</div>
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
                    <h2 class="a2-section-title">3) الخدمات</h2>
                    <div class="a2-section-subtitle">
                        اختر الخدمات المطلوب ربطها أو تعطيلها للفروع المختارة.
                    </div>
                </div>

                <div class="a2-page-actions">
                    <button type="button" class="a2-btn a2-btn-ghost" id="checkServices">تحديد كل الخدمات</button>
                    <button type="button" class="a2-btn a2-btn-ghost" id="uncheckServices">إلغاء كل الخدمات</button>
                </div>
            </div>

            @if($servicesSafe->isEmpty())
                <div class="a2-muted">لا توجد خدمات مفعلة.</div>
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
                                    <span class="a2-pill a2-pill-warning">مفعلة جزئيًا</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="a2-card a2-mb-16">
            <h2 class="a2-section-title">4) طريقة التطبيق</h2>
            <div class="a2-section-subtitle">
                اختر هل تريد إضافة/تحديث الخدمات، استبدالها، أو تعطيل الخدمات المختارة.
            </div>

            <div class="a2-check-grid a2-mt-16">
                <label class="a2-check-card">
                    <span>
                        <strong>إضافة / تحديث</strong>
                        <small>يضيف الخدمات المختارة للفروع، ويحدث إعداداتها ورسومها بدون تعطيل الخدمات الأخرى.</small>
                    </span>

                    <input type="radio" name="mode" value="append" checked>
                </label>

                <label class="a2-check-card">
                    <span>
                        <strong>استبدال خدمات الفروع المختارة</strong>
                        <small>يجعل الخدمات المختارة هي الخدمات النشطة للفروع المحددة، ويعطل غير المختار.</small>
                    </span>

                    <input type="radio" name="mode" value="replace">
                </label>

                <label class="a2-check-card">
                    <span>
                        <strong>تعطيل الخدمات المختارة</strong>
                        <small>يعطل الخدمات المختارة ورسومها للفروع المحددة فقط.</small>
                    </span>

                    <input type="radio" name="mode" value="remove">
                </label>
            </div>
        </div>

        <div class="a2-card a2-mb-16" id="feesSection">
            <div class="a2-flex-between">
                <div>
                    <h2 class="a2-section-title">5) رسوم الخدمات المختارة</h2>
                    <div class="a2-section-subtitle">
                        تظهر هنا فقط الخدمات التي تم اختيارها. عند الحفظ سيتم تطبيق القيم على كل الفروع المحددة.
                    </div>
                </div>
            </div>

            @if($servicesSafe->isEmpty())
                <div class="a2-muted">لا توجد خدمات مفعلة.</div>
            @else
                <div class="a2-alert a2-alert-info" id="feesHelpBox">
                    اختر خدمة واحدة أو أكثر من قسم الخدمات بالأعلى، وستظهر إعدادات رسوم كل خدمة هنا.
                </div>

                <div class="a2-alert a2-alert-warning" id="removeModeFeesNote" hidden>
                    وضع التعطيل لا يحتاج ضبط رسوم. سيتم تعطيل الربط والرسوم للخدمات المختارة.
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
                                            Override خاص بهذه الخدمة فقط. القيم هنا تطبق على الفروع المحددة.
                                        </div>

                                        <div class="a2-alert a2-alert-warning js-fee-mixed-warning" hidden>
                                            الفروع المختارة تحتوي قيم رسوم مختلفة لهذه الخدمة. سيتم عرض أول قيمة موجودة،
                                            وأي حفظ جديد سيطبق القيمة الجديدة على كل الفروع المختارة.
                                        </div>
                                    </div>
                                </div>

                                <div class="a2-form-grid a2-mt-16">
                                    <div class="a2-form-group">
                                        <label class="a2-label">العملة</label>
                                        <input
                                            class="a2-input"
                                            name="service_fees[{{ $serviceId }}][currency]"
                                            value="{{ old("service_fees.$serviceId.currency", 'EGP') }}"
                                            maxlength="3"
                                            dir="ltr"
                                        >
                                    </div>

                                    <div class="a2-form-group">
                                        <label class="a2-label">ملاحظات</label>
                                        <input
                                            class="a2-input"
                                            name="service_fees[{{ $serviceId }}][fee_notes]"
                                            value="{{ old("service_fees.$serviceId.fee_notes") }}"
                                            placeholder="اختياري"
                                        >
                                    </div>
                                </div>

                                <div class="a2-card-grid-2 a2-mt-16">
                                    <div class="a2-card-muted">
                                        <h4 class="a2-section-title">رسوم البزنس</h4>

                                        <label class="a2-check">
                                            <input
                                                type="checkbox"
                                                name="service_fees[{{ $serviceId }}][business_fee_enabled]"
                                                value="1"
                                                @checked(old("service_fees.$serviceId.business_fee_enabled"))
                                            >
                                            <span>تفعيل رسوم البزنس</span>
                                        </label>

                                        <div class="a2-form-grid a2-mt-12">
                                            <div class="a2-form-group">
                                                <label class="a2-label">نوع الرسوم</label>
                                                <select
                                                    class="a2-select"
                                                    name="service_fees[{{ $serviceId }}][business_fee_type]"
                                                >
                                                    <option value="fixed" @selected(old("service_fees.$serviceId.business_fee_type", 'fixed') === 'fixed')>
                                                        مبلغ ثابت
                                                    </option>
                                                    <option value="percent" @selected(old("service_fees.$serviceId.business_fee_type") === 'percent')>
                                                        نسبة %
                                                    </option>
                                                </select>
                                            </div>

                                            <div class="a2-form-group">
                                                <label class="a2-label">القيمة</label>
                                                <input
                                                    class="a2-input"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    name="service_fees[{{ $serviceId }}][business_fee_amount]"
                                                    value="{{ old("service_fees.$serviceId.business_fee_amount") }}"
                                                    placeholder="مثال: 10"
                                                >
                                            </div>
                                        </div>
                                    </div>

                                    <div class="a2-card-muted">
                                        <h4 class="a2-section-title">رسوم العميل</h4>

                                        <label class="a2-check">
                                            <input
                                                type="checkbox"
                                                name="service_fees[{{ $serviceId }}][client_fee_enabled]"
                                                value="1"
                                                @checked(old("service_fees.$serviceId.client_fee_enabled"))
                                            >
                                            <span>تفعيل رسوم العميل</span>
                                        </label>

                                        <div class="a2-form-grid a2-mt-12">
                                            <div class="a2-form-group">
                                                <label class="a2-label">نوع الرسوم</label>
                                                <select
                                                    class="a2-select"
                                                    name="service_fees[{{ $serviceId }}][client_fee_type]"
                                                >
                                                    <option value="fixed" @selected(old("service_fees.$serviceId.client_fee_type", 'fixed') === 'fixed')>
                                                        مبلغ ثابت
                                                    </option>
                                                    <option value="percent" @selected(old("service_fees.$serviceId.client_fee_type") === 'percent')>
                                                        نسبة %
                                                    </option>
                                                </select>
                                            </div>

                                            <div class="a2-form-group">
                                                <label class="a2-label">القيمة</label>
                                                <input
                                                    class="a2-input"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    name="service_fees[{{ $serviceId }}][client_fee_amount]"
                                                    value="{{ old("service_fees.$serviceId.client_fee_amount") }}"
                                                    placeholder="مثال: 2"
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
                        الفروع المختارة: <strong id="selectedChildrenCount">0</strong>
                    </span>
                    <span class="a2-pill a2-pill-gray">
                        الخدمات المختارة: <strong id="selectedServicesCount">0</strong>
                    </span>
                    <span class="a2-pill a2-pill-gray">
                        الوضع: <strong id="selectedModeLabel">إضافة / تحديث</strong>
                    </span>
                </div>

                <button type="submit" class="a2-btn a2-btn-primary" id="submitBulkBtn">
                    تطبيق الخدمات والرسوم
                </button>
            </div>
        </div>
    </form>
</div>

<script>
window.BIM_SERVICE_FEE_MATRIX = @json($feeMatrixSafe ?? []);
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
