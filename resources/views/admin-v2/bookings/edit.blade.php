@extends('admin-v2.layouts.master')

@section('title', 'Edit Booking')
@section('body_class', 'admin-v2 admin-v2-bookings-edit')

@section('content')
<div class="a2-page">

    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل الحجز #{{ $booking->id }}</h1>
            <div class="a2-page-subtitle">
                تعديل بيانات الحجز، الخدمة، الموعد، الغرفة، الحالة، والسعر المحسوب.
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.bookings.show', $booking) }}" class="a2-btn a2-btn-primary">
                عرض الحجز
            </a>

            <a href="{{ route('admin.bookings.index') }}" class="a2-btn a2-btn-ghost">
                رجوع
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="a2-alert a2-alert-danger">
            {{ session('error') }}
        </div>
    @endif
    @php
$selectedBookableItemId = (int) old('bookable_item_id', $selectedBookableItemId ?? 0);
@endphp

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            <div class="a2-fw-900 a2-mb-8">يوجد أخطاء في بيانات الحجز:</div>

            <ul class="bk-error-list">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.bookings.update', $booking) }}" class="bk-form-shell">
        @csrf
        @method('PUT')

        @include('admin-v2.bookings._form', [
            'isEdit' => true,
            'booking' => $booking,
            'selectedBookableItemId' => $selectedBookableItemId,
            'statusOptions' => $statusOptions ?? \App\Models\Booking::statusOptions(),
            'services' => $services ?? collect(),
            'businesses' => $businesses ?? collect(),
            'clients' => $clients ?? collect(),
            'businessServicePrices' => $businessServicePrices ?? collect(),
        ])
    </form>
</div>
@endsection