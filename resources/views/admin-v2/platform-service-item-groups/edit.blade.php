@extends('admin-v2.layouts.master')

@section('title', 'Edit Item Group')
@section('body_class', 'admin-v2 admin-v2-platform-service-item-groups-edit')

@section('content')
<div class="a2-page a2-page-narrow">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل فرع</h1>
            <div class="a2-page-subtitle">
                {{ $row->name_ar ?: ($row->name_en ?: $row->key) }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.platform-service-item-groups.index', ['service_id' => $row->platform_service_id]) }}" class="a2-btn a2-btn-ghost">رجوع</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.platform-service-item-groups.update', $row) }}">
        @csrf
        @method('PUT')

        @include('admin-v2.platform-service-item-groups._form', [
            'row' => $row,
            'services' => $services,
        ])
    </form>
</div>
@endsection
