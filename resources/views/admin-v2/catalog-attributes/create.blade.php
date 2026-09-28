@extends('admin-v2.layouts.master')

@section('title','New Catalog Attribute')
@section('body_class','admin-v2-catalog-attributes-create')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('نوع مواصفة جديد') }}</h1>
            <div class="a2-page-subtitle">{{ __('مثال: البروسيسور، سعة البطارية، حجم الشاشة') }}</div>
        </div>
    </div>

    <div class="a2-card a2-card--section">
        <form method="POST" action="{{ route('admin.catalog-attributes.store') }}">
            @csrf
            <div class="a2-form-grid">
                <div class="a2-form-group">
                    <label class="a2-label">{{ __('الكود') }} <span class="a2-danger">*</span></label>
                    <input class="a2-input" name="code" value="{{ old('code') }}" dir="ltr" placeholder="battery_capacity">
                    @error('code')<div class="a2-error">{{ $message }}</div>@enderror
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">{{ __('الاسم عربي') }} <span class="a2-danger">*</span></label>
                    <input class="a2-input" name="name_ar" value="{{ old('name_ar') }}">
                    @error('name_ar')<div class="a2-error">{{ $message }}</div>@enderror
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">{{ __('الاسم إنجليزي') }}</label>
                    <input class="a2-input" name="name_en" value="{{ old('name_en') }}" dir="ltr">
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">{{ __('نوع القيمة') }} <span class="a2-danger">*</span></label>
                    <select class="a2-select" name="data_type">
                        <option value="text" @selected(old('data_type') === 'text')>{{ __('نص') }}</option>
                        <option value="number" @selected(old('data_type') === 'number')>{{ __('رقم') }}</option>
                        <option value="boolean" @selected(old('data_type') === 'boolean')>{{ __('نعم/لا') }}</option>
                        <option value="select" @selected(old('data_type') === 'select')>{{ __('قائمة اختيار') }}</option>
                    </select>
                    @error('data_type')<div class="a2-error">{{ $message }}</div>@enderror
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">{{ __('الوحدة (للأرقام فقط)') }}</label>
                    <select class="a2-select" name="unit_id">
                        <option value="">{{ __('بدون وحدة') }}</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}" @selected((string) old('unit_id') === (string) $unit->id)>{{ $unit->name_ar ?: $unit->name_en }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
                <a href="{{ route('admin.catalog-products.create') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
                <button type="submit" class="a2-btn a2-btn-primary">{{ __('حفظ') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
