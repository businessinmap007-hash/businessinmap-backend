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
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">Bulk Services + Fees</h1>
            <div class="a2-page-subtitle">
                اختيار مجموعة فروع + مجموعة خدمات + تطبيق الربط والرسوم دفعة واحدة
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.categories.index', $rootIdInt > 0 ? ['root_id' => $rootIdInt] : []) }}"
               class="a2-btn a2-btn-ghost">
                رجوع
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

        <input type="hidden" name="root_id" id="bulk_root_id" value="{{ $rootIdInt }}">

        {{-- Roots --}}
        <div class="a2-card" style="margin-bottom:16px;">
            <div class="a2-section-head">
                <div>
                    <h2 class="a2-section-title">التصنيفات الرئيسية</h2>
                    <div class="a2-section-subtitle">اختر التصنيف الرئيسي لعرض الفروع الخاصة به فقط</div>
                </div>
            </div>

            @if($rootsSafe->isEmpty())
                <div class="a2-muted">لا توجد تصنيفات رئيسية بها فروع.</div>
            @else
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    @foreach($rootsSafe as $root)
                        @php
                            $rid = (int) $root->id;
                            $isActive = $rid === $rootIdInt || ($rootIdInt === 0 && $loop->first);
                            $childrenCount = collect($root->children ?? [])->count();
                        @endphp

                        <button
                            type="button"
                            class="a2-btn {{ $isActive ? 'a2-btn-primary' : 'a2-btn-ghost' }} js-root-tab"
                            data-root-id="{{ $rid }}"
                        >
                            {{ $nameOf($root) }}
                            <span class="a2-badge" style="margin-inline-start:6px;">{{ $childrenCount }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Children --}}
        <div class="a2-card" style="margin-bottom:16px;">
            <div class="a2-section-head">
                <div>
                    <h2 class="a2-section-title">الأقسام الفرعية</h2>
                    <div class="a2-section-subtitle">اختر مجموعة الفروع التي سيتم تطبيق الخدمات والرسوم عليها</div>
                </div>

                <div class="a2-page-actions">
                    <button type="button" class="a2-btn a2-btn-ghost" id="checkVisibleChildren">تحديد الظاهر</button>
                    <button type="button" class="a2-btn a2-btn-ghost" id="uncheckVisibleChildren">إلغاء الظاهر</button>
                </div>
            </div>

            @foreach($rootsSafe as $root)
                @php
                    $rid = (int) $root->id;
                    $isActive = $rid === $rootIdInt || ($rootIdInt === 0 && $loop->first);
                    $children = collect($root->children ?? []);
                @endphp

                <div class="js-root-panel" data-root-id="{{ $rid }}" style="{{ $isActive ? '' : 'display:none;' }}">
                    @if($children->isEmpty())
                        <div class="a2-muted">لا توجد فروع داخل هذا التصنيف.</div>
                    @else
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;">
                            @foreach($children as $child)
                                <label class="a2-check-card">
                                    <input
                                        type="checkbox"
                                        name="category_ids[]"
                                        value="{{ $child->id }}"
                                        class="js-child-checkbox"
                                        {{ $isActive ? '' : 'disabled' }}
                                    >
                                    <span>{{ $nameOf($child) }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Services --}}
        <div class="a2-card" style="margin-bottom:16px;">
            <div class="a2-section-head">
                <div>
                    <h2 class="a2-section-title">الخدمات</h2>
                    <div class="a2-section-subtitle">اختر مجموعة الخدمات التي سيتم ربطها بالفروع المختارة</div>
                </div>

                <div class="a2-page-actions">
                    <button type="button" class="a2-btn a2-btn-ghost" id="checkServices">تحديد كل الخدمات</button>
                    <button type="button" class="a2-btn a2-btn-ghost" id="uncheckServices">إلغاء كل الخدمات</button>
                </div>
            </div>

            @if($servicesSafe->isEmpty())
                <div class="a2-muted">لا توجد خدمات مفعلة.</div>
            @else
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;">
                   @foreach($servicesSafe as $service)
    @php
        $serviceId = (int) $service->id;
        $activeCount = (int) ($activeServiceCountsSafe[$serviceId] ?? 0);
        $isFullyActive = $activeChildrenCountInt > 0 && $activeCount >= $activeChildrenCountInt;
        $isPartialActive = $activeCount > 0 && !$isFullyActive;
    @endphp

    <label class="a2-check-card">
        <input
            type="checkbox"
            name="platform_service_ids[]"
            value="{{ $serviceId }}"
            class="js-service-checkbox"
            data-active-count="{{ $activeCount }}"
            data-total-count="{{ $activeChildrenCountInt }}"
            @checked($isFullyActive)
        >
        <span>
            {{ $nameOf($service) }}
            <small class="a2-muted">({{ $service->key }})</small>

            @if($activeChildrenCountInt > 0)
                <span class="a2-badge" style="margin-inline-start:6px;">
                    {{ $activeCount }}/{{ $activeChildrenCountInt }}
                </span>
            @endif

            @if($isPartialActive)
                <small class="a2-muted" style="margin-inline-start:6px;">
                    مفعلة جزئيًا
                </small>
            @endif
        </span>
    </label>
