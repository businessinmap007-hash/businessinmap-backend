@extends('admin-v2.layouts.master')

@section('title','Create Bookable Item')
@section('body_class','admin-v2-bookable-items-create')

@section('content')
<div class="a2-page a2-page-narrow">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">إضافة عنصر قابل للحجز</h1>
            <div class="a2-page-subtitle">
                إنشاء غرفة أو ملعب أو طاولة أو أي عنصر قابل للحجز حسب الخدمة والتصنيف
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.bookable-items.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
        </div>
    </div>

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.bookable-items.store') }}">
        @csrf

        @include('admin-v2.bookable-items._form', [
            'row' => $row,
            'services' => $services,
            'businesses' => $businesses,
            'allowedItemTypes' => $allowedItemTypes ?? [],
            'submitLabel' => 'حفظ',
        ])
    </form>
</div>
@endsection