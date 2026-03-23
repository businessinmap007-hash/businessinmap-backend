@extends('admin-v2.layouts.master')

@section('title','Edit Option Group')

@section('content')
<form method="POST" action="{{ route('admin.option-groups.update', $group->id) }}">
    @csrf
    @method('PUT')

    <div class="a2-page">
        <div class="a2-page-head">
            <h1 class="a2-page-title">تعديل مجموعة</h1>
        </div>

        @include('admin-v2.options.groups._form')

        <div class="a2-page-actions" style="justify-content:flex-end;">
            <a href="{{ route('admin.option-groups.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
            <button class="a2-btn a2-btn-primary">تحديث</button>
        </div>
    </div>
</form>
@endsection