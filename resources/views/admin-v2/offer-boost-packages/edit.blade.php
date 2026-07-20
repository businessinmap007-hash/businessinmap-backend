@extends('admin-v2.layouts.master')

@section('title', 'Edit Boost Package')
@section('topbar_title', 'Edit Boost Package')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('تعديل باقة تمييز') }}</h1>
            <div class="a2-page-subtitle">#{{ $package->id }} — {{ $package->displayName() }}</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.offer-boost-packages.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.offer-boost-packages.update', $package->id) }}">
        @csrf
        @method('PUT')
        @include('admin-v2.offer-boost-packages._form')
    </form>
</div>
@endsection
