@extends('business.layouts.master')

@section('title', __('إضافة وحدة'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('إضافة وحدة قابلة للحجز') }}</h1>
        <div class="a2-page-subtitle">{{ __('أضف وحدة فعلية تمتلكها (غرفة، طاولة، ملعب...).') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.bookable-items.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
    </div>
</div>

<form method="POST" action="{{ route('business.bookable-items.store') }}">
    @csrf
    @include('business.bookable-items._form', [
        'row' => $row,
        'services' => $services,
        'allowedTypesByService' => $allowedTypesByService,
        'lineOptions' => $lineOptions,
    ])
</form>
@endsection
