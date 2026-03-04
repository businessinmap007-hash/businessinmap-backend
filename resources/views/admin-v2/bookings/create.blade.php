@extends('admin-v2.layouts.master')

@section('title','Create Booking')
@section('body_class','admin-v2-bookings')

@section('content')
<div class="a2-page">
  <div class="a2-card" style="max-width:980px;margin:0 auto;">

    <div class="a2-header">
      <div>
        <h2 class="a2-title">إضافة حجز</h2>
        <div class="a2-hint">إنشاء Booking جديد</div>
      </div>
      <div class="a2-actionsbar">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookings.index') }}">رجوع</a>
      </div>
    </div>

    <form method="POST" action="{{ route('admin.bookings.store') }}" style="display:grid;gap:12px;">
      @csrf
      @include('admin-v2.bookings._form', ['booking' => $booking, 'statusOptions' => $statusOptions])

      <div class="a2-actionsbar" style="justify-content:flex-end;">
        <button class="a2-btn a2-btn-primary" type="submit">حفظ</button>
      </div>
    </form>

  </div>
</div>
@endsection