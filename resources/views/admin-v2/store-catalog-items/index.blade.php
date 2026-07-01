@extends('admin-v2.layouts.master')

@section('title','Store Catalog Items')
@section('body_class','admin-v2 admin-v2-store-catalog-items-index')

@section('content')
@php
    $businessIdVal = (int)($businessId ?? 0);
    $childIdVal = (int)($childId ?? 0);
    $qVal = (string)($q ?? '');
    $statusVal = (string)($status ?? '');
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">منتجات المتاجر من الكتالوج</h1>
            <div class="a2-page-subtitle">بحث مباشر باسم المتجر أو المنتج، وربط المنتج بالسعر والمخزون فقط بدون باركود.</div>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            <div class="a2-fw-900 a2-mb-8">يوجد أخطاء:</div>
            <ul style="margin:0;padding-inline-start:18px;">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="a2-stat-grid" style="margin-bottom:16px;">
        <div class="a2-stat-card"><div class="a2-stat-label">إجمالي الربط</div><div class="a2-stat-value">{{ $stats['total'] ?? 0 }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">نشط</div><div class="a2-stat-value">{{ $stats['active'] ?? 0 }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">متاح للبيع</div><div class="a2-stat-value">{{ $stats['available'] ?? 0 }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">نفد المخزون</div><div class="a2-stat-value">{{ $stats['out'] ?? 0 }}</div></div>
    </div>

    <div class="a2-card a2-mb-16">
        <div class="a2-section-title">إضافة منتج لمتجر</div>
        <div class="a2-section-subtitle">اكتب اسم المتجر أو المنتج وسيتم البحث مباشرة داخل الداتا بدون تحميل كل المنتجات في الصفحة.</div>

        <form method="POST" action="{{ route('admin.store-catalog-items.store') }}" class="a2-filterbar" style="align-items:flex-end;gap:12px;">
            @csrf

            <div style="min-width:260px;">
                <label class="a2-label">المتجر</label>
                <select id="businessLookup" class="a2-select" name="business_id" required>
                    <option value="">اكتب اسم المتجر...</option>
                </select>
            </div>

            <div style="min-width:360px;flex:1;">
                <label class="a2-label">المنتج</label>
                <select id="productLookup" class="a2-select" name="catalog_product_id" required>
                    <option value="">اكتب اسم المنتج أو الكود...</option>
                </select>
            </div>

            <div>
                <label class="a2-label">السعر</label>
                <input class="a2-input a2-filter-sm" name="price" type="number" step="0.01" min="0" value="0" required>
            </div>

            <div>
                <label class="a2-label">سعر العرض</label>
                <input class="a2-input a2-filter-sm" name="offer_price" type="number" step="0.01" min="0">
            </div>

            <div>
                <label class="a2-label">المخزون</label>
                <input class="a2-input a2-filter-sm" name="stock_quantity" type="number" step="0.001" min="0" value="0">
            </div>

            <label class="a2-check" style="margin-bottom:10px;"><input type="checkbox" name="is_available" value="1" checked> متاح</label>
            <input type="hidden" name="status" value="active">
            <button type="submit" class="a2-btn a2-btn-primary">ربط المنتج</button>
        </form>
    </div>

    <div class="a2-card">
        <form id="storeCatalogLiveFilter" method="GET" action="{{ route('admin.store-catalog-items.index') }}" class="a2-filterbar">
            <input id="liveSearchInput" class="a2-input a2-filter-search" name="q" value="{{ $qVal }}" placeholder="اكتب للبحث المباشر: اسم المنتج / المتجر / البراند / الكود">

            <select id="liveBusinessFilter" class="a2-select a2-filter-md" name="business_id">
                <option value="0">كل المتاجر</option>
                @foreach(($businesses ?? []) as $business)
                    <option value="{{ $business->id }}" @selected($businessIdVal === (int)$business->id)>{{ $business->name ?: ('#'.$business->id) }}</option>
                @endforeach
            </select>

            <select id="liveChildFilter" class="a2-select a2-filter-md" name="child_id">
                <option value="0">كل الأقسام</option>
                @foreach(($children ?? []) as $child)
                    <option value="{{ $child->id }}" @selected($childIdVal === (int)$child->id)>{{ $child->name_ar ?: $child->name_en }}</option>
                @endforeach
            </select>

            <select id="liveStatusFilter" class="a2-select a2-filter-sm" name="status">
                <option value="" @selected($statusVal === '')>كل الحالات</option>
                <option value="active" @selected($statusVal === 'active')>Active</option>
                <option value="inactive" @selected($statusVal === 'inactive')>Inactive</option>
                <option value="archived" @selected($statusVal === 'archived')>Archived</option>
            </select>

            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
                <a href="{{ route('admin.store-catalog-items.index') }}" class="a2-btn a2-btn-ghost">تفريغ</a>
            </div>
        </form>
        <div id="liveSearchStatus" class="a2-muted" style="margin-top:10px;">النتائج يتم تحديثها أثناء الكتابة.</div>
    </div>

    <div class="a2-card" style="margin-top:16px;">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>ID</th><th>المتجر</th><th>المنتج</th><th>القسم</th><th>البراند</th><th>السعر</th><th>المخزون</th><th>الحالة</th><th>إجراء</th>
                    </tr>
                </thead>
                <tbody id="storeCatalogRows">
                    @include('admin-v2.store-catalog-items._rows', ['rows' => $rows])
                </tbody>
            </table>
        </div>
        <div id="serverPagination" class="a2-pagination">{{ $rows->links() }}</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const indexUrl = @json(route('admin.store-catalog-items.index'));
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const rowsBox = document.getElementById('storeCatalogRows');
    const statusBox = document.getElementById('liveSearchStatus');
    const pagination = document.getElementById('serverPagination');
    const qInput = document.getElementById('liveSearchInput');
    const businessFilter = document.getElementById('liveBusinessFilter');
    const childFilter = document.getElementById('liveChildFilter');
    const statusFilter = document.getElementById('liveStatusFilter');

    function debounce(fn, wait) {
        let t;
        return function () {
            clearTimeout(t);
            const args = arguments;
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    async function liveSearch() {
        const params = new URLSearchParams();
        params.set('q', qInput.value || '');
        params.set('business_id', businessFilter.value || '0');
        params.set('child_id', childFilter.value || '0');
        params.set('status', statusFilter.value || '');
        params.set('lookup', 'table');

        statusBox.textContent = 'جاري البحث...';
        try {
            const res = await fetch(indexUrl + '?' + params.toString(), {
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
            });
            const data = await res.json();
            rowsBox.innerHTML = data.html || '';
            statusBox.textContent = 'عدد النتائج الحالية: ' + (data.count || 0);
            if (pagination) pagination.style.display = 'none';
        } catch (e) {
            statusBox.textContent = 'حدث خطأ أثناء البحث.';
        }
    }

    const runLiveSearch = debounce(liveSearch, 350);
    qInput?.addEventListener('input', runLiveSearch);
    businessFilter?.addEventListener('change', liveSearch);
    childFilter?.addEventListener('change', liveSearch);
    statusFilter?.addEventListener('change', liveSearch);

    function initRemoteSelect(selector, lookup, placeholder) {
        const el = document.querySelector(selector);
        if (!el || typeof TomSelect === 'undefined') return;
        new TomSelect(el, {
            valueField: 'id',
            labelField: 'text',
            searchField: 'text',
            maxOptions: 30,
            create: false,
            placeholder: placeholder,
            loadThrottle: 350,
            render: {
                option: function(item, escape) {
                    return '<div><div style="font-weight:800">' + escape(item.name || item.text) + '</div>' +
                        '<div class="a2-muted" style="font-size:12px">' + escape([item.brand, item.child, item.size, item.code].filter(Boolean).join(' · ')) + '</div></div>';
                },
                item: function(item, escape) {
                    return '<div>' + escape(item.text) + '</div>';
                }
            },
            load: function(query, callback) {
                const params = new URLSearchParams();
                params.set('lookup', lookup);
                params.set('q', query || '');
                if (lookup === 'products') params.set('child_id', childFilter?.value || '0');
                fetch(indexUrl + '?' + params.toString(), {
                    headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
                })
                .then(r => r.json())
                .then(json => callback(json.results || []))
                .catch(() => callback());
            }
        });
    }

    initRemoteSelect('#businessLookup', 'businesses', 'اكتب اسم المتجر...');
    initRemoteSelect('#productLookup', 'products', 'اكتب اسم المنتج أو الكود...');
})();
</script>
@endpush
