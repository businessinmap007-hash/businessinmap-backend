@extends('admin-v2.layouts.master')

@section('title','Create Option')
@section('body_class','admin-v2-options-create')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">إضافة Option</h1>
            <div class="a2-page-subtitle">إضافة خيار جديد</div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.options.store') }}">
        @csrf
        @include('admin-v2.options._form', ['row' => $row, 'hasIsActive' => $hasIsActive, 'hasSortOrder' => $hasSortOrder])
    </form>
</div>
@endsection