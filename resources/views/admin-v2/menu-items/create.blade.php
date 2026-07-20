@extends('admin-v2.layouts.master')

@section('title', 'Create Menu Item')
@section('body_class', 'admin-v2-menu-items-create')

@section('content')
<div class="a2-page a2-page-narrow">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('إضافة عنصر منيو') }}</h1>
            <div class="a2-page-subtitle">{{ __('إنشاء صنف جديد ضمن قائمة طعام البزنس') }}</div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.menu-items.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
        </div>
    </div>

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.menu-items.store') }}">
        @csrf

        @include('admin-v2.menu-items._form', [
            'row' => $row,
            'businesses' => $businesses,
            'submitLabel' => 'حفظ',
        ])
    </form>
</div>
@endsection
