@extends('admin-v2.layouts.master')

@section('title','Edit Booking')
@section('body_class','admin-v2-bookings')

@section('content')
<div class="a2-page">
  <div class="a2-card" style="max-width:980px;margin:0 auto;">

    <div class="a2-header">
      <div>
        <h2 class="a2-title">تعديل حجز</h2>
        <div class="a2-hint">ID #{{ $booking->id }}</div>
      </div>
      <div class="a2-actionsbar">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookings.show', $booking) }}">عرض</a>
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookings.index') }}">رجوع</a>
      </div>
    </div>

    <form method="POST" action="{{ route('admin.bookings.update', $booking) }}" style="display:grid;gap:12px;">
      @csrf
      @method('PUT')

      @include('admin-v2.bookings._form', ['booking' => $booking, 'statusOptions' => $statusOptions])

      <div class="a2-actionsbar" style="justify-content:space-between;align-items:center;">
        <form method="POST" action="{{ route('admin.bookings.destroy', $booking) }}" onsubmit="return confirm('حذف الحجز؟');">
          @csrf
          @method('DELETE')
          <button class="a2-btn a2-btn-danger" type="submit">حذف</button>
        </form>

        <form method="POST" action="{{ route('admin.bookings.update', $booking) }}">
          @csrf
          @method('PUT')

          <!-- fields -->

          <button type="submit" class="a2-btn a2-btn-primary">حفظ</button>
        </form>
      </div>
    </form>

  </div>
</div>
@endsection