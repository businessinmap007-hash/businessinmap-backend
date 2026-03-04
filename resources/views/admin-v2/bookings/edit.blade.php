{{-- resources/views/admin-v2/bookings/edit.blade.php --}}
@extends('admin-v2.layouts.master')

@section('title','Edit Booking')
@section('body_class','admin-v2-bookings edit')

@section('content')
<div class="a2-page">
  <div class="a2-header" style="margin-bottom:12px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
      <div class="a2-title">تعديل الحجز</div>
      <div class="a2-hint">Booking #{{ $booking->id }}</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookings.show', $booking) }}">عرض</a>
      <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookings.index') }}">رجوع</a>
    </div>
  </div>

  <form method="POST" action="{{ route('admin.bookings.update', $booking) }}">
    @csrf
    @method('PUT')
    @include('admin-v2.bookings._form', ['booking' => $booking, 'submitLabel' => 'تحديث'])
  </form>

  <div class="a2-card" style="padding:14px;margin-top:12px;">
    <div class="a2-header" style="margin-bottom:10px;">
      <div>
        <div class="a2-title" style="font-size:15px;color:#b91c1c;">حذف الحجز</div>
        <div class="a2-hint">سيتم Soft Delete (بحسب إعدادات الموديل)</div>
      </div>
    </div>

    <form method="POST" action="{{ route('admin.bookings.destroy', $booking) }}"
          onsubmit="return confirm('هل أنت متأكد من حذف الحجز؟');">
      @csrf
      @method('DELETE')
      <button class="a2-btn a2-btn-danger" type="submit">حذف</button>
    </form>
  </div>
</div>
@endsection