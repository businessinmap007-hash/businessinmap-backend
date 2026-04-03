@extends('admin-v2.layouts.master')

@section('title','Create Category Child')
@section('body_class','admin-v2 admin-v2-category-children-create')

@section('content')
@php
    $parentIdInt = (int) ($parentId ?? 0);
    $rootName = $root?->name_ar ?: ($root?->name_en ?: null);
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">إضافة قسم فرعي عام</h1>
            <div class="a2-page-subtitle">
                @if($parentIdInt > 0 && $rootName)
                    سيتم ربطه مبدئيًا بالقسم الرئيسي: {{ $rootName }}
                @else
                    إنشاء قسم فرعي عام جديد داخل النظام
                @endif
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.category-children.index', $parentIdInt > 0 ? ['parent_id' => $parentIdInt] : []) }}"
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
          action="{{ route('admin.category-children.store') }}">
        @csrf

        <input type="hidden" name="parent_id" value="{{ $parentIdInt }}">

        @include('admin-v2.category-children._form')
    </form>
</div>
@endsection