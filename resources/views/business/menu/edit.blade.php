@extends('business.layouts.master')

@section('title', 'تعديل صنف')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">تعديل صنف</h1>
        <div class="a2-page-subtitle">{{ $row->name_ar ?: $row->name_en }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.menu.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('business.menu.update', $row->id) }}">
    @csrf
    @method('PUT')
    @include('business.menu._form', ['row' => $row])
</form>
@endsection
