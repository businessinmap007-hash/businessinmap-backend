@extends('business.layouts.master')

@section('title', __('تعديل خط التشغيل'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('تعديل خط التشغيل #:id', ['id' => $row->id]) }}</h1>
        <div class="a2-page-subtitle">{{ __('أي تعديل ينعكس مباشرة على نتائج البحث لدى العملاء.') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.schedules.reservations.index', ['trip_schedule_id' => $row->id]) }}" class="a2-btn a2-btn-ghost">{{ __('حجوزات هذا الخط') }}</a>
        <a href="{{ route('business.schedules.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
    </div>
</div>

<form method="POST" action="{{ route('business.schedules.update', $row->id) }}">
    @csrf
    @method('PUT')
    @include('business.schedules._form')
</form>
@endsection
