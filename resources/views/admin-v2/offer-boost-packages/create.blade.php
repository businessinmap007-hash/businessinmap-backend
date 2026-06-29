@extends('admin-v2.layouts.master')

@section('title', 'Create Boost Package')
@section('topbar_title', 'Create Boost Package')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">إنشاء باقة تمييز</h1>
            <div class="a2-page-subtitle">إضافة باقة Boost جديدة للعروض.</div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.offer-boost-packages.store') }}">
        @csrf
        @include('admin-v2.offer-boost-packages._form')
    </form>
</div>
@endsection
