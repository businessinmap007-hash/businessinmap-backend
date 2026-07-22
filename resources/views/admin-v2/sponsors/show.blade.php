@extends('admin-v2.layouts.master')

@section('title', __('عرض راعٍ'))
@section('body_class', 'admin-v2-sponsors-show')

@section('content')
@php
    $s = $sponsor;

    $isActive = ! is_null($s->activated_at)
        && (is_null($s->expire_at) || \Carbon\Carbon::parse($s->expire_at)->gte(now()));
    $isExpired = ! is_null($s->expire_at) && \Carbon\Carbon::parse($s->expire_at)->lt(now());

    $typeLabel = $s->type === 'paid' ? __('مدفوع') : __('مجاني');

    // Resolve the ad image to a viewable URL (same rule as the album view).
    $img = trim((string) ($s->image ?? ''));
    if ($img !== '' && ! preg_match('~^https?://~i', $img)) {
        $img = '/' . ltrim($img, '/');
    }

    $userName = (string) ($s->user->name ?? '');
    $userLabel = $userName !== '' ? $userName : ($s->user_id ? '#' . $s->user_id : '—');
    $userShowUrl = null;
    if ($s->user_id) {
        try { $userShowUrl = route('admin.users.show', $s->user_id); } catch (\Throwable $e) {}
    }
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('راعٍ #') }}{{ $s->id }}</h1>
            <div class="a2-page-subtitle">
                @if($isActive)
                    <span class="a2-pill a2-pill-active">{{ __('نشط') }}</span>
                @elseif($isExpired)
                    <span class="a2-pill a2-pill-danger">{{ __('منتهٍ') }}</span>
                @else
                    <span class="a2-pill a2-pill-gray">{{ __('غير مفعّل') }}</span>
                @endif
                <span class="a2-pill a2-pill-gray">{{ $typeLabel }}</span>
            </div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.sponsors.index') }}">{{ __('رجوع') }}</a>
            <form method="POST" action="{{ route('admin.sponsors.toggleActive', $s->id) }}" style="display:inline;">
                @csrf
                <button class="a2-btn a2-btn-ghost" type="submit">{{ $isActive ? __('تعطيل') : __('تفعيل') }}</button>
            </form>
            <a class="a2-btn a2-btn-primary" href="{{ route('admin.sponsors.edit', $s->id) }}">{{ __('تعديل') }}</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-card">
        <div class="a2-album-show-grid">

            {{-- LEFT: the ad image --}}
            <div class="a2-card a2-card-flat">
                <div class="a2-header">
                    <h3 class="a2-section-title">{{ __('صورة الإعلان') }}</h3>
                </div>
                <div class="a2-album-cover-body">
                    @if($img !== '')
                        <div class="a2-album-cover-preview-wrap">
                            <a href="{{ $img }}" target="_blank" rel="noopener">
                                <img src="{{ $img }}" alt="sponsor" class="a2-album-cover-preview-img">
                            </a>
                        </div>
                        <div class="a2-muted a2-clip a2-mt-8" dir="ltr" title="{{ $s->image }}">{{ $s->image }}</div>
                    @else
                        <div class="a2-album-cover-placeholder a2-muted">{{ __('لا توجد صورة') }}</div>
                    @endif
                </div>
            </div>

            {{-- RIGHT: details --}}
            <div class="a2-card a2-card-flat">
                <div class="a2-header">
                    <h3 class="a2-section-title">{{ __('البيانات') }}</h3>
                </div>
                <div class="a2-album-details-body">
                    <div class="a2-kv">
                        <div class="a2-kv-row">
                            <div class="a2-kv-key">{{ __('المستخدم') }}</div>
                            <div class="a2-kv-val">
                                @if($userShowUrl)
                                    <a class="a2-link a2-clip" href="{{ $userShowUrl }}" title="{{ $userLabel }}">{{ $userLabel }}</a>
                                @else
                                    {{ $userLabel }}
                                @endif
                            </div>
                        </div>
                        <div class="a2-kv-row">
                            <div class="a2-kv-key">{{ __('النوع') }}</div>
                            <div class="a2-kv-val">{{ $typeLabel }}</div>
                        </div>
                        <div class="a2-kv-row">
                            <div class="a2-kv-key">{{ __('السعر') }}</div>
                            <div class="a2-kv-val" dir="ltr">{{ $s->price !== null ? number_format((float) $s->price, 2) : '—' }}</div>
                        </div>
                        <div class="a2-kv-row">
                            <div class="a2-kv-key">{{ __('تاريخ التفعيل') }}</div>
                            <div class="a2-kv-val" dir="ltr">{{ $s->activated_at ? $s->activated_at->format('Y-m-d H:i') : '—' }}</div>
                        </div>
                        <div class="a2-kv-row">
                            <div class="a2-kv-key">{{ __('تاريخ الانتهاء') }}</div>
                            <div class="a2-kv-val" dir="ltr">{{ $s->expire_at ? $s->expire_at->format('Y-m-d H:i') : '—' }}</div>
                        </div>
                        <div class="a2-kv-row">
                            <div class="a2-kv-key">{{ __('أُنشئ') }}</div>
                            <div class="a2-kv-val" dir="ltr">{{ $s->created_at ? $s->created_at->format('Y-m-d H:i') : '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
