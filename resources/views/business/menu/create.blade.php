@extends('business.layouts.master')

@section('title', 'إضافة صنف')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">إضافة صنف للمنيو</h1>
        <div class="a2-page-subtitle">أضف صنفًا يمكن للعميل طلبه.</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.menu.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    </div>
</div>

<form method="POST" action="{{ route('business.menu.store') }}">
    @csrf
    @include('business.menu._form', ['row' => $row])
</form>
@endsection
