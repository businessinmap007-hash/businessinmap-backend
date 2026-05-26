@extends('admin-v2.layouts.master')

@section('title','Edit Business Service Price')
@section('body_class','admin-v2 admin-v2-business-service-prices-edit')

@section('content')
@php
    $backUrl = route('admin.business_service_prices.index');

    $displayName = $row->display_name
        ?? (($row->business->name ?? 'Business') . ' / ' . ($row->service->name_ar ?? $row->service->key ?? 'Service'));
@endphp

<div class="a2-page a2-page-narrow">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل سعر خدمة</h1>
            <div class="a2-page-subtitle">
                {{ $displayName }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ $backUrl }}" class="a2-btn a2-btn-ghost">رجوع</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="a2-alert a2-alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            <div class="a2-fw-900 a2-mb-8">يوجد أخطاء في البيانات:</div>

            <ul style="margin:0;padding-inline-start:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.business_service_prices.update', $row->id) }}">
        @csrf
        @method('PUT')

        @include('admin-v2.business-service-prices._form', [
            'row' => $row,
            'services' => $services,
            'businesses' => $businesses,
            'children' => $children,
            'backUrl' => $backUrl,
        ])
    </form>
</div>
@endsection