@extends('business.layouts.master')

@section('title', __('الموظفون'))

@section('content')
@php $__en = app()->getLocale() === 'en'; @endphp
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('الموظفون والصلاحيات') }}</h1>
        <div class="a2-page-subtitle">{{ __('امنح موظفًا (سكرتيرة، كاشير، مسؤول صفحة) إدارة نشاطك بصلاحيات محدّدة من قائمة الخدمات.') }}</div>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="a2-alert a2-alert-danger">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('إضافة موظف') }}</div>
            <div class="a2-card-sub">{{ __('حدّد الموظف بهاتفه المسجّل في التطبيق، ثم اختر ما يُسمح له بإدارته.') }}</div>
        </div>
    </div>

    <form method="POST" action="{{ route('business.staff.store') }}">
        @csrf
        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label" for="phone">{{ __('هاتف الموظف') }}</label>
                <input type="text" id="phone" name="phone" class="a2-input" value="{{ old('phone') }}" placeholder="01xxxxxxxxx">
            </div>
            <div class="a2-form-group">
                <label class="a2-label" for="title">{{ __('المسمّى (اختياري)') }}</label>
                <input type="text" id="title" name="title" class="a2-input" value="{{ old('title') }}" placeholder="{{ __('سكرتيرة / كاشير') }}">
            </div>
        </div>

        <div class="a2-form-group a2-field-full" style="margin-top:8px;">
            <label class="a2-label">{{ __('الصلاحيات') }}</label>
            <div class="a2-form-grid">
                @foreach($capabilities as $key => [$ar, $en])
                    <label class="a2-check">
                        <input type="checkbox" name="capabilities[]" value="{{ $key }}"
                               @checked(in_array($key, (array) old('capabilities', [])))>
                        <span>{{ $__en ? $en : $ar }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="a2-page-actions" style="justify-content:flex-end;margin-top:12px;">
            <button type="submit" class="a2-btn a2-btn-primary">{{ __('منح الصلاحيات') }}</button>
        </div>
    </form>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div><div class="a2-card-title">{{ __('الموظفون الحاليون') }}</div></div>
    </div>

    @forelse($staff as $member)
        <form method="POST" action="{{ route('business.staff.update', $member->user_id) }}"
              class="a2-card" style="margin-bottom:12px;padding:12px;">
            @csrf
            @method('PUT')
            <div class="a2-page-head" style="margin-bottom:8px;">
                <div>
                    <div class="a2-card-title">
                        {{ optional($member->user)->name ?? ('#' . $member->user_id) }}
                        @if(optional($member->user)->phone)
                            <span class="a2-page-subtitle">({{ $member->user->phone }})</span>
                        @endif
                    </div>
                </div>
                <label class="a2-check">
                    <input type="checkbox" name="is_active" value="1" @checked($member->is_active)>
                    <span>{{ __('نشط') }}</span>
                </label>
            </div>

            <div class="a2-form-group">
                <label class="a2-label">{{ __('المسمّى') }}</label>
                <input type="text" name="title" class="a2-input" value="{{ $member->title }}" placeholder="{{ __('سكرتيرة / كاشير') }}">
            </div>

            <div class="a2-form-group a2-field-full" style="margin-top:8px;">
                <label class="a2-label">{{ __('الصلاحيات') }}</label>
                <div class="a2-form-grid">
                    @foreach($capabilities as $key => [$ar, $en])
                        <label class="a2-check">
                            <input type="checkbox" name="capabilities[]" value="{{ $key }}"
                                   @checked(in_array($key, (array) $member->capabilities))>
                            <span>{{ $__en ? $en : $ar }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="a2-page-actions" style="justify-content:flex-end;gap:8px;margin-top:12px;">
                <button type="submit" class="a2-btn a2-btn-primary">{{ __('حفظ') }}</button>
        </div>
        </form>
        <form method="POST" action="{{ route('business.staff.destroy', $member->user_id) }}"
              onsubmit="return confirm('{{ __('إزالة هذا الموظف؟') }}');" style="margin:-8px 0 16px;">
            @csrf
            @method('DELETE')
            <button type="submit" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __('إزالة الموظف') }}</button>
        </form>
    @empty
        <div class="a2-empty">{{ __('لا يوجد موظفون بعد.') }}</div>
    @endforelse
</div>
@endsection
