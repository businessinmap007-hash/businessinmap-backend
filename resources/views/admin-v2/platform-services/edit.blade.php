@extends('admin-v2.layouts.master')

@section('title','Edit Platform Service')
@section('body_class','admin-v2 admin-v2-platform-services-edit')
@section('topbar_title','Edit Platform Service')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">Edit Platform Service</h1>
            <div class="a2-page-subtitle">
                تعديل تعريف الخدمة فقط بدون أي رسوم أو تسعير.
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.platform-services.index') }}" class="a2-btn a2-btn-ghost">
                Back
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="a2-alert a2-alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            <div class="a2-fw-900 a2-mb-8">يوجد أخطاء في البيانات:</div>

            <ul class="a2-errors-list">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.platform-services.update', $row) }}">
        @csrf
        @method('PUT')

        @include('admin-v2.platform-services._form', [
            'submitLabel' => 'Update'
        ])
    </form>
</div>
@endsection