@extends('admin-v2.layouts.master')

@section('title', 'Edit Item Type')
@section('body_class', 'admin-v2 admin-v2-platform-service-item-types-edit')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل نوع العنصر #{{ $row->id }}</h1>
            <div class="a2-page-subtitle">
                تعديل الاسم، المفتاح، التفعيل، الافتراضي، والترتيب.
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.platform-service-item-types.index', ['service_id' => $row->platform_service_id]) }}" class="a2-btn a2-btn-ghost">
                رجوع
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.platform-service-item-types.update', $row) }}">
        @csrf
        @method('PUT')

        @include('admin-v2.platform-service-item-types._form', [
            'row' => $row,
            'services' => $services,
        ])
    </form>
</div>
@endsection