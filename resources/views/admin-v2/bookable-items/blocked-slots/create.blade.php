@extends('admin-v2.layouts.master')

@section('title', 'Create Blocked Slot')
@section('body_class', 'admin-v2-bookable-blocked-slot-create')

@section('content')
@include('admin-v2.bookable-items.partials.tabs', ['item' => $item])

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">إضافة فترة غلق</h1>
            <div class="a2-page-subtitle">{{ $item->title }}</div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.bookable-items.calendar', ['bookableItem' => $item->id]) }}"
               class="a2-btn a2-btn-ghost">
                العودة إلى التقويم
            </a>
        </div>
    </div>

    <div class="a2-card">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">بيانات فترة الغلق</div>
                <div class="a2-card-sub">أدخل تاريخ البداية والنهاية ونوع الغلق</div>
            </div>
        </div>

        <form method="POST"
              action="{{ route('admin.bookable-items.blocked-slots.store', ['bookableItem' => $item->id]) }}"
              class="a2-form">
            @csrf

            @include('admin-v2.bookable-items.blocked-slots._form', [
                'bookableItem' => $item,
                'slot' => null,
                'submitLabel' => 'إضافة فترة الغلق'
            ])
        </form>
    </div>
</div>
@endsection