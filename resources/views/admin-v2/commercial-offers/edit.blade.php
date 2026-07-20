@extends('admin-v2.layouts.master')

@section('title', 'Edit Commercial Offer')
@section('topbar_title', 'Edit Commercial Offer')
@section('body_class', 'admin-v2-commercial-offers')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('تعديل عرض #') }}{{ $offer->id }}</h1>
            <div class="a2-page-subtitle">{{ $offer->displayTitle() }}</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.commercial-offers.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.commercial-offers.update', $offer->id) }}">
        @csrf
        @method('PUT')
        @include('admin-v2.commercial-offers._form')
    </form>
</div>
@endsection
