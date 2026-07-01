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
            <div class="a2-page-subtitle">
                المتجر يختار المنتج من الكتالوج ويضيف السعر والمخزون فقط. الباركود غير مطلوب.
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            <div class="a2-fw-900 a2-mb-8">يوجد أخطاء:</div>
            <ul style="margin:0;padding-inline-start:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="a2-stat-grid" style="margin-bottom:16px;">
        <div class="a2-stat-card">
            <div class="a2-stat-label">إجمالي الربط</div>
            <div class="a2-stat-value">{{ $stats['total'] ?? 0 }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">نشط</div>
            <div class="a2-stat-value">{{ $stats['active'] ?? 0 }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">متاح للبيع</div>
            <div class="a2-stat-value">{{ $stats['available'] ?? 0 }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">نفد المخزون</div>
            <div class="a2-stat-value">{{ $stats['out'] ?? 0 }}</div>
        </div>
    </div>

    <div class="a2-card a2-mb-16">
        <div class="a2-section-title">إضافة منتج لمتجر</div>
        <form method="POST" action="{{ route('admin.store-catalog-items.store') }}" class="a2-filterbar" style="align-items:flex-end;">
            @csrf

            <div>
                <label class="a2-label">المتجر</label>
                <select class="a2-select a2-filter-md" name="business_id" required>
                    <option value="">اختر المتجر</option>
                    @foreach(($businesses ?? []) as $business)
                        <option value="{{ $business->id }}">{{ $business->name ?: ('#'.$business->id) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="a2-label">المنتج</label>
                <select class="a2-select a2-filter-lg" name="catalog_product_id" required>
                    <option value="">اختر من أول 300 منتج</option>
                    @foreach(($catalogProducts ?? []) as $product)
                        <option value="{{ $product->id }}">
                            {{ $product->name_ar ?: $product->name_en }}
                            @if($product->package_label_ar) - {{ $product->package_label_ar }} @endif
                            ({{ $product->bim_code }})
                        </option>
                    @endforeach
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

            <label class="a2-check" style="margin-bottom:10px;">
                <input type="checkbox" name="is_available" value="1" checked>
                متاح
            </label>

            <input type="hidden" name="status" value="active">

            <button type="submit" class="a2-btn a2-btn-primary">ربط المنتج</button>
        </form>
    </div>

    <div class="a2-card">
        <form method="GET" action="{{ route('admin.store-catalog-items.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" name="q" value="{{ $qVal }}" placeholder="بحث باسم المنتج أو الكود أو المتجر">

            <select class="a2-select a2-filter-md" name="business_id">
                <option value="0">كل المتاجر</option>
                @foreach(($businesses ?? []) as $business)
                    <option value="{{ $business->id }}" @selected($businessIdVal === (int)$business->id)>{{ $business->name ?: ('#'.$business->id) }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-md" name="child_id">
                <option value="0">كل الأقسام</option>
                @foreach(($children ?? []) as $child)
                    <option value="{{ $child->id }}" @selected($childIdVal === (int)$child->id)>{{ $child->name_ar ?: $child->name_en }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="status">
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
    </div>

    <div class="a2-card" style="margin-top:16px;">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>المتجر</th>
                        <th>المنتج</th>
                        <th>القسم</th>
                        <th>البراند</th>
                        <th>السعر</th>
                        <th>المخزون</th>
                        <th>الحالة</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="a2-fw-900">{{ $row->id }}</td>
                        <td>
                            <div class="a2-fw-900">{{ $row->business_name ?: ('#'.$row->business_id) }}</div>
                            <div class="a2-muted a2-mt-8">ID: {{ $row->business_id }}</div>
                        </td>
                        <td>
                            <div class="a2-fw-900">{{ $row->product_name_ar ?: $row->product_name_en }}</div>
                            <div class="a2-muted a2-mt-8" dir="ltr">{{ $row->bim_code }}</div>
                            @if($row->package_label_ar || $row->package_label_en)
                                <div class="a2-muted a2-mt-8">{{ $row->package_label_ar ?: $row->package_label_en }}</div>
                            @endif
                        </td>
                        <td>{{ $row->child_name_ar ?: ($row->child_name_en ?: '—') }}</td>
                        <td>{{ $row->brand_name_ar ?: ($row->brand_name_en ?: '—') }}</td>
                        <td>
                            <div class="a2-fw-900">{{ number_format((float)$row->price, 2) }} {{ $row->currency_code ?: 'EGP' }}</div>
                            @if($row->offer_price !== null)
                                <div class="a2-pill a2-pill-success a2-mt-8">عرض: {{ number_format((float)$row->offer_price, 2) }}</div>
                            @endif
                        </td>
                        <td>
                            <div>{{ number_format((float)$row->stock_quantity, 3) }}</div>
                            <div class="a2-muted a2-mt-8">{{ $row->stock_status }}</div>
                        </td>
                        <td>
                            @if((int)$row->is_available === 1 && $row->status === 'active')
                                <span class="a2-pill a2-pill-success">متاح</span>
                            @else
                                <span class="a2-pill a2-pill-danger">غير متاح</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.store-catalog-items.destroy', $row->id) }}" onsubmit="return confirm('حذف المنتج من المتجر؟')">
                                @csrf
                                @method('DELETE')
                                <button class="a2-btn a2-btn-danger" type="submit">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="a2-empty">لا توجد منتجات مرتبطة بمتاجر حتى الآن.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="a2-pagination">
            {{ $rows->links() }}
        </div>
    </div>
</div>
@endsection
