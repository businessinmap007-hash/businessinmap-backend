@extends('admin-v2.layouts.master')

@section('title','Create Option Group')
@section('body_class','admin-v2 admin-v2-category-child-option-groups-create')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">إضافة مجموعة خيارات</h1>
            <div class="a2-page-subtitle">
                {{ $categoryChild->name_ar ?: ($categoryChild->name_en ?: ('#' . $categoryChild->id)) }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.category-child-option-groups.index', ['categoryChild' => $categoryChild->id, 'parent_id' => (int) ($parentId ?? 0)]) }}"
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

    <form method="POST"
          action="{{ route('admin.category-child-option-groups.store', ['categoryChild' => $categoryChild->id]) }}">
        @csrf
        @include('admin-v2.category-children.option-groups._form')
    </form>
</div>
@endsection