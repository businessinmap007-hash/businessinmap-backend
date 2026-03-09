@extends('admin-v2.layouts.master')

@section('title','Edit Booking')
@section('body_class','admin-v2-bookings')

@section('content')
<form method="POST" action="{{ route('admin.bookings.update', $booking) }}">
    @csrf
    @method('PUT')
    @include('admin-v2.bookings._form', [
        'submitLabel' => 'تحديث الحجز',
        'booking' => $booking->load('bookable'),
    ])
</form>
@endsection