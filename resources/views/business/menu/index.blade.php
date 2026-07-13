@extends('business.layouts.master')

@section('title', 'المنيو')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">منيو نشاطي</h1>
        <div class="a2-page-subtitle">الأصناف التي يمكن للعميل طلبها — تخصّك أنت فقط.</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.menu-settings.edit') }}" class="a2-btn a2-btn-ghost">إعدادات</a>
        <a href="{{ route('business.menu-sections.index') }}" class="a2-btn a2-btn-ghost">الأقسام</a>
        <a href="{{ route('business.menu.create') }}" class="a2-btn a2-btn-primary">إضافة صنف</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<div class="a2-card a2-card--soft a2-mb-16">
    <form method="GET" action="{{ route('business.menu.index') }}" class="a2-filterbar">
        <div class="a2-filter-search">
            <label class="a2-label">بحث</label>
            <input class="a2-input" name="q" value="{{ $q }}" placeholder="اسم الصنف">
        </div>
        <div class="a2-filter-sm">
            <label class="a2-label">الحالة</label>
            <select class="a2-select" name="active">
                <option value="">الكل</option>
                <option value="1" @selected($active === '1')>متاح</option>
                <option value="0" @selected($active === '0')>غير متاح</option>
            </select>
        </div>
        <div class="a2-filter-actions">
            <button class="a2-btn a2-btn-primary" type="submit">تصفية</button>
            <a href="{{ route('business.menu.index') }}" class="a2-btn a2-btn-ghost">إعادة</a>
        </div>
    </form>
</div>

<div class="a2-card">
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الاسم</th>
                    <th>القسم</th>
                    <th>السعر</th>
                    <th>الترتيب</th>
                    <th>الحالة</th>
                    <th class="a2-text-right">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>
                            <div class="a2-fw-900">{{ $row->name_ar ?: '—' }}</div>
                            <div class="a2-muted" dir="ltr">{{ $row->name_en ?: '' }}</div>
                        </td>
                        <td>
                            @if($row->section)
                                <span class="a2-pill a2-pill-gray">{{ $row->section->name_ar }}</span>
                            @else
                                <span class="a2-muted">—</span>
                            @endif
                        </td>
                        <td class="a2-fw-900">{{ number_format((float) $row->base_price, 2) }}</td>
                        <td>{{ (int) $row->sort_order }}</td>
                        <td>
                            @if($row->is_active)
                                <span class="a2-pill a2-pill-success">متاح</span>
                            @else
                                <span class="a2-pill a2-pill-gray">غير متاح</span>
                            @endif
                        </td>
                        <td class="a2-text-right">
                            <div class="a2-inline-actions">
                                <a href="{{ route('business.menu.edit', $row->id) }}" class="a2-btn a2-btn-sm a2-btn-ghost">تعديل</a>
                                <form method="POST" action="{{ route('business.menu.destroy', $row->id) }}" onsubmit="return confirm('حذف هذا الصنف؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">حذف</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="a2-empty">لا توجد أصناف بعد. ابدأ بإضافة منيو نشاطك.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($rows, 'links'))
        <div class="a2-pagination">{{ $rows->links() }}</div>
    @endif
</div>
@endsection
