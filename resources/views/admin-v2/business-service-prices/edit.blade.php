@extends('admin-v2.layouts.master')

@section('title','Edit Business Service Price')
@section('body_class','admin-v2-business-service-prices-edit')

@section('content')
@php
    $backUrl = route('admin.business_service_prices.index');
@endphp

<div class="a2-page a2-page-narrow">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل سعر خدمة</h1>
            <div class="a2-page-subtitle">تعديل السعر والديبوزت والخصم حسب نوع العنصر</div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ $backUrl }}" class="a2-btn a2-btn-ghost">رجوع</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.business_service_prices.update', $row->id) }}">
        @csrf
        @method('PUT')

        @include('admin-v2.business-service-prices._form', [
            'row' => $row,
            'services' => $services,
            'businesses' => $businesses,
            'backUrl' => $backUrl,
        ])
    </form>
</div>
@endsection