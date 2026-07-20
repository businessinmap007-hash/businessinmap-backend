@extends('admin-v2.layouts.master')

@section('title', 'Service Catalog Matrix')
@section('body_class', 'admin-v2 admin-v2-service-catalog-matrix')

@section('content')
@php
    $nameOf = function ($item) {
        $ar = trim((string) ($item->name_ar ?? ''));
        $en = trim((string) ($item->name_en ?? ''));
        return $ar !== '' ? $ar : ($en !== '' ? $en : ('#' . ($item->id ?? '')));
    };

    $rootsSafe = collect($roots ?? []);
    $servicesSafe = collect($services ?? []);
    $childrenSafe = collect($children ?? []);
    $childCountSafe = (int) ($childCount ?? $childrenSafe->count());
    $serviceUsageCountsSafe = $serviceUsageCounts ?? [];
    $childActiveServicesSafe = $childActiveServices ?? [];

    // service id => display name, for the "already active" pills on each child.
    $serviceNameById = $servicesSafe->mapWithKeys(fn ($s) => [(int) $s->id => $nameOf($s)])->all();
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">Service Catalog Matrix</h1>
            <div class="a2-page-subtitle">
                {{ __('شاشة تنظيمية للأدمن لتحديد الخدمات واختياراتها المسموحة لكل Category Child. البزنس سيختار لاحقًا فقط مما تم ضبطه هنا.') }}
            </div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.categories.services-bulk.index', $activeRootId ? ['root_id' => $activeRootId] : []) }}" class="a2-btn a2-btn-ghost">Bulk Services + Fees</a>
            <a href="{{ route('admin.platform-service-item-types.index') }}" class="a2-btn a2-btn-ghost">Service Item Types</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="a2-card a2-card--soft a2-mb-16">
        <div class="a2-section-title">{{ __('الفكرة العملية') }}</div>
        <div class="a2-section-subtitle">
            {{ __('اختر Root، ثم فعّل خدمة أو أكثر. كل خدمة تظهر ككارت — اضغطه ليفتح اختياراته الفرعية وتحدد منها ما تريد. بعدها اختر الـ children التي ستستقبل هذه الخدمات، وطبّق دفعة واحدة. مثال: خدمة الحجز مع Child فندق = غرفة فردية، مزدوجة، جناح. نفس خدمة الحجز مع Child ملعب = ملعب خماسي، سباعي.') }}
        </div>
    </div>

    <div class="a2-card a2-mb-16">
        <div class="a2-flex-between">
            <div>
                <h2 class="a2-section-title">{{ __('1) التصنيف الرئيسي') }}</h2>
                <div class="a2-section-subtitle">{{ __('غيّر الـ Root لعرض الـ children الخاصة به.') }}</div>
            </div>
        </div>
        <div class="a2-actionsbar a2-mt-12">
            @foreach($rootsSafe as $root)
                @php $rid = (int) $root->id; @endphp
                <a href="{{ route('admin.service-catalog-matrix.index', ['root_id' => $rid]) }}" class="a2-btn {{ $rid === (int) $activeRootId ? 'a2-btn-primary' : 'a2-btn-ghost' }}">
                    {{ $nameOf($root) }}
                    <span class="a2-pill a2-pill-gray">{{ collect($root->children ?? [])->count() }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <form method="POST" action="{{ route('admin.service-catalog-matrix.apply') }}" id="serviceCatalogMatrixForm">
        @csrf
        <input type="hidden" name="root_id" value="{{ (int) $activeRootId }}">

        <div class="a2-card a2-mb-16">
            <div class="a2-flex-between">
                <div>
                    <h2 class="a2-section-title">{{ __('2) الخدمات واختياراتها الفرعية') }}</h2>
                    <div class="a2-section-subtitle">
                        {{ __('فعّل الخدمة من مربع الاختيار في رأس الكارت، ثم افتح الكارت لتحديد اختياراتها الفرعية. يمكن تفعيل أكثر من خدمة معًا.') }}
                    </div>
                </div>
            </div>

            @if($servicesSafe->isEmpty())
                <div class="a2-alert a2-alert-warning a2-mt-12">{{ __('لا توجد خدمات مفعلة حاليًا.') }}</div>
            @else
                <div class="a2-mt-16">
                    @foreach($servicesSafe as $service)
                        @php
                            $sid = (int) $service->id;
                            $itemTypes = collect($service->item_types ?? []);
                            $usage = (int) ($serviceUsageCountsSafe[$sid] ?? 0);
                        @endphp
                        <details class="a2-card a2-card--section a2-mb-12 js-service-card" data-service-id="{{ $sid }}">
                            <summary class="a2-card-head" style="cursor:pointer;list-style:none;">
                                <label class="a2-check-inline" onclick="event.stopPropagation();" style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                    <input type="checkbox" name="services[]" value="{{ $sid }}" class="js-service-toggle">
                                    <span>
                                        <strong>{{ $nameOf($service) }}</strong>
                                        <small dir="ltr">{{ $service->key }}</small>
                                        <span class="a2-pill a2-pill-gray">{{ __('مفعّلة لـ') }} {{ $usage }}/{{ $childCountSafe }}</span>
                                        <span class="a2-pill a2-pill-gray">{{ $itemTypes->count() }} {{ __('اختيار فرعي') }}</span>
                                    </span>
                                </label>
                                <span class="a2-muted js-service-hint">{{ __('اضغط لعرض الاختيارات ▾') }}</span>
                            </summary>

                            <div class="js-service-body">
                                @if($itemTypes->isEmpty())
                                    <div class="a2-alert a2-alert-warning a2-mt-12">
                                        {{ __('لا توجد اختيارات فرعية لهذه الخدمة. أضفها أولًا من صفحة Service Item Types.') }}
                                    </div>
                                @else
                                    <div class="a2-page-actions a2-mt-12" style="justify-content:flex-start;">
                                        <button type="button" class="a2-btn a2-btn-sm a2-btn-ghost js-check-types">{{ __('تحديد كل الاختيارات') }}</button>
                                        <button type="button" class="a2-btn a2-btn-sm a2-btn-ghost js-uncheck-types">{{ __('إلغاء الكل') }}</button>
                                    </div>
                                    <div class="a2-check-grid a2-mt-12">
                                        @foreach($itemTypes as $type)
                                            <label class="a2-check-card">
                                                <span>
                                                    <strong>{{ $type->name_ar ?: ($type->name_en ?: $type->key) }}</strong>
                                                    <small dir="ltr">{{ $type->key }}</small>
                                                </span>
                                                <input type="checkbox" name="item_types[{{ $sid }}][]" value="{{ $type->key }}" class="js-item-type" disabled>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="a2-card a2-mb-16">
            <div class="a2-flex-between">
                <div>
                    <h2 class="a2-section-title">{{ __('3) اختر الـ Children التي ستأخذ الخدمات') }}</h2>
                    <div class="a2-section-subtitle">{{ __('يمكن اختيار أكثر من child وتطبيق نفس الخدمات واختياراتها عليهم دفعة واحدة.') }}</div>
                </div>
                <div class="a2-page-actions">
                    <button type="button" class="a2-btn a2-btn-ghost" id="checkChildren">{{ __('تحديد الكل') }}</button>
                    <button type="button" class="a2-btn a2-btn-ghost" id="uncheckChildren">{{ __('إلغاء الكل') }}</button>
                </div>
            </div>

            @if($childrenSafe->isEmpty())
                <div class="a2-alert a2-alert-warning a2-mt-12">{{ __('لا توجد أقسام فرعية لهذا التصنيف.') }}</div>
            @else
                <div class="a2-check-grid a2-mt-16">
                    @foreach($childrenSafe as $child)
                        @php
                            $childId = (int) $child->id;
                            $activeServiceIds = collect($childActiveServicesSafe[$childId] ?? []);
                        @endphp
                        <label class="a2-check-card">
                            <span>
                                <strong>{{ $nameOf($child) }}</strong>
                                <small>Child #{{ $childId }}</small>
                                @if($activeServiceIds->isNotEmpty())
                                    @foreach($activeServiceIds as $asid)
                                        <span class="a2-pill a2-pill-success">{{ $serviceNameById[(int) $asid] ?? ('#' . $asid) }}</span>
                                    @endforeach
                                @else
                                    <small>{{ __('لا توجد خدمات مفعّلة بعد') }}</small>
                                @endif
                            </span>
                            <input type="checkbox" name="child_ids[]" value="{{ $childId }}" class="js-catalog-child">
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="a2-card a2-mb-16">
            <h2 class="a2-section-title">{{ __('4) طريقة التطبيق') }}</h2>
            <div class="a2-section-subtitle">{{ __('تُطبَّق على كل الخدمات المفعّلة أعلاه.') }}</div>
            <div class="a2-check-grid a2-mt-16">
                <label class="a2-check-card">
                    <span><strong>{{ __('استبدال الاختيارات') }}</strong><small>{{ __('يجعل المختار هو القائمة النهائية للـ children المختارة.') }}</small></span>
                    <input type="radio" name="mode" value="replace" checked>
                </label>
                <label class="a2-check-card">
                    <span><strong>{{ __('إضافة فقط') }}</strong><small>{{ __('يضيف الاختيارات الجديدة فوق الموجود بدون حذف القديم.') }}</small></span>
                    <input type="radio" name="mode" value="append">
                </label>
                <label class="a2-check-card">
                    <span><strong>{{ __('حذف اختيارات') }}</strong><small>{{ __('يحذف الاختيارات المحددة فقط من children المختارة.') }}</small></span>
                    <input type="radio" name="mode" value="remove">
                </label>
                <label class="a2-check-card">
                    <span><strong>{{ __('تعطيل الخدمات للـ children') }}</strong><small>{{ __('يعطل الخدمات المفعّلة نفسها للـ children المختارة.') }}</small></span>
                    <input type="radio" name="mode" value="disable_service">
                </label>
            </div>
        </div>

        <div class="a2-card a2-mb-16">
            <h2 class="a2-section-title">{{ __('5) إعدادات سريعة') }}</h2>
            <div class="a2-section-subtitle">{{ __('تُطبَّق على كل الخدمات المفعّلة.') }}</div>
            <div class="a2-form-grid-3 a2-mt-12">
                <label class="a2-check-card"><span>{{ __('يتطلب عنصر قابل للحجز') }}</span><input type="checkbox" name="requires_bookable_item" value="1" checked></label>
                <label class="a2-check-card"><span>{{ __('يدعم الكمية') }}</span><input type="checkbox" name="supports_quantity" value="1" checked></label>
                <label class="a2-check-card"><span>{{ __('يدعم عدد الضيوف/الأفراد') }}</span><input type="checkbox" name="supports_guest_count" value="1"></label>
            </div>
            <div class="a2-form-group a2-mt-12">
                <label class="a2-label">{{ __('ملاحظات داخلية') }}</label>
                <input class="a2-input" name="notes" placeholder="{{ __('مثال: فروع الفنادق ترى أنواع غرف فقط') }}">
            </div>
        </div>

        <div class="a2-card">
            <div class="a2-flex-between">
                <div class="a2-actionsbar">
                    <span class="a2-pill a2-pill-gray">Root: {{ $activeRoot ? $nameOf($activeRoot) : '—' }}</span>
                    <span class="a2-pill a2-pill-gray">{{ __('الخدمات المفعّلة:') }}  278@php
    $nameOf = function ($item) {
        $ar = trim((string) ($item->name_ar ?? ''));
        $en = trim((string) ($item->name_en ?? ''));
        return $ar !== '' ? $ar : ($en !== '' ? $en : ('#' . ($item->id ?? '')));
    };

    $rootsSafe = collect($roots ?? []);
    $servicesSafe = collect($services ?? []);
    $childrenSafe = collect($children ?? []);
    $childCountSafe = (int) ($childCount ?? $childrenSafe->count());
    $serviceUsageCountsSafe = $serviceUsageCounts ?? [];
    $childActiveServicesSafe = $childActiveServices ?? [];

    // service id => display name, for the "already active" pills on each child.
    $serviceNameById = $servicesSafe->mapWithKeys(fn ($s) => [(int) $s->id => $nameOf($s)])->all();
@endphp279 </span>
                    <span class="a2-pill a2-pill-gray">{{ __('الأقسام المختارة:') }}  282@php
    $nameOf = function ($item) {
        $ar = trim((string) ($item->name_ar ?? ''));
        $en = trim((string) ($item->name_en ?? ''));
        return $ar !== '' ? $ar : ($en !== '' ? $en : ('#' . ($item->id ?? '')));
    };

    $rootsSafe = collect($roots ?? []);
    $servicesSafe = collect($services ?? []);
    $childrenSafe = collect($children ?? []);
    $childCountSafe = (int) ($childCount ?? $childrenSafe->count());
    $serviceUsageCountsSafe = $serviceUsageCounts ?? [];
    $childActiveServicesSafe = $childActiveServices ?? [];

    // service id => display name, for the "already active" pills on each child.
    $serviceNameById = $servicesSafe->mapWithKeys(fn ($s) => [(int) $s->id => $nameOf($s)])->all();
@endphp283 </span>
                </div>
                <button class="a2-btn a2-btn-primary">{{ __('تطبيق Service Catalog Matrix') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function setAll(nodes, checked) {
        nodes.forEach(function (el) { if (!el.disabled) el.checked = checked; });
    }

    // Per-service card: toggle enables/opens its item-type checkboxes.
    document.querySelectorAll('.js-service-card').forEach(function (card) {
        var toggle = card.querySelector('.js-service-toggle');
        var itemTypes = Array.from(card.querySelectorAll('.js-item-type'));
        var checkBtn = card.querySelector('.js-check-types');
        var uncheckBtn = card.querySelector('.js-uncheck-types');

        function syncEnabled() {
            var on = toggle.checked;
            card.classList.toggle('is-active', on);
            itemTypes.forEach(function (cb) {
                cb.disabled = !on;
                if (!on) cb.checked = false;
            });
            if (on && !card.open) card.open = true;
            updateCounters();
        }

        toggle.addEventListener('change', syncEnabled);
        if (checkBtn) checkBtn.addEventListener('click', function () {
            if (!toggle.checked) { toggle.checked = true; syncEnabled(); }
            setAll(itemTypes, true);
        });
        if (uncheckBtn) uncheckBtn.addEventListener('click', function () { setAll(itemTypes, false); });

        syncEnabled();
    });

    var childBoxes = Array.from(document.querySelectorAll('.js-catalog-child'));
    document.getElementById('checkChildren')?.addEventListener('click', function () { setAll(childBoxes, true); updateCounters(); });
    document.getElementById('uncheckChildren')?.addEventListener('click', function () { setAll(childBoxes, false); updateCounters(); });
    childBoxes.forEach(function (cb) { cb.addEventListener('change', updateCounters); });

    var servicesCountEl = document.getElementById('selectedServicesCount');
    var childrenCountEl = document.getElementById('selectedChildrenCount');

    function updateCounters() {
        if (servicesCountEl) servicesCountEl.textContent = document.querySelectorAll('.js-service-toggle:checked').length;
        if (childrenCountEl) childrenCountEl.textContent = document.querySelectorAll('.js-catalog-child:checked').length;
    }

    document.getElementById('serviceCatalogMatrixForm')?.addEventListener('submit', function (e) {
        var services = document.querySelectorAll('.js-service-toggle:checked').length;
        var children = document.querySelectorAll('.js-catalog-child:checked').length;
        if (services === 0) { e.preventDefault(); alert('فعّل خدمة واحدة على الأقل.'); return; }
        if (children === 0) { e.preventDefault(); alert('اختر قسمًا فرعيًا واحدًا على الأقل.'); return; }
    });

    updateCounters();
});
</script>
@endpush
