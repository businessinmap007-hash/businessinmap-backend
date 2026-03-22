@extends('admin-v2.layouts.master')

@section('title', 'إضافة قسم فرعي')
@section('body_class', 'admin-v2 admin-v2-category-children-create')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">إضافة قسم فرعي جديد</h1>
            <div class="a2-page-subtitle">
                إنشاء قسم فرعي موحّد وربطه بقسم رئيسي أو أكثر
            </div>
        </div>

        <div class="a2-page-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('admin.category-children.index', !empty($parentId) ? ['parent_id' => $parentId] : []) }}"
               class="a2-btn a2-btn-ghost">
                رجوع
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.category-children.store') }}">
        @csrf

        @include('admin-v2.category-children._form', [
            'mode' => 'create',
            'submitLabel' => 'حفظ القسم الفرعي',
        ])
    </form>
</div>
@endsection