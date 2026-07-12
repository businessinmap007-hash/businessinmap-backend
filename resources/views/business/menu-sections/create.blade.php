@extends('business.layouts.master')

@section('title', 'إضافة قسم')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">إضافة قسم للمنيو</h1>
        <div class="a2-page-subtitle">قسم يجمع أصناف المنيو المتشابهة.</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.menu-sections.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    </div>
</div>

<form method="POST" action="{{ route('business.menu-sections.store') }}">
    @csrf
    @include('business.menu-sections._form', ['row' => $row])
</form>
@endsection
