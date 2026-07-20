@extends('admin-v2.layouts.master')

@section('title', 'Create Item Group')
@section('body_class', 'admin-v2 admin-v2-platform-service-item-groups-create')

@section('content')
<div class="a2-page a2-page-narrow">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('إضافة فرع') }}</h1>
            <div class="a2-page-subtitle">{{ __('فرع جديد لتقسيم أنواع العناصر داخل خدمة.') }}</div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.platform-service-item-groups.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.platform-service-item-groups.store') }}">
        @csrf

        @include('admin-v2.platform-service-item-groups._form', [
            'row' => $row,
            'services' => $services,
        ])
    </form>
</div>
@endsection
