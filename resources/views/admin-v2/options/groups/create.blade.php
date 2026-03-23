@extends('admin-v2.layouts.master')

@section('title','Add Option Group')

@section('content')
<form method="POST" action="{{ route('admin.option-groups.store') }}">
    @csrf

    <div class="a2-page">
        <div class="a2-page-head">
            <h1 class="a2-page-title">إضافة مجموعة</h1>
        </div>

        @include('admin-v2.options.groups._form')

        <div class="a2-page-actions" style="justify-content:flex-end;">
            <a href="{{ route('admin.option-groups.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
            <button class="a2-btn a2-btn-primary">حفظ</button>
        </div>
    </div>
</form>
@endsection