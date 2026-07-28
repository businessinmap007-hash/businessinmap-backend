@extends('business.layouts.master')

@section('title', __('إضافة صنف'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('إضافة صنف للمنيو') }}</h1>
        <div class="a2-page-subtitle">{{ __('أضف صنفًا يمكن للعميل طلبه.') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.menu.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
    </div>
</div>

<form method="POST" action="{{ route('business.menu.store') }}">
    @csrf
    @include('business.menu._form', ['row' => $row])
</form>
@endsection
