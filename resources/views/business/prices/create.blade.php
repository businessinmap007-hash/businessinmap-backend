@extends('business.layouts.master')

@section('title', 'إضافة سعر')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">إضافة سعر</h1>
        <div class="a2-page-subtitle">سعر لكل نوع من الأنواع التي تقدّمها.</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.prices.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    </div>
</div>

<form method="POST" action="{{ route('business.prices.store') }}">
    @csrf
    @include('business.prices._form', [
        'row' => $row,
        'services' => $services,
        'allowedTypesByService' => $allowedTypesByService,
    ])
</form>
@endsection
