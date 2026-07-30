@extends('admin-v2.layouts.master')

@section('title', 'Taxonomy Lab — Options by child')
@section('body_class', 'admin-v2 admin-v2-taxonomy-lab-options')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('الخيارات حسب الابن') }}</h1>
            <div class="a2-page-subtitle">{{ __('اختر ابنًا (تصنيفًا) ثم حدّد الخيارات التي تخصّه.') }}</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.taxonomy-lab.index') }}" class="a2-btn a2-btn-ghost">{{ __('← المختبر') }}</a>
        </div>
    </div>

    @foreach($parents as $pid => $p)
        <section class="lo-parent">
            <h2 class="lo-parent-name">{{ $p['name'] }}</h2>
            <div class="lo-grid">
                @foreach($p['children'] as $c)
                    <a class="lo-child" href="{{ route('admin.taxonomy-lab.options.child', $c['id']) }}">
                        <span class="lo-child-name">{{ $c['name'] }}</span>
                        <span class="lo-child-count {{ $c['count'] ? 'has' : '' }}">{{ $c['count'] }} {{ __('خيار') }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>

<style>
.lo-parent{margin-top:18px}
.lo-parent-name{font-size:14px;font-weight:700;margin:0 0 10px;padding-bottom:6px;border-bottom:1px solid var(--a2-border,#eef0f2)}
.lo-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:10px}
.lo-child{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:11px 13px;border:1px solid var(--a2-border,#e3e6ea);border-radius:10px;background:var(--a2-surface,#fff);text-decoration:none;color:inherit;transition:border-color .12s}
.lo-child:hover{border-color:var(--a2-primary,#3b5bdb)}
.lo-child-name{font-size:13px;font-weight:600}
.lo-child-count{font-size:11px;color:#adb5bd;white-space:nowrap}
.lo-child-count.has{color:#1c7ed6;font-weight:600}
</style>
@endsection
