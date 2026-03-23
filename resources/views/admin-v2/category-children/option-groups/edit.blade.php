@extends('admin-v2.layouts.master')

@section('title','Edit Option Group')
@section('body_class','admin-v2 admin-v2-category-child-option-groups-edit')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل مجموعة الخيارات</h1>
            <div class="a2-page-subtitle">
                {{ $group->name_ar ?: ($group->name_en ?: ('#' . $group->id)) }}
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
          action="{{ route('admin.category-child-option-groups.update', ['categoryChild' => $categoryChild->id, 'group' => $group->id]) }}">
        @csrf
        @method('PUT')
        @include('admin-v2.category-children.option-groups._form')
    </form>
</div>
@endsection