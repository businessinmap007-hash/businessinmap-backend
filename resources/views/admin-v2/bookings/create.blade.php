@extends('admin-v2.layouts.master')

@section('title','Create Booking')
@section('body_class','admin-v2-bookings')

@section('content')
<form method="POST" action="{{ route('admin.bookings.store') }}">
    @csrf
    @include('admin-v2.bookings._form', [
        'submitLabel' => 'إنشاء الحجز',
        'booking' => null,
    ])
</form>
@endsection