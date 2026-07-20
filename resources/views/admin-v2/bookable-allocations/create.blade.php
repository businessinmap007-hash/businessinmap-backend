@extends('admin-v2.layouts.master')

@section('title', 'Create Bookable Allocation')
@section('topbar_title', 'Create Bookable Allocation')
@section('body_class', 'admin-v2-bookable-allocations')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('إنشاء حصة وحدة') }}</h1>
            <div class="a2-page-subtitle">{{ __('تحديد حصة لشريك من غرفة أو وحدة قابلة للحجز مع توليد عرض قابل للمقارنة.') }}</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.bookable-allocations.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.bookable-allocations.store') }}">
        @csrf
        @include('admin-v2.bookable-allocations._form', [
            'allocation' => $allocation,
            'partnerships' => $partnerships,
            'bookables' => $bookables,
            'services' => $services,
        ])
    </form>
</div>
@endsection
