@extends('admin-v2.layouts.master')

@section('title', 'Taxonomy Lab — ' . $child->name_ar)
@section('body_class', 'admin-v2 admin-v2-taxonomy-lab-options-builder')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <nav class="tls-crumb">
                <a href="{{ route('admin.taxonomy-lab.options.index') }}">{{ __('الخيارات حسب الابن') }}</a>
                <span class="tls-sep" aria-hidden="true">/</span>
                <span class="tls-here">{{ $child->name_ar }}</span>
            </nav>
            <h1 class="a2-page-title">{{ __('خيارات:') }} {{ $child->name_ar }}</h1>
            <div class="a2-page-subtitle">{{ __('اضغط الخيار في العمود الأيمن لإضافته لهذا الابن، ثم احفظ.') }}</div>
        </div>
    </div>

    @include('admin-v2.taxonomy-lab._transfer', [
        'ttAll' => $all,
        'ttSelected' => $selected,
        'ttSaveUrl' => route('admin.taxonomy-lab.options.save', $child->id, false),
        'ttIdsKey' => 'option_ids',
        'ttSourceLabel' => __('كل الخيارات'),
        'ttTargetLabel' => __('خيارات هذا الابن'),
    ])
</div>

<style>
.tls-crumb{font-size:13px;color:#868e96;margin-bottom:6px;display:flex;flex-wrap:wrap;align-items:center;gap:6px}
.tls-crumb a{color:var(--a2-primary,#3b5bdb);text-decoration:none}
.tls-sep{opacity:.5}
.tls-here{color:inherit;font-weight:600}
</style>
@endsection