@endforeach
                </div>
            @endif
        </div>

        {{-- Apply mode --}}
        <div class="a2-card" style="margin-bottom:16px;">
            <h2 class="a2-section-title">طريقة التطبيق</h2>

            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <label class="a2-check-card">
                    <input type="radio" name="mode" value="append" checked>
                    <span>إضافة / تحديث</span>
                </label>

                <label class="a2-check-card">
                    <input type="radio" name="mode" value="replace">
                    <span>استبدال خدمات الفروع المختارة</span>
                </label>

                <label class="a2-check-card">
                    <input type="radio" name="mode" value="remove">
                    <span>تعطيل الخدمات المختارة من الفروع</span>
                </label>
            </div>
        </div>

        {{-- Fees --}}
        <div class="a2-card" style="margin-bottom:16px;">
            <div class="a2-section-head">
                <div>
                    <h2 class="a2-section-title">رسوم موحدة للخدمات المختارة</h2>
                    <div class="a2-section-subtitle">
                        هذه القيم سيتم تطبيقها على كل فرع + كل خدمة تم اختيارها
                    </div>
                </div>
            </div>

            {{-- Fees --}}
{{-- Per Service Fees --}}
<div class="a2-card" style="margin-bottom:16px;">
    <div class="a2-section-head">
        <div>
            <h2 class="a2-section-title">Override رسوم كل خدمة منفصلة</h2>
            <div class="a2-section-subtitle">
                اختر الخدمات من الأعلى، ثم حدد رسوم كل خدمة بشكل مستقل.
            </div>
        </div>
    </div>

    @if($servicesSafe->isEmpty())
        <div class="a2-muted">لا توجد خدمات مفعلة.</div>
    @else
        <div class="a2-alert a2-alert-info" id="feesHelpBox">
            اختر خدمة واحدة أو أكثر من قسم الخدمات بالأعلى، وستظهر هنا إعدادات الرسوم لكل خدمة مختارة.
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(420px,1fr));gap:14px;">
            @foreach($servicesSafe as $service)
                @php
                    $serviceId = (int) $service->id;
                    $serviceTitle = $nameOf($service);
                @endphp

                <div
                    class="a2-card a2-card--soft js-service-fee-card"
                    data-service-id="{{ $serviceId }}"
                    style="display:none;"
                >
                    <div class="a2-section-head">
                        <div>
                            <h3 class="a2-section-title">
                                {{ $serviceTitle }}
                                <span class="a2-badge" dir="ltr">{{ $service->key }}</span>
                            </h3>
                            <div class="a2-section-subtitle">
                                Override خاص بهذه الخدمة فقط
                            </div>
                        </div>
                    </div>

                    <div class="a2-form-grid">
                        <div>
                            <label class="a2-label">العملة</label>
                            <input
                                class="a2-input"
                                name="service_fees[{{ $serviceId }}][currency]"
                                value="{{ old("service_fees.$serviceId.currency", 'EGP') }}"
                                maxlength="3"
                                dir="ltr"
                            >
                        </div>

                        <div>
                            <label class="a2-label">ملاحظات</label>
                            <input
                                class="a2-input"
                                name="service_fees[{{ $serviceId }}][fee_notes]"
                                value="{{ old("service_fees.$serviceId.fee_notes") }}"
                                placeholder="اختياري"
                            >
                        </div>
                    </div>

                    <div class="a2-grid-2 a2-mt-16" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
                        <div class="a2-card">
                            <div class="a2-section-title">رسوم البزنس</div>

                            <label class="a2-check" style="margin-top:10px;">
                                <input
                                    type="checkbox"
                                    name="service_fees[{{ $serviceId }}][business_fee_enabled]"
                                    value="1"
                                    @checked(old("service_fees.$serviceId.business_fee_enabled"))
                                >
                                <span>تفعيل رسوم البزنس</span>
                            </label>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px;">
                                <div>
                                    <label class="a2-label">نوع الرسوم</label>
                                    <select
                                        class="a2-input"
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

                                <div>
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

                        <div class="a2-card">
                            <div class="a2-section-title">رسوم العميل</div>

                            <label class="a2-check" style="margin-top:10px;">
                                <input
                                    type="checkbox"
                                    name="service_fees[{{ $serviceId }}][client_fee_enabled]"
                                    value="1"
                                    @checked(old("service_fees.$serviceId.client_fee_enabled"))
                                >
                                <span>تفعيل رسوم العميل</span>
                            </label>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px;">
                                <div>
                                    <label class="a2-label">نوع الرسوم</label>
                                    <select
                                        class="a2-input"
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

                                <div>
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
            @endforeach
        </div>
    @endif
