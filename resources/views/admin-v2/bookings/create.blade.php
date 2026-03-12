@extends('admin-v2.layouts.master')

@section('title', 'Create Booking')

@section('content')
<div class="a2-page-head" style="margin-bottom:16px;">
    <div>
        <h1 class="a2-page-title">إضافة حجز</h1>
        <div class="a2-page-subtitle">إنشاء حجز جديد بنفس منطق الخدمات والأسعار والديبوزت</div>
    </div>
</div>

<form method="POST" action="{{ route('admin.bookings.store') }}">
    @csrf
    @include('admin-v2.bookings._form', [
        'isEdit' => false,
        'booking' => new \App\Models\Booking(),
        'businessServicePrices' => $businessServicePrices,
    ])
</form>
@endsection