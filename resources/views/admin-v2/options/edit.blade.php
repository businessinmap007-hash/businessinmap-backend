@extends('admin-v2.layouts.master')

@section('title','Edit Option')
@section('body_class','admin-v2-options-edit')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل Option</h1>
            <div class="a2-page-subtitle">تعديل بيانات الخيار</div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.options.update', $row) }}">
        @csrf
        @method('PUT')
        @include('admin-v2.options._form', ['row' => $row, 'hasIsActive' => $hasIsActive, 'hasSortOrder' => $hasSortOrder])
    </form>
</div>
@endsection