</div>
        </div>

        {{-- Submit --}}
        <div class="a2-card">
            <button type="submit" class="a2-btn a2-btn-primary">
                تطبيق الخدمات والرسوم على المحدد
            </button>
        </div>
    </form>
</div>

<script>
window.BIM_SERVICE_FEE_MATRIX = @json($feeMatrixSafe);
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const rootTabs = document.querySelectorAll('.js-root-tab');
    const rootPanels = document.querySelectorAll('.js-root-panel');
    const rootInput = document.getElementById('bulk_root_id');

    const serviceInputs = document.querySelectorAll('.js-service-checkbox');
    const serviceFeeCards = document.querySelectorAll('.js-service-fee-card');
    const feesHelpBox = document.getElementById('feesHelpBox');

    const feeMatrix = window.BIM_SERVICE_FEE_MATRIX || {};
    const serviceDefaults = {};

    /*
    |--------------------------------------------------------------------------
    | Store initial default values from Platform Service fields
    |--------------------------------------------------------------------------
    */
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

        if (input) {
            input.value = value ?? '';
        }
    }

    function setCheckboxValue(card, serviceId, field, checked) {
        const input = getField(card, serviceId, field);

        if (input) {
            input.checked = !!checked;
        }
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

    function getServiceInput(serviceId) {
        return document.querySelector('.js-service-checkbox[value="' + serviceId + '"]');
    }

    function setServiceFeeCardState(card, active) {
        card.style.display = active ? '' : 'none';

        card.querySelectorAll('input, select, textarea').forEach(function (field) {
            field.disabled = !active;
        });
    }

    function visibleChildren() {
        const activePanel = Array.from(rootPanels).find(function (panel) {
            return panel.style.display !== 'none';
        });

        if (!activePanel) {
            return [];
        }

        return activePanel.querySelectorAll('.js-child-checkbox:not(:disabled)');
    }

    function getSelectedChildIds() {
        return Array.from(document.querySelectorAll('.js-child-checkbox:not(:disabled):checked'))
            .map(function (input) {
                return String(input.value);
            });
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

            const keys = [
                'business_fee_enabled',
                'business_fee_type',
                'business_fee_amount',
                'client_fee_enabled',
                'client_fee_type',
                'client_fee_amount',
                'currency',
                'fee_notes'
            ];

            keys.forEach(function (key) {
                if (String(first[key] ?? '') !== String(row[key] ?? '')) {
                    mixed = true;
                }
            });
        });

        if (first && rowsFound < selectedChildIds.length) {
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
            mixedBox.style.display = result.mixed ? '' : 'none';
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
        document.querySelectorAll('.js-service-checkbox:checked').forEach(function (input) {
            fillServiceFeeCard(input.value);
        });
    }

    function syncServiceFeeCards() {
        let visibleCount = 0;

        serviceFeeCards.forEach(function (card) {
            const serviceId = String(card.dataset.serviceId || '');
            const input = getServiceInput(serviceId);
            const active = !!(input && input.checked);

            setServiceFeeCardState(card, active);

            if (active) {
                visibleCount++;
                fillServiceFeeCard(serviceId);
            }
        });

        if (feesHelpBox) {
            feesHelpBox.style.display = visibleCount > 0 ? 'none' : '';
        }
    }

    function markServicePartialStates() {
        serviceInputs.forEach(function (input) {
            const activeCount = parseInt(input.dataset.activeCount || '0', 10);
            const totalCount = parseInt(input.dataset.totalCount || '0', 10);

            input.indeterminate = activeCount > 0 && totalCount > 0 && activeCount < totalCount;
        });
    }

    function activateRootLocally(rootId) {
        rootTabs.forEach(function (tab) {
            const active = tab.dataset.rootId === rootId;
            tab.classList.toggle('a2-btn-primary', active);
            tab.classList.toggle('a2-btn-ghost', !active);
        });

        rootPanels.forEach(function (panel) {
            const active = panel.dataset.rootId === rootId;
            panel.style.display = active ? '' : 'none';

            panel.querySelectorAll('.js-child-checkbox').forEach(function (input) {
                input.disabled = !active;
                input.checked = false;
            });
        });

        if (rootInput) {
            rootInput.value = rootId;
        }

        fillVisibleServiceFeeCards();
    }

    function reloadForRoot(rootId) {
        const url = new URL(window.location.href);
        url.searchParams.set('root_id', rootId);
        window.location.href = url.toString();
    }

    /*
    |--------------------------------------------------------------------------
    | Root tabs
    |--------------------------------------------------------------------------
    | الأفضل إعادة تحميل الصفحة عند تغيير Root حتى يتم تحميل activeServiceCounts
    | و feeMatrix الخاصة بالتصنيف الصحيح من الكنترول.
    */
    rootTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const rootId = tab.dataset.rootId;

            if (rootInput && String(rootInput.value) === String(rootId)) {
                activateRootLocally(rootId);
                return;
            }

            reloadForRoot(rootId);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Children actions
    |--------------------------------------------------------------------------
    */
    document.getElementById('checkVisibleChildren')?.addEventListener('click', function () {
        visibleChildren().forEach(function (input) {
            input.checked = true;
        });

        fillVisibleServiceFeeCards();
    });

    document.getElementById('uncheckVisibleChildren')?.addEventListener('click', function () {
        visibleChildren().forEach(function (input) {
            input.checked = false;
        });

        fillVisibleServiceFeeCards();
    });

    document.querySelectorAll('.js-child-checkbox').forEach(function (input) {
        input.addEventListener('change', function () {
            fillVisibleServiceFeeCards();
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Services actions
    |--------------------------------------------------------------------------
    */
    serviceInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            input.indeterminate = false;
            syncServiceFeeCards();
        });
    });

    document.getElementById('checkServices')?.addEventListener('click', function () {
        serviceInputs.forEach(function (input) {
            input.checked = true;
            input.indeterminate = false;
        });

        syncServiceFeeCards();
    });

    document.getElementById('uncheckServices')?.addEventListener('click', function () {
        serviceInputs.forEach(function (input) {
            input.checked = false;
            input.indeterminate = false;
        });

        syncServiceFeeCards();
    });

    /*
    |--------------------------------------------------------------------------
    | Initial state
    |--------------------------------------------------------------------------
    */
    markServicePartialStates();
    syncServiceFeeCards();
    fillVisibleServiceFeeCards();
});
</script>
@endsection
