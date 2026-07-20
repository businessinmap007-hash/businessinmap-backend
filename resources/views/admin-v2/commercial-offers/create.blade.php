@extends('admin-v2.layouts.master')

@section('title', 'Create Commercial Offer')
@section('topbar_title', 'Create Commercial Offer')
@section('body_class', 'admin-v2-commercial-offers')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('إنشاء عرض تجاري') }}</h1>
            <div class="a2-page-subtitle">{{ __('عرض تسويقي أو سعر قابل للمقارنة لمنتج أو خدمة أو باكدج.') }}</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.commercial-offers.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.commercial-offers.store') }}">
        @csrf
        @include('admin-v2.commercial-offers._form')
    </form>
</div>
@endsection
