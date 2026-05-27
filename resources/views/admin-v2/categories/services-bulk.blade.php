@extends('admin-v2.layouts.master')

@section('title', 'Bulk Services + Fees')
@section('body_class', 'admin-v2 admin-v2-services-bulk')

@section('content')
@php
    $rootsSafe = collect($roots ?? []);
    $servicesSafe = collect($services ?? []);
    $rootIdInt = (int) ($rootId ?? 0);

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
                        <label class="a2-check-card">
                            <input
                                type="checkbox"
                                name="platform_service_ids[]"
                                value="{{ $service->id }}"
                                class="js-service-checkbox"
                            >
                            <span>
                                {{ $nameOf($service) }}
                                <small class="a2-muted">({{ $service->key }})</small>
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

            <div class="a2-form-grid">
                <div>
                    <label class="a2-label">العملة</label>
                    <input class="a2-input" name="currency" value="EGP" maxlength="3">
                </div>

                <div>
                    <label class="a2-label">
                        <input type="checkbox" name="business_fee_enabled" value="1">
                        تفعيل رسوم البزنس
                    </label>
                    <input class="a2-input" type="number" step="0.01" min="0" name="business_fee_amount" placeholder="مثال: 5">
                </div>

                <div>
                    <label class="a2-label">
                        <input type="checkbox" name="client_fee_enabled" value="1">
                        تفعيل رسوم العميل
                    </label>
                    <input class="a2-input" type="number" step="0.01" min="0" name="client_fee_amount" placeholder="مثال: 3">
                </div>

                <div style="grid-column:1/-1;">
                    <label class="a2-label">ملاحظات الرسوم</label>
                    <textarea class="a2-input" name="fee_notes" rows="3" placeholder="اختياري"></textarea>
                </div>
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
document.addEventListener('DOMContentLoaded', function () {
    const rootTabs = document.querySelectorAll('.js-root-tab');
    const rootPanels = document.querySelectorAll('.js-root-panel');
    const rootInput = document.getElementById('bulk_root_id');

    function activateRoot(rootId) {
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
    }

    rootTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activateRoot(tab.dataset.rootId);
        });
    });

    function visibleChildren() {
        const activePanel = Array.from(rootPanels).find(function (panel) {
            return panel.style.display !== 'none';
        });

        if (!activePanel) {
            return [];
        }

        return activePanel.querySelectorAll('.js-child-checkbox:not(:disabled)');
    }

    document.getElementById('checkVisibleChildren')?.addEventListener('click', function () {
        visibleChildren().forEach(function (input) {
            input.checked = true;
        });
    });

    document.getElementById('uncheckVisibleChildren')?.addEventListener('click', function () {
        visibleChildren().forEach(function (input) {
            input.checked = false;
        });
    });

    const serviceInputs = document.querySelectorAll('.js-service-checkbox');

    document.getElementById('checkServices')?.addEventListener('click', function () {
        serviceInputs.forEach(function (input) {
            input.checked = true;
        });
    });

    document.getElementById('uncheckServices')?.addEventListener('click', function () {
        serviceInputs.forEach(function (input) {
            input.checked = false;
        });
    });
});
</script>
@endsection