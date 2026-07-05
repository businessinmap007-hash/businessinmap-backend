@extends('business.layouts.master')

@section('title', 'تعديل سعر')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">تعديل سعر</h1>
        <div class="a2-page-subtitle" dir="ltr">{{ $row->bookable_item_type }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.prices.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('business.prices.update', $row->id) }}">
    @csrf
    @method('PUT')
    @include('business.prices._form', [
        'row' => $row,
        'services' => $services,
        'allowedTypesByService' => $allowedTypesByService,
    ])
</form>
@endsection
