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
    $itemTypesSafe = collect($itemTypes ?? []);
    $matrixSafe = $matrix ?? [];
    $serviceUsageCountsSafe = $serviceUsageCounts ?? [];
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">Service Catalog Matrix</h1>
            <div class="a2-page-subtitle">
                شاشة تنظيمية للأدمن لتحديد الخدمات والاختيارات المسموحة لكل Category Child. البزنس سيختار لاحقًا فقط مما تم ضبطه هنا.
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
        <div class="a2-section-title">الفكرة العملية</div>
        <div class="a2-section-subtitle">
            اختر Root ثم خدمة واحدة. تظهر كل الـ children المرتبطة بهذا الـ Root، وتحدد أي children تستقبل هذه الخدمة، وما هي اختيارات الخدمة المسموحة لها.
            مثال: خدمة الحجز مع Child فندق = غرفة فردية، مزدوجة، جناح، Royal Suite. نفس خدمة الحجز مع Child ملعب = ملعب خماسي، سباعي.
        </div>
    </div>

    <div class="a2-card a2-mb-16">
        <div class="a2-flex-between">
            <div>
                <h2 class="a2-section-title">1) التصنيف الرئيسي</h2>
                <div class="a2-section-subtitle">غيّر الـ Root لعرض الـ children الخاصة به.</div>
            </div>
        </div>
        <div class="a2-actionsbar a2-mt-12">
            @foreach($rootsSafe as $root)
                @php $rid = (int) $root->id; @endphp
                <a href="{{ route('admin.service-catalog-matrix.index', ['root_id' => $rid, 'service_id' => $activeServiceId]) }}" class="a2-btn {{ $rid === (int) $activeRootId ? 'a2-btn-primary' : 'a2-btn-ghost' }}">
                    {{ $nameOf($root) }}
                    <span class="a2-pill a2-pill-gray">{{ collect($root->children ?? [])->count() }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <div class="a2-card a2-mb-16">
        <h2 class="a2-section-title">2) الخدمة</h2>
        <div class="a2-service-check-grid a2-mt-12">
            @foreach($servicesSafe as $service)
                @php
                    $sid = (int) $service->id;
                    $activeCount = (int) ($serviceUsageCountsSafe[$sid] ?? 0);
                @endphp
                <a href="{{ route('admin.service-catalog-matrix.index', ['root_id' => $activeRootId, 'service_id' => $sid]) }}" class="a2-service-check-box {{ $sid === (int) $activeServiceId ? 'is-active' : '' }}">
                    <strong>{{ $nameOf($service) }}</strong>
                    <small dir="ltr">{{ $service->key }}</small>
                    <span class="a2-pill a2-pill-gray">{{ $activeCount }}/{{ $childrenSafe->count() }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <form method="POST" action="{{ route('admin.service-catalog-matrix.apply') }}" id="serviceCatalogMatrixForm">
        @csrf
        <input type="hidden" name="root_id" value="{{ (int) $activeRootId }}">
        <input type="hidden" name="service_id" value="{{ (int) $activeServiceId }}">

        <div class="a2-card a2-mb-16">
            <div class="a2-flex-between">
                <div>
                    <h2 class="a2-section-title">3) اختر الـ Children التي ستأخذ الخدمة</h2>
                    <div class="a2-section-subtitle">يمكن اختيار أكثر من child وتطبيق نفس اختيارات الخدمة عليهم دفعة واحدة.</div>
                </div>
                <div class="a2-page-actions">
                    <button type="button" class="a2-btn a2-btn-ghost" id="checkChildren">تحديد الكل</button>
                    <button type="button" class="a2-btn a2-btn-ghost" id="uncheckChildren">إلغاء الكل</button>
                </div>
            </div>

            <div class="a2-check-grid a2-mt-16">
                @foreach($childrenSafe as $child)
                    @php
                        $childId = (int) $child->id;
                        $row = $matrixSafe[$childId] ?? [];
                        $isActive = (bool) ($row['service_active'] ?? false);
                        $allowed = collect($row['allowed_item_types'] ?? [])->filter()->values();
                    @endphp
                    <label class="a2-check-card">
                        <span>
                            <strong>{{ $nameOf($child) }}</strong>
                            <small>Child #{{ $childId }}</small>
                            @if($isActive)
                                <span class="a2-pill a2-pill-success">الخدمة مفعلة</span>
                            @else
                                <span class="a2-pill a2-pill-gray">غير مفعلة</span>
                            @endif
                            @if($allowed->isNotEmpty())
                                <small dir="ltr">{{ $allowed->implode(', ') }}</small>
                            @else
                                <small>لا توجد اختيارات محددة لهذه الخدمة</small>
                            @endif
                        </span>
                        <input type="checkbox" name="child_ids[]" value="{{ $childId }}" class="js-catalog-child" @checked($isActive)>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="a2-card a2-mb-16">
            <div class="a2-flex-between">
                <div>
                    <h2 class="a2-section-title">4) اختيارات الخدمة داخل الـ Children المختارة</h2>
                    <div class="a2-section-subtitle">
                        هذه الاختيارات تأتي من Platform Service Item Types الخاصة بالخدمة الحالية فقط، ثم يتم تحديد المناسب لكل child.
                    </div>
                </div>
                <div class="a2-page-actions">
                    <button type="button" class="a2-btn a2-btn-ghost" id="checkItemTypes">تحديد الكل</button>
                    <button type="button" class="a2-btn a2-btn-ghost" id="uncheckItemTypes">إلغاء الكل</button>
                </div>
            </div>

            @if($itemTypesSafe->isEmpty())
                <div class="a2-alert a2-alert-warning a2-mt-12">
                    لا توجد اختيارات مفعلة لهذه الخدمة. أضفها أولًا من صفحة Service Item Types.
                </div>
            @else
                <div class="a2-check-grid a2-mt-16">
                    @foreach($itemTypesSafe as $type)
                        <label class="a2-check-card">
                            <span>
                                <strong>{{ $type->name_ar ?: ($type->name_en ?: $type->key) }}</strong>
                                <small dir="ltr">{{ $type->key }}</small>
                            </span>
                            <input type="checkbox" name="item_types[]" value="{{ $type->key }}" class="js-catalog-item-type">
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="a2-card a2-mb-16">
            <h2 class="a2-section-title">5) طريقة التطبيق</h2>
            <div class="a2-check-grid a2-mt-16">
                <label class="a2-check-card">
                    <span><strong>استبدال الاختيارات</strong><small>يجعل المختار هو القائمة النهائية للـ children المختارة.</small></span>
                    <input type="radio" name="mode" value="replace" checked>
                </label>
                <label class="a2-check-card">
                    <span><strong>إضافة فقط</strong><small>يضيف الاختيارات الجديدة فوق الموجود بدون حذف القديم.</small></span>
                    <input type="radio" name="mode" value="append">
                </label>
                <label class="a2-check-card">
                    <span><strong>حذف اختيارات</strong><small>يحذف الاختيارات المحددة فقط من children المختارة.</small></span>
                    <input type="radio" name="mode" value="remove">
                </label>
                <label class="a2-check-card">
                    <span><strong>تعطيل الخدمة للـ children</strong><small>يعطل الخدمة نفسها للـ children المختارة.</small></span>
                    <input type="radio" name="mode" value="disable_service">
                </label>
            </div>
        </div>

        <div class="a2-card a2-mb-16">
            <h2 class="a2-section-title">6) إعدادات سريعة</h2>
            <div class="a2-form-grid-3 a2-mt-12">
                <label class="a2-check-card"><span>يتطلب عنصر قابل للحجز</span><input type="checkbox" name="requires_bookable_item" value="1" checked></label>
                <label class="a2-check-card"><span>يدعم الكمية</span><input type="checkbox" name="supports_quantity" value="1" checked></label>
                <label class="a2-check-card"><span>يدعم عدد الضيوف/الأفراد</span><input type="checkbox" name="supports_guest_count" value="1"></label>
            </div>
            <div class="a2-form-group a2-mt-12">
                <label class="a2-label">ملاحظات داخلية</label>
                <input class="a2-input" name="notes" placeholder="مثال: فروع الفنادق ترى أنواع غرف فقط">
            </div>
        </div>

        <div class="a2-card">
            <div class="a2-flex-between">
                <div class="a2-actionsbar">
                    <span class="a2-pill a2-pill-gray">Root: {{ $activeRoot ? $nameOf($activeRoot) : '—' }}</span>
                    <span class="a2-pill a2-pill-gray">Service: {{ $activeService ? $nameOf($activeService) : '—' }}</span>
                </div>
                <button class="a2-btn a2-btn-primary">تطبيق Service Catalog Matrix</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function setAll(selector, checked) {
        document.querySelectorAll(selector).forEach(function (el) { el.checked = checked; });
    }
    document.getElementById('checkChildren')?.addEventListener('click', function () { setAll('.js-catalog-child', true); });
    document.getElementById('uncheckChildren')?.addEventListener('click', function () { setAll('.js-catalog-child', false); });
    document.getElementById('checkItemTypes')?.addEventListener('click', function () { setAll('.js-catalog-item-type', true); });
    document.getElementById('uncheckItemTypes')?.addEventListener('click', function () { setAll('.js-catalog-item-type', false); });
});
</script>
@endpush
