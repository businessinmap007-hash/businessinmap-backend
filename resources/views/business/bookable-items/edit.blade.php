@extends('business.layouts.master')

@section('title', __('تعديل وحدة'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('تعديل وحدة') }}</h1>
        <div class="a2-page-subtitle">{{ $row->title ?: $row->code }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.bookable-items.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('business.bookable-items.update', $row->id) }}">
    @csrf
    @method('PUT')
    @include('business.bookable-items._form', [
        'row' => $row,
        'services' => $services,
        'allowedTypesByService' => $allowedTypesByService,
        'lineOptions' => $lineOptions,
    ])
</form>

{{-- ───────── الصور ─────────
     الغرفةُ تُرى قبل أن تُحجَز. نفسُ معرض صنف المنيو ونفسُ حدِّه، لأنها
     نفسُ الآلية — و`HasOwnedImages` يحذف الملفَّ مع الصف. --}}
<div class="a2-card a2-card--section" style="margin-top:20px;">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('صور الوحدة') }}</div>
            <div class="a2-card-sub">{{ __('حتى ١٠ صور. تُحذف نهائيًا مع الوحدة.') }}</div>
        </div>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
        @forelse($row->images as $img)
            <div style="position:relative;width:120px;">
                <img src="{{ asset($img->image) }}" alt="" style="width:120px;height:120px;object-fit:cover;border-radius:8px;display:block;">
                <form method="POST" action="{{ route('business.bookable-items.images.destroy', [$row->id, $img->id]) }}" onsubmit="return confirm('{{ __('حذف هذه الصورة نهائيًا؟') }}')" style="margin-top:6px;">
                    @csrf @method('DELETE')
                    <button class="a2-btn a2-btn-sm a2-btn-ghost" type="submit" style="width:100%">{{ __('حذف') }}</button>
                </form>
            </div>
        @empty
            <div class="a2-muted">{{ __('لا صور بعد.') }}</div>
        @endforelse
    </div>

    <form method="POST" action="{{ route('business.bookable-items.images.store', $row->id) }}" enctype="multipart/form-data">
        @csrf
        <div class="a2-form-grid">
            <div class="a2-form-group a2-field-full">
                <label class="a2-label" for="images">{{ __('أضف صورًا') }}</label>
                <input class="a2-input" id="images" type="file" name="images[]" accept="image/*" multiple required>
            </div>
        </div>
        <button class="a2-btn a2-btn-primary" type="submit">{{ __('رفع') }}</button>
    </form>
</div>
@endsection
