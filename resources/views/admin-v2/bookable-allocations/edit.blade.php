@extends('admin-v2.layouts.master')

@section('title', 'Edit Bookable Allocation')
@section('topbar_title', 'Edit Bookable Allocation')
@section('body_class', 'admin-v2-bookable-allocations')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('تعديل حصة #') }}{{ $allocation->id }}</h1>
            <div class="a2-page-subtitle">{{ __('تعديل الكمية والسعر وقواعد العرض التجاري المرتبط بالحصة.') }}</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.bookable-allocations.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.bookable-allocations.update', $allocation->id) }}">
        @csrf
        @method('PUT')
        @include('admin-v2.bookable-allocations._form', [
            'allocation' => $allocation,
            'partnerships' => $partnerships,
            'bookables' => $bookables,
            'services' => $services,
        ])
    </form>
</div>
@endsection
