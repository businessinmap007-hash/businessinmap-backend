@extends('admin-v2.layouts.master')

@section('title', 'Create Item Type')
@section('body_class', 'admin-v2 admin-v2-platform-service-item-types-create')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('إضافة نوع عنصر') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('إضافة نوع عنصر جديد داخل خدمة منصة.') }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.platform-service-item-types.index') }}" class="a2-btn a2-btn-ghost">
                {{ __('رجوع') }}
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.platform-service-item-types.store') }}">
        @csrf

        @include('admin-v2.platform-service-item-types._form', [
            'row' => $row,
            'services' => $services,
        ])
    </form>
</div>
@endsection