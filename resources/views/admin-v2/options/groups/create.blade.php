@extends('admin-v2.layouts.master')

@section('title','Create Group')

@section('content')
<div class="a2-page">

    <div class="a2-page-head">
        <h1 class="a2-page-title">إضافة Group</h1>
    </div>

    <form method="POST" action="{{ route('admin.option-groups.store') }}">
        @csrf

        @include('admin-v2.options.groups._form')
    </form>

</div>
@endsection