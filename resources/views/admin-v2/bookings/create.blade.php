{{-- resources/views/admin-v2/bookings/create.blade.php --}}
@extends('admin-v2.layouts.master')

@section('title','Create Booking')
@section('body_class','admin-v2-bookings create')

@section('content')
<div class="a2-page">
  <div class="a2-header" style="margin-bottom:12px;">
    <div>
      <div class="a2-title">إنشاء حجز</div>
      <div class="a2-hint">سيتم حساب السعر تلقائيًا من الخدمة</div>
    </div>
  </div>

  <form method="POST" action="{{ route('admin.bookings.store') }}">
    @csrf
    @include('admin-v2.bookings._form', ['submitLabel' => 'إنشاء'])
  </form>
</div>
@endsection