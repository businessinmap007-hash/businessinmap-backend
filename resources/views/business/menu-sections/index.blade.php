@extends('business.layouts.master')

@section('title', 'أقسام المنيو')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">أقسام المنيو</h1>
        <div class="a2-page-subtitle">نظّم منيو نشاطك (مقبلات، أطباق رئيسية، حلويات، مشروبات…).</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.menu.index') }}" class="a2-btn a2-btn-ghost">المنيو</a>
        <a href="{{ route('business.menu-sections.create') }}" class="a2-btn a2-btn-primary">إضافة قسم</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<div class="a2-card">
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الاسم</th>
                    <th>عدد الأصناف</th>
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
                            <div class="a2-fw-900">{{ $row->name_ar }}</div>
                            <div class="a2-muted" dir="ltr">{{ $row->name_en ?: '' }}</div>
                        </td>
                        <td>{{ (int) $row->items_count }}</td>
                        <td>{{ (int) $row->sort_order }}</td>
                        <td>
                            @if($row->is_active)
                                <span class="a2-pill a2-pill-success">نشط</span>
                            @else
                                <span class="a2-pill a2-pill-gray">غير نشط</span>
                            @endif
                        </td>
                        <td class="a2-text-right">
                            <div class="a2-inline-actions">
                                <a href="{{ route('business.menu-sections.edit', $row->id) }}" class="a2-btn a2-btn-sm a2-btn-ghost">تعديل</a>
                                <form method="POST" action="{{ route('business.menu-sections.destroy', $row->id) }}" onsubmit="return confirm('حذف هذا القسم؟ ستصبح أصنافه بلا قسم.');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">حذف</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="a2-empty">لا أقسام بعد. أضف قسماً لتنظيم منيوك.</td>
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
