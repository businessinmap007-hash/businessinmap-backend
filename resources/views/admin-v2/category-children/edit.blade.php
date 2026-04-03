@extends('admin-v2.layouts.master')

@section('title','Edit Category Child')
@section('body_class','admin-v2 admin-v2-category-children-edit')

@section('content')
@php
    $parentIdInt = (int) ($parentId ?? 0);
    $rowSafe = $row ?? $categoryChild ?? null;
    $childName = $rowSafe?->name_ar ?: ($rowSafe?->name_en ?: ('#' . ($rowSafe->id ?? 0)));
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل القسم الفرعي</h1>
            <div class="a2-page-subtitle">
                {{ $childName }}
                @if(!empty($rowSafe?->id))
                    <span class="a2-muted">#{{ $rowSafe->id }}</span>
                @endif
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.category-child-options.edit', ['categoryChild' => $rowSafe->id, 'parent_id' => $parentIdInt]) }}"
               class="a2-btn a2-btn-primary">
                إدارة خيارات القسم
            </a>

            <a href="{{ route('admin.category-children.index', $parentIdInt > 0 ? ['parent_id' => $parentIdInt] : []) }}"
               class="a2-btn a2-btn-ghost">
                رجوع
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card">
            <div class="a2-stat-label">رقم القسم الفرعي</div>
            <div class="a2-stat-value">#{{ $rowSafe->id }}</div>
            <div class="a2-stat-note">المعرف الداخلي</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">إدارة الخيارات</div>
            <div class="a2-stat-value">Options</div>
            <div class="a2-stat-note">من الزر العلوي أو بعد الحفظ</div>
        </div>
    </div>

    <form method="POST"
          action="{{ route('admin.category-children.update', ['categoryChild' => $rowSafe->id]) }}">
        @csrf
        @method('PUT')

        <input type="hidden" name="parent_id" value="{{ $parentIdInt }}">

        @include('admin-v2.category-children._form')
    </form>

    <div class="a2-card a2-card--soft a2-mt-16">
        <div class="a2-section-title">إجراءات إضافية</div>
        <div class="a2-section-subtitle">استخدم الحذف فقط إذا كنت تريد إزالة القسم الفرعي من النظام نهائيًا</div>

        <div class="a2-page-actions a2-mt-12" style="justify-content:flex-start;">
            <form method="POST"
                  action="{{ route('admin.category-children.destroy', ['categoryChild' => $rowSafe->id]) }}"
                  onsubmit="return confirm('تأكيد حذف القسم الفرعي نهائيًا؟');">
                @csrf
                @method('DELETE')
                <input type="hidden" name="parent_id" value="{{ $parentIdInt }}">

                <button type="submit" class="a2-btn a2-btn-danger">
                    حذف القسم الفرعي
                </button>
            </form>
        </div>
    </div>
</div>
@endsection