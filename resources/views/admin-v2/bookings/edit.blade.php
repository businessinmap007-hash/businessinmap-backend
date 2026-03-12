@extends('admin-v2.layouts.master')

@section('title', 'Edit Booking')

@section('content')
<div class="a2-page-head" style="margin-bottom:16px;">
    <div>
        <h1 class="a2-page-title">تعديل الحجز #{{ $booking->id }}</h1>
        <div class="a2-page-subtitle">تعديل بيانات الحجز والحالة والعنصر القابل للحجز</div>
    </div>
</div>

<form method="POST" action="{{ route('admin.bookings.update', $booking) }}">
    @csrf
    @method('PUT')
    @include('admin-v2.bookings._form', [
        'isEdit' => true,
        'booking' => $booking,
        'businessServicePrices' => $businessServicePrices,
    ])
</form>
@endsection