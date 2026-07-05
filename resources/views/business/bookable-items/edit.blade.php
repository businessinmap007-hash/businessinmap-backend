@extends('business.layouts.master')

@section('title', 'تعديل وحدة')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">تعديل وحدة</h1>
        <div class="a2-page-subtitle">{{ $row->title ?: $row->code }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.bookable-items.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('business.bookable-items.update', $row->id) }}">
    @csrf
    @method('PUT')
    @include('business.bookable-items._form', [
        'row' => $row,
        'services' => $services,
        'allowedTypesByService' => $allowedTypesByService,
    ])
</form>
@endsection
