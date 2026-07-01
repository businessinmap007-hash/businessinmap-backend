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
            <div class="a2-page-subtitle">مراجعة وتعديل المنتجات يدويًا، تحديد المكرر، أو حذف المنتجات نهائيًا من نفس الشاشة.</div>
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
                    <option value="update_selected">Save Inline Edits / حفظ تعديلات المحدد</option>
                    <option value="duplicate">Mark as Duplicate / إخفاء كمكرر</option>
                    <option value="unique">Keep as Unique / إبقاء كمنتج صحيح</option>
                    <option value="review">Send to Review / يحتاج مراجعة</option>
                    <option value="inactive">Deactivate / تعطيل</option>
                    <option value="active">Activate / تفعيل</option>
                    <option value="delete_forever">Delete Forever / حذف نهائي</option>
                </select>
                <button class="a2-btn a2-btn-primary" type="submit">تنفيذ على المحدد</button>
                <span class="a2-muted">للتعديل: اختر الصنف، عدل الحقول داخل الصف، ثم اختر Save Inline Edits.</span>
            </div>

            <div class="a2-alert a2-alert-danger" style="margin-bottom:12px;">
                حذف نهائي يعني حذف المنتج من جدول الكتالوج وحذف روابطه المعروفة مثل صور المنتج، الباركود، خصائص المنتج، وربطه بالمتاجر. لا يمكن التراجع عنه إلا من نسخة احتياطية.
            </div>

            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" onclick="document.querySelectorAll('.js-product-check').forEach(cb => { cb.checked = this.checked; cb.dispatchEvent(new Event('change')); })"></th>
                            <th>ID</th>
                            <th>Product Inline Edit</th>
                            <th>Size / Model</th>
                            <th>Category</th>
                            <th>Duplicate</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><input class="js-product-check" type="checkbox" name="ids[]" value="{{ $row->id }}"></td>
                            <td class="a2-fw-900">{{ $row->id }}</td>
                            <td style="min-width:360px;">
                                <label class="a2-muted">Arabic</label>
                                <input class="a2-input js-row-field" data-row-id="{{ $row->id }}" disabled name="products[{{ $row->id }}][name_ar]" value="{{ $row->name_ar }}" style="margin-bottom:6px;">
                                <label class="a2-muted">English</label>
                                <input class="a2-input js-row-field" data-row-id="{{ $row->id }}" disabled name="products[{{ $row->id }}][name_en]" value="{{ $row->name_en }}" dir="ltr" style="margin-bottom:6px;">
                                @if($row->bim_code)<div class="a2-muted" dir="ltr">Code: {{ $row->bim_code }}</div>@endif
                            </td>
                            <td style="min-width:260px;">
                                <label class="a2-muted">Package Value</label>
                                <input class="a2-input js-row-field" data-row-id="{{ $row->id }}" disabled name="products[{{ $row->id }}][package_value]" value="{{ $row->package_value }}" dir="ltr" style="margin-bottom:6px;">
                                <label class="a2-muted">Package AR</label>
                                <input class="a2-input js-row-field" data-row-id="{{ $row->id }}" disabled name="products[{{ $row->id }}][package_label_ar]" value="{{ $row->package_label_ar }}" style="margin-bottom:6px;">
                                <label class="a2-muted">Package EN</label>
                                <input class="a2-input js-row-field" data-row-id="{{ $row->id }}" disabled name="products[{{ $row->id }}][package_label_en]" value="{{ $row->package_label_en }}" dir="ltr" style="margin-bottom:6px;">
                                <label class="a2-muted">Model</label>
                                <input class="a2-input js-row-field" data-row-id="{{ $row->id }}" disabled name="products[{{ $row->id }}][model]" value="{{ $row->model }}" dir="ltr">
                            </td>
                            <td>
                                <div>{{ $row->category_name_ar ?: '—' }}</div>
                                <div class="a2-muted a2-mt-8">{{ $row->child_name_ar ?: '—' }}</div>
                                <div class="a2-muted a2-mt-8">Brand: {{ $row->brand_name_ar ?: '—' }}</div>
                            </td>
                            <td style="min-width:160px;">
                                <select class="a2-select js-row-field" data-row-id="{{ $row->id }}" disabled name="products[{{ $row->id }}][duplicate_status]">
                                    @foreach(['unique','master','duplicate','review'] as $ds)
                                        <option value="{{ $ds }}" @selected(($row->duplicate_status ?? 'unique') === $ds)>{{ $ds }}</option>
                                    @endforeach
                                </select>
                                @if($row->duplicate_master_id)<div class="a2-muted a2-mt-8">Master: {{ $row->duplicate_master_id }}</div>@endif
                            </td>
                            <td style="min-width:170px;">
                                <select class="a2-select js-row-field" data-row-id="{{ $row->id }}" disabled name="products[{{ $row->id }}][is_active]" style="margin-bottom:6px;">
                                    <option value="1" @selected((int)$row->is_active === 1)>Active</option>
                                    <option value="0" @selected((int)$row->is_active === 0)>Inactive</option>
                                </select>
                                <select class="a2-select js-row-field" data-row-id="{{ $row->id }}" disabled name="products[{{ $row->id }}][approval_status]">
                                    @foreach(['draft','pending','approved','rejected'] as $as)
                                        <option value="{{ $as }}" @selected(($row->approval_status ?? '') === $as)>{{ $as }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="a2-empty">لا توجد منتجات.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        <div class="a2-pagination">{{ $rows->links() }}</div>
    </div>
</div>

<script>
document.querySelectorAll('.js-product-check').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
        var id = this.value;
        document.querySelectorAll('.js-row-field[data-row-id="' + id + '"]').forEach(function (field) {
            field.disabled = !checkbox.checked;
        });
    });
});

window.catalogProductsManagerConfirm = function (form) {
    var action = form.querySelector('[name="manager_action"]').value;
    var selected = form.querySelectorAll('.js-product-check:checked').length;

    if (! action) {
        alert('اختر الإجراء أولًا.');
        return false;
    }

    if (selected < 1) {
        alert('اختر صنف واحد على الأقل.');
        return false;
    }

    if (action === 'delete_forever') {
        return confirm('تحذير: سيتم حذف ' + selected + ' صنف نهائيًا مع روابطه. لا يمكن التراجع إلا من backup. هل أنت متأكد؟');
    }

    if (action === 'update_selected') {
        return confirm('سيتم حفظ تعديلات الحقول للصنف/الأصناف المحددة فقط. هل تريد المتابعة؟');
    }

    return confirm('تأكيد تنفيذ العملية على ' + selected + ' صنف؟');
};
</script>
@endsection
