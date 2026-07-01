@extends('admin-v2.layouts.master')

@section('title','Catalog Products Manager')
@section('body_class','admin-v2 admin-v2-catalog-products-index')

@section('content')
@php
    $qVal = (string)($q ?? '');
    $childIdVal = (int)($childId ?? 0);
    $brandIdVal = (int)($brandId ?? 0);
    $statusVal = (string)($status ?? '');
    $duplicateStatusVal = (string)($duplicateStatus ?? '');
    $perPageVal = (int)($perPage ?? 100);
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">Catalog Products Manager</h1>
            <div class="a2-page-subtitle">الحقول تظهر كنص عادي. اضغط على أي قيمة قابلة للتعديل، عدّلها، وسيتم الحفظ تلقائيًا عند الخروج من الحقل.</div>
        </div>
    </div>

    <div class="a2-stat-grid" style="margin-bottom:16px;">
        <div class="a2-stat-card"><div class="a2-stat-label">إجمالي المنتجات</div><div class="a2-stat-value">{{ number_format($stats['total'] ?? 0) }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Unique</div><div class="a2-stat-value">{{ ($stats['unique'] ?? null) === null ? '—' : number_format($stats['unique']) }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Duplicate</div><div class="a2-stat-value">{{ ($stats['duplicate'] ?? null) === null ? '—' : number_format($stats['duplicate']) }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Review</div><div class="a2-stat-value">{{ ($stats['review'] ?? null) === null ? '—' : number_format($stats['review']) }}</div></div>
    </div>

    <div class="a2-card">
        <form method="GET" action="{{ route('admin.catalog-products.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" name="q" value="{{ $qVal }}" placeholder="بحث باسم المنتج / الكود / البراند / الموديل">

            <select class="a2-select a2-filter-md" name="child_id">
                <option value="0">كل الأقسام الفرعية</option>
                @foreach(($children ?? []) as $child)
                    <option value="{{ $child->id }}" @selected($childIdVal === (int)$child->id)>{{ $child->name_ar ?: $child->name_en }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-md" name="brand_id">
                <option value="0">كل البراندات</option>
                @foreach(($brands ?? []) as $brand)
                    <option value="{{ $brand->id }}" @selected($brandIdVal === (int)$brand->id)>{{ $brand->name_ar ?: $brand->name_en }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="duplicate_status">
                <option value="" @selected($duplicateStatusVal === '')>كل التكرار</option>
                <option value="unique" @selected($duplicateStatusVal === 'unique')>Unique</option>
                <option value="review" @selected($duplicateStatusVal === 'review')>Review</option>
                <option value="duplicate" @selected($duplicateStatusVal === 'duplicate')>Duplicate</option>
                <option value="master" @selected($duplicateStatusVal === 'master')>Master</option>
            </select>

            <select class="a2-select a2-filter-sm" name="status">
                <option value="" @selected($statusVal === '')>كل الحالات</option>
                <option value="active" @selected($statusVal === 'active')>Active</option>
                <option value="inactive" @selected($statusVal === 'inactive')>Inactive</option>
                <option value="approved" @selected($statusVal === 'approved')>Approved</option>
                <option value="pending" @selected($statusVal === 'pending')>Pending</option>
                <option value="draft" @selected($statusVal === 'draft')>Draft</option>
                <option value="rejected" @selected($statusVal === 'rejected')>Rejected</option>
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach([50, 100, 200, 500] as $n)
                    <option value="{{ $n }}" @selected($perPageVal === $n)>{{ $n }} / صفحة</option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
                <a href="{{ route('admin.catalog-products.index') }}" class="a2-btn a2-btn-ghost">تفريغ</a>
            </div>
        </form>
    </div>

    <div class="a2-card" style="margin-top:16px;">
        <form method="GET" action="{{ route('admin.catalog-products.index') }}" onsubmit="return window.catalogProductsManagerConfirm ? window.catalogProductsManagerConfirm(this) : confirm('تأكيد تنفيذ العملية؟');">
            <input type="hidden" name="q" value="{{ $qVal }}">
            <input type="hidden" name="child_id" value="{{ $childIdVal }}">
            <input type="hidden" name="brand_id" value="{{ $brandIdVal }}">
            <input type="hidden" name="status" value="{{ $statusVal }}">
            <input type="hidden" name="duplicate_status" value="{{ $duplicateStatusVal }}">
            <input type="hidden" name="per_page" value="{{ $perPageVal }}">
            <input type="hidden" name="confirm_action" value="yes">

            <div class="a2-filterbar" style="margin-bottom:12px;">
                <select class="a2-select a2-filter-md" name="manager_action" required>
                    <option value="">اختر إجراء للمنتجات المحددة</option>
                    <option value="duplicate">Mark as Duplicate / إخفاء كمكرر</option>
                    <option value="unique">Keep as Unique / إبقاء كمنتج صحيح</option>
                    <option value="review">Send to Review / يحتاج مراجعة</option>
                    <option value="inactive">Deactivate / تعطيل</option>
                    <option value="active">Activate / تفعيل</option>
                    <option value="delete_forever">Delete Forever / حذف نهائي</option>
                </select>
                <button class="a2-btn a2-btn-primary" type="submit">تنفيذ على المحدد</button>
                <span class="a2-muted">التعديل يتم بالضغط على النص نفسه. الحذف النهائي من هنا للصفوف المحددة فقط.</span>
            </div>

            <div class="a2-alert a2-alert-danger" style="margin-bottom:12px;">
                حذف نهائي يعني حذف المنتج وروابطه المعروفة من قاعدة البيانات. لا يمكن التراجع إلا من نسخة احتياطية.
            </div>

            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" onclick="document.querySelectorAll('.js-product-check').forEach(cb => cb.checked = this.checked)"></th>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Brand</th>
                            <th>Size / Model</th>
                            <th>Duplicate</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><input class="js-product-check" type="checkbox" name="ids[]" value="{{ $row->id }}"></td>
                            <td class="a2-fw-900">{{ $row->id }}</td>
                            <td dir="ltr">{{ $row->bim_code }}</td>
                            <td style="min-width:310px;">
                                <div class="a2-fw-900 js-editable" data-id="{{ $row->id }}" data-field="name_ar" data-type="text">{{ $row->name_ar ?: '—' }}</div>
                                <div class="a2-muted a2-mt-8 js-editable" dir="ltr" data-id="{{ $row->id }}" data-field="name_en" data-type="text">{{ $row->name_en ?: '—' }}</div>
                            </td>
                            <td>
                                <div>{{ $row->category_name_ar ?: '—' }}</div>
                                <div class="a2-muted a2-mt-8">{{ $row->child_name_ar ?: '—' }}</div>
                            </td>
                            <td>{{ $row->brand_name_ar ?: '—' }}</td>
                            <td style="min-width:220px;">
                                <div>
                                    <span class="a2-muted">Value:</span>
                                    <span class="js-editable" data-id="{{ $row->id }}" data-field="package_value" data-type="number">{{ $row->package_value ?: '—' }}</span>
                                    <span dir="ltr">{{ $row->unit_code }}</span>
                                </div>
                                <div class="a2-muted a2-mt-8">
                                    AR: <span class="js-editable" data-id="{{ $row->id }}" data-field="package_label_ar" data-type="text">{{ $row->package_label_ar ?: '—' }}</span>
                                </div>
                                <div class="a2-muted a2-mt-8" dir="ltr">
                                    EN: <span class="js-editable" data-id="{{ $row->id }}" data-field="package_label_en" data-type="text">{{ $row->package_label_en ?: '—' }}</span>
                                </div>
                                <div class="a2-muted a2-mt-8" dir="ltr">
                                    Model: <span class="js-editable" data-id="{{ $row->id }}" data-field="model" data-type="text">{{ $row->model ?: '—' }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="a2-pill a2-pill-gray js-editable" data-id="{{ $row->id }}" data-field="duplicate_status" data-type="select" data-options="unique,master,duplicate,review">{{ $row->duplicate_status ?? 'unique' }}</span>
                                @if($row->duplicate_master_id)<div class="a2-muted a2-mt-8">Master: {{ $row->duplicate_master_id }}</div>@endif
                            </td>
                            <td>
                                <div>
                                    <span class="a2-pill {{ (int)$row->is_active === 1 ? 'a2-pill-success' : 'a2-pill-danger' }} js-editable" data-id="{{ $row->id }}" data-field="is_active" data-type="select" data-options="1:Active,0:Inactive">{{ (int)$row->is_active === 1 ? 'Active' : 'Inactive' }}</span>
                                </div>
                                <div class="a2-muted a2-mt-8 js-editable" data-id="{{ $row->id }}" data-field="approval_status" data-type="select" data-options="draft,pending,approved,rejected">{{ $row->approval_status }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="a2-empty">لا توجد منتجات.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        <div class="a2-pagination">{{ $rows->links() }}</div>
    </div>
</div>

<style>
    .js-editable{cursor:pointer;border-bottom:1px dashed transparent;display:inline-block;min-width:32px;padding:2px 3px;border-radius:6px;}
    .js-editable:hover{border-bottom-color:currentColor;background:rgba(0,0,0,.04);}
    .a2-inline-edit{min-width:140px;max-width:280px;}
    .a2-save-ok{outline:2px solid rgba(22,163,74,.35);}
    .a2-save-error{outline:2px solid rgba(220,38,38,.35);}
</style>

<script>
(function(){
    const autosaveBaseUrl = @json(route('admin.catalog-products.index'));

    function flash(el, cls) {
        el.classList.add(cls);
        setTimeout(function(){ el.classList.remove(cls); }, 900);
    }

    function displayValue(value) {
        if (value === null || value === undefined || String(value).trim() === '') return '—';
        return String(value);
    }

    function saveField(wrapper, id, field, value) {
        const url = new URL(autosaveBaseUrl, window.location.origin);
        url.searchParams.set('manager_action', 'inline_update');
        url.searchParams.set('product_id', id);
        url.searchParams.set('field', field);
        url.searchParams.set('value', value);

        wrapper.textContent = 'Saving...';
        return fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
            .then(function(response){
                if (!response.ok) throw new Error('save failed');
                return response.json();
            })
            .then(function(data){
                wrapper.textContent = displayValue(data.value ?? value);
                flash(wrapper, 'a2-save-ok');
            })
            .catch(function(){
                wrapper.textContent = displayValue(wrapper.dataset.originalValue || value);
                flash(wrapper, 'a2-save-error');
                alert('لم يتم حفظ التعديل.');
            });
    }

    function makeInput(el) {
        const id = el.dataset.id;
        const field = el.dataset.field;
        const type = el.dataset.type || 'text';
        const originalText = el.textContent.trim();
        const originalValue = originalText === '—' ? '' : originalText;
        el.dataset.originalValue = originalValue;

        let input;
        if (type === 'select') {
            input = document.createElement('select');
            input.className = 'a2-select a2-inline-edit';
            const options = (el.dataset.options || '').split(',');
            options.forEach(function(optionRaw){
                const parts = optionRaw.split(':');
                const value = parts[0];
                const label = parts[1] || parts[0];
                const opt = document.createElement('option');
                opt.value = value;
                opt.textContent = label;
                if (label === originalText || value === originalText || (field === 'is_active' && ((originalText === 'Active' && value === '1') || (originalText === 'Inactive' && value === '0')))) {
                    opt.selected = true;
                }
                input.appendChild(opt);
            });
        } else {
            input = document.createElement('input');
            input.type = type === 'number' ? 'number' : 'text';
            input.step = 'any';
            input.className = 'a2-input a2-inline-edit';
            input.value = originalValue;
        }

        el.replaceChildren(input);
        input.focus();
        if (input.select) input.select();

        let saved = false;
        const finish = function(){
            if (saved) return;
            saved = true;
            const newValue = input.value;
            if (String(newValue) === String(originalValue)) {
                el.textContent = displayValue(originalValue);
                return;
            }
            saveField(el, id, field, newValue);
        };

        input.addEventListener('blur', finish);
        input.addEventListener('keydown', function(e){
            if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
            if (e.key === 'Escape') { saved = true; el.textContent = displayValue(originalValue); }
        });
    }

    document.addEventListener('click', function(e){
        const el = e.target.closest('.js-editable');
        if (!el || el.querySelector('input,select')) return;
        makeInput(el);
    });

    window.catalogProductsManagerConfirm = function (form) {
        const action = form.querySelector('[name="manager_action"]').value;
        const selected = form.querySelectorAll('.js-product-check:checked').length;

        if (! action) { alert('اختر الإجراء أولًا.'); return false; }
        if (selected < 1) { alert('اختر صنف واحد على الأقل.'); return false; }
        if (action === 'delete_forever') {
            return confirm('تحذير: سيتم حذف ' + selected + ' صنف نهائيًا مع روابطه. لا يمكن التراجع إلا من backup. هل أنت متأكد؟');
        }
        return confirm('تأكيد تنفيذ العملية على ' + selected + ' صنف؟');
    };
})();
</script>
@endsection
