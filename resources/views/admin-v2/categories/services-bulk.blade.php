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

                {{-- «السابق والتالى يجب ان يكون بجوار الابن المختار» — the two
                     buttons sit either side of the name, not at the ends of a
                     wide row, so a walk is a pair of clicks in one place.

                     Pinned because stepping re-draws the cards below it, and
                     without the pin the buttons slide out from under the
                     cursor. It never scrolls: «لا يجب ان ترفع الشاشة لاعلى». --}}
                <div id="childNav" class="a2-mt-12"
                     style="display:none;align-items:center;justify-content:center;gap:8px;
                            padding:8px 12px;border:1px solid var(--a2-border,#d4d4d8);border-radius:10px;
                            position:sticky;top:0;z-index:5;background:var(--a2-card,#fff);">
                    <button type="button" class="a2-btn a2-btn-ghost" id="childPrev">↦ {{ __('السابق') }}</button>

                    <span style="font-weight:600;" id="childNavName"></span>
                    <span class="a2-badge" id="childNavPos"></span>

                    <button type="button" class="a2-btn a2-btn-ghost" id="childNext">{{ __('التالي') }} ↤</button>
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
                    {{-- Chips, not a tab strip — the same shape as
                         category-child-options/bulk: what is chosen sits on the
                         first row, everything else hides behind «Other», and
                         pressing any chip opens that one service below and
                         closes the rest. --}}
                    <div id="serviceChipBar" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;"></div>

                    <div id="serviceOtherBar" style="display:none;gap:6px;flex-wrap:wrap;margin-bottom:12px;
                                                     padding:8px;border:1px dashed var(--a2-border,#d4d4d8);border-radius:10px;"></div>

                    {{-- Kept, hidden, and still the thing the JS drives: the
                         card show/hide already hangs off these, so the chips
                         press them rather than replacing the machinery. --}}
                    <div class="a2-tabs" id="feeTabs" style="display:none;">
                        @foreach($servicesSafe as $service)
                            @php
                                $serviceId = (int) $service->id;
                            @endphp

                            <button
                                type="button"
                                class="a2-tab js-fee-tab"
                                data-service-id="{{ $serviceId }}"
                                data-service-name="{{ $nameOf($service) }}"
                                data-service-key="{{ $service->key }}"
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
                                            {{ __('اختر الفروع المناسبة لهذا التصنيف — صاحب الحساب سيختار مما تحدده هنا فقط. اترك الكل فارغًا للسماح بجميع الأنواع. ما تغيّره هنا يُطبَّق على كل الفروع المحددة؛ وما لا تلمسه يبقى لكل فرع كما هو.') }}
                                        </div>

                                        <div class="a2-alert a2-alert-warning js-branch-mixed-warning" hidden>
                                            {{ __('الأقسام الفرعية المختارة تحتوي اختيارات مختلفة لهذه الخدمة. يُعرض أول اختيار للاطّلاع فقط؛ ولن يُكتب على الباقي إلا إذا غيّرت الاختيار هنا بنفسك.') }}
                                        </div>

                                        @php
                                            // Branches this root's children already use come first and are
                                            // shown; the rest are folded away. The picker used to list all 21
                                            // whatever root was selected, and a screen that shows everything
                                            // invites ticking everything.
                                            $inUseCount = (int) ($branchData['in_use_count'] ?? 0);
                                            $hiddenCount = count($branchData['branches']) - $inUseCount;
                                        @endphp

                                        @if($inUseCount > 0 && $hiddenCount > 0)
                                            <div class="a2-section-subtitle a2-mt-8">
                                                {{ __('يُعرض :n فرعًا يخصّ هذا القسم.', ['n' => $inUseCount]) }}
                                                <button type="button" class="a2-btn a2-btn-ghost a2-btn-sm js-branch-show-all"
                                                        data-service-id="{{ $serviceId }}">
                                                    {{ __('أظهر كل الفروع (:n)', ['n' => $hiddenCount]) }}
                                                </button>
                                            </div>
                                        @endif

                                        {{-- One picker fills from the FIRST selected child, so its
                                             contents are a proposal until someone moves a checkbox.
                                             Without this flag, saving wrote that one list to every
                                             selected child — see typesTouched() in the controller. --}}
                                        <input type="hidden" class="js-types-touched"
                                               name="types_touched[{{ $serviceId }}]"
                                               value="{{ old("types_touched.$serviceId") ? 1 : 0 }}">

                                        <div class="a2-branch-list a2-mt-12">
                                            @foreach($branchData['branches'] as $branch)
                                                <div class="a2-branch"
                                                     @class(['js-branch-offroot' => $inUseCount > 0 && ! ($branch['in_use'] ?? true)])
                                                     @if($inUseCount > 0 && ! ($branch['in_use'] ?? true)) hidden @endif>
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

                                <p class="a2-muted" style="font-size:12px;">{{ __("الرسوم لم تعد تُضبَط هنا — رسمٌ واحدٌ لكل ابن الآن، لا لكل خدمة. عدّله من «طاولة عمل الابن» أو «رسوم الأبناء (جماعي)».") }}</p>
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
                        {{ __('الفروع المختارة:') }} <strong id="selectedChildrenCount">0</strong>
                    </span>
                    <span class="a2-pill a2-pill-gray">
                        {{ __('الخدمات المختارة:') }} <strong id="selectedServicesCount">0</strong>
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

    function markTypesTouched(serviceId, touched) {
        const block = branchBlock(serviceId);
        const flag = block ? block.querySelector('.js-types-touched') : null;

        if (flag) {
            flag.value = touched ? '1' : '0';
        }
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

        // The picker is about to be re-filled from whatever is selected now, so
        // whatever was ticked before this point was not a choice about THESE
        // children.
        markTypesTouched(serviceId, false);

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

        const allowedTypes = new Set((row.allowed_item_types || []).map(String));

        /*
        | Drive the whole picker from allowed_item_types, and derive each branch
        | from its own types rather than trusting item_groups.
        |
        | The old order was the reverse — tick the stored branches, expand each
        | to ALL its nested types, then add anything left over. That was right
        | when a branch was a coarse group, and became destructive after the
        | kinds collapse put all 11 booking kinds in the single «أنواع الحجز»
        | branch: a clinic storing 4 kinds also stores item_groups=[84], so the
        | screen re-opened with all 11 ticked and saving wrote all 11 back,
        | quietly replacing the four-kind choice made in the child workbench.
        |
        | A branch is now ticked only when every one of its types is allowed,
        | which is exactly what ticking it means on save.
        */
        block.querySelectorAll('.js-type-checkbox').forEach(function (typeCb) {
            typeCb.checked = allowedTypes.has(String(typeCb.value));
        });

        block.querySelectorAll('.js-branch-checkbox').forEach(function (branchCb) {
            const branchEl = branchCb.closest('.a2-branch');

            if (!branchEl) {
                return;
            }

            const types = branchEl.querySelectorAll('.js-type-checkbox');
            const allTicked = types.length > 0
                && Array.prototype.every.call(types, function (t) { return t.checked; });

            branchCb.checked = allTicked;
            branchEl.classList.toggle('is-selected', allTicked);
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

        renderServiceChips();
        refreshChildNav();

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

    /*
    |--------------------------------------------------------------------------
    | Service chips — the shape of category-child-options/bulk
    |--------------------------------------------------------------------------
    |
    | «اريدها بنفس طريقة عمل category-child-options/bulk/edit فى الشكل واسلوب
    | العمل». There it is: what is chosen sits on the first row, the rest hide
    | behind «Other», and pressing any chip opens that one panel below.
    |
    | The chips do not replace the tab machinery, they press it — the cards
    | already show and hide off `.js-fee-tab`, and two mechanisms driving one
    | panel is how they drift apart.
    */
    const serviceChipBar = document.getElementById('serviceChipBar');
    const serviceOtherBar = document.getElementById('serviceOtherBar');
    let otherServicesOpen = false;

    function serviceChip(tab, chosen) {
        const button = document.createElement('button');
        const id = String(tab.dataset.serviceId || '');
        const active = tab.classList.contains('is-active') && !tab.hidden;

        button.type = 'button';
        button.className = 'a2-btn a2-btn-sm ' + (chosen ? 'a2-btn-primary' : 'a2-btn-ghost');
        button.textContent = tab.dataset.serviceName || tab.textContent.trim();

        if (active) {
            button.style.outline = '2px solid currentColor';
        }

        button.title = chosen
            ? @json(__('اعرضها بالأسفل'))
            : @json(__('فعّل الخدمة واعرضها بالأسفل'));

        button.addEventListener('click', function () {
            // An «Other» chip switches the service on first — the panel below is
            // only meaningful for a service this save actually touches, and its
            // fields stay disabled until then.
            if (!chosen) {
                const box = document.querySelector('.js-service-checkbox[value="' + id + '"]');

                if (box && !box.checked) {
                    box.indeterminate = false;
                    box.checked = true;
                    box.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            setActiveFeeCard(id);
            renderServiceChips();
        });

        return button;
    }

    function renderServiceChips() {
        if (!serviceChipBar || !serviceOtherBar) {
            return;
        }

        const chosenIds = getSelectedServiceIds();

        serviceChipBar.innerHTML = '';
        serviceOtherBar.innerHTML = '';

        const others = [];

        feeTabs.forEach(function (tab) {
            const id = String(tab.dataset.serviceId || '');

            if (chosenIds.includes(id)) {
                serviceChipBar.appendChild(serviceChip(tab, true));
            } else {
                others.push(tab);
            }
        });

        if (!others.length) {
            serviceOtherBar.style.display = 'none';
            return;
        }

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'a2-btn a2-btn-sm a2-btn-ghost';
        toggle.textContent = @json(__('أخرى')) + ' (' + others.length + ')';
        toggle.addEventListener('click', function () {
            otherServicesOpen = !otherServicesOpen;
            renderServiceChips();
        });
        serviceChipBar.appendChild(toggle);

        serviceOtherBar.style.display = otherServicesOpen ? 'flex' : 'none';
        others.forEach(function (tab) { serviceOtherBar.appendChild(serviceChip(tab, false)); });
    }

    /*
    |--------------------------------------------------------------------------
    | Walking the children one at a time
    |--------------------------------------------------------------------------
    */
    const childNav = document.getElementById('childNav');
    const childNavName = document.getElementById('childNavName');
    const childNavPos = document.getElementById('childNavPos');

    function childLabel(input) {
        const card = input.closest('.a2-check-card');
        const name = card ? card.querySelector('strong') : null;

        return name ? name.textContent.trim() : ('#' + input.value);
    }

    function refreshChildNav() {
        if (!childNav) {
            return;
        }

        const inputs = Array.from(childInputs).filter(function (input) {
            return input.offsetParent !== null || input.checked;
        });

        if (!inputs.length) {
            childNav.style.display = 'none';
            return;
        }

        childNav.style.display = 'flex';

        const picked = inputs.filter(function (input) { return input.checked; });

        if (picked.length === 1) {
            childNavName.textContent = childLabel(picked[0]);
            childNavPos.textContent = (inputs.indexOf(picked[0]) + 1) + ' / ' + inputs.length;
            return;
        }

        childNavName.textContent = picked.length
            ? @json(__('عدة أقسام محددة')) + ' (' + picked.length + ')'
            : @json(__('لم يُحدَّد قسم — اضغط «التالي» للبدء'));
        childNavPos.textContent = inputs.length;
    }

    function stepChild(delta) {
        const inputs = Array.from(childInputs).filter(function (input) {
            return input.offsetParent !== null || input.checked;
        });

        if (!inputs.length) {
            return;
        }

        // Several ticked (or none) has no "current", so a step starts the walk
        // from whichever end it was heading towards.
        const picked = inputs.filter(function (input) { return input.checked; });
        const from = picked.length === 1 ? inputs.indexOf(picked[0]) : (delta > 0 ? -1 : 0);

        let next = from + delta;

        if (next < 0) { next = inputs.length - 1; }
        if (next >= inputs.length) { next = 0; }

        inputs.forEach(function (input, index) { input.checked = index === next; });

        // No scrollIntoView. «لا يجب ان ترفع الشاشة لاعلى» — the page moves when
        // the admin scrolls it and at no other time.
        fillVisibleServiceFeeCards();
        syncAll();
    }

    const childPrev = document.getElementById('childPrev');
    const childNext = document.getElementById('childNext');

    if (childPrev) { childPrev.addEventListener('click', function () { stepChild(-1); }); }
    if (childNext) { childNext.addEventListener('click', function () { stepChild(1); }); }

    document.querySelectorAll('input[name="mode"]').forEach(function (input) {
        input.addEventListener('change', function () {
            syncAll();
        });
    });

    /*
    | Typing a fee amount ticks its own enable box.
    |
    | serviceFeePayload() zeroes any amount whose *_fee_enabled is off, so an
    | admin who typed 25 into a card whose toggle he never noticed saved 0.00
    | and got the same success message as a real save — the fee simply was not
    | there afterwards. The toggle now follows the number, visibly, so the two
    | can no longer disagree. Clearing the amount is left alone: unticking is
    | still how a fee is switched off.
    */
    document.querySelectorAll('.js-service-fee-card input[type="number"]').forEach(function (amountInput) {
        const name = String(amountInput.name || '');
        const match = name.match(/^service_fees\[(\d+)\]\[(business|client)_fee_amount\]$/);

        if (!match) {
            return;
        }

        const toggle = amountInput
            .closest('.js-service-fee-card')
            ?.querySelector('[name="service_fees[' + match[1] + '][' + match[2] + '_fee_enabled]"]');

        if (!toggle) {
            return;
        }

        amountInput.addEventListener('input', function () {
            if (parseFloat(amountInput.value) > 0 && !toggle.checked) {
                toggle.checked = true;
                toggle.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });

    document.querySelectorAll('.js-branch-checkbox').forEach(function (branchCb) {
        branchCb.addEventListener('change', function () {
            markTypesTouched(branchCb.dataset.serviceId, true);
            setBranchNestedTypes(branchCb, branchCb.checked);
        });
    });

    document.querySelectorAll('.js-type-checkbox').forEach(function (typeCb) {
        typeCb.addEventListener('change', function () {
            markTypesTouched(typeCb.dataset.serviceId, true);

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

    function toggleBranchTypes(branchEl) {
        const types = branchEl?.querySelector('.a2-branch-types');
        if (!types) return;
        types.hidden = !types.hidden;
        branchEl.classList.toggle('is-open', !types.hidden);
    }

    document.querySelectorAll('.js-branch-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            toggleBranchTypes(btn.closest('.a2-branch'));
        });
    });

    // The whole branch head is clickable to open its types (collapsed by default),
    // except when interacting with the branch checkbox itself.
    document.querySelectorAll('.a2-branch-head').forEach(function (head) {
        head.style.cursor = 'pointer';
        head.addEventListener('click', function (e) {
            if (e.target.closest('label') || e.target.closest('input') || e.target.closest('.js-branch-toggle')) {
                return;
            }
            toggleBranchTypes(head.closest('.a2-branch'));
        });
    });

    /*
    | «أظهر كل الفروع» — reveals the branches this root does not use yet, which
    | is how a root starts using a new one. Nothing is filtered server-side, so
    | a hidden branch that was already ticked still submits normally.
    */
    document.querySelectorAll('.js-branch-show-all').forEach(function (button) {
        button.addEventListener('click', function () {
            const block = branchBlock(button.dataset.serviceId);

            if (!block) {
                return;
            }

            block.querySelectorAll('.js-branch-offroot').forEach(function (branchEl) {
                branchEl.hidden = false;
            });

            button.hidden = true;
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
