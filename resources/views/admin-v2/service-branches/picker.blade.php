@extends('admin-v2.layouts.master')

@section('title', 'Service Branches')
@section('body_class', 'admin-v2 admin-v2-service-branches-picker')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('تنظيم فروع الخدمات') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('اختر خدمة واحدة لإدارة فروعها وما بداخل كل فرع. كل خدمة على حدة — لا تظهر فروع الخدمات الأخرى.') }}
            </div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.platform-service-item-groups.index') }}" class="a2-btn a2-btn-ghost">{{ __('إدارة الفروع') }}</a>
        </div>
    </div>

    <div class="a2sb-grid">
        @foreach($cards as $c)
            <a class="a2sb-svc {{ $c['is_active'] ? '' : 'is-off' }}" href="{{ route('admin.service-branches.index', ['service_id' => $c['id']]) }}">
                <div class="a2sb-svc-head">
                    <span class="a2sb-svc-name">{{ $c['name'] }}</span>
                    @unless($c['is_active'])
                        <span class="a2sb-badge">{{ __('غير مفعّلة') }}</span>
                    @endunless
                </div>
                <div class="a2sb-svc-stats">
                    <span><strong>{{ $c['branches'] }}</strong> {{ __('فرع') }}</span>
                    <span class="a2sb-dot">·</span>
                    <span><strong>{{ $c['types'] }}</strong> {{ __('نوع') }}</span>
                </div>
                <div class="a2sb-svc-go">{{ __('إدارة الفروع') }} <span aria-hidden="true">←</span></div>
            </a>
        @endforeach
    </div>
</div>

<style>
.a2sb-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;margin-top:6px}
.a2sb-svc{display:flex;flex-direction:column;gap:10px;padding:16px;border:1px solid var(--a2-border,#e3e6ea);border-radius:12px;background:var(--a2-surface,#fff);text-decoration:none;color:inherit;transition:border-color .12s,box-shadow .12s}
.a2sb-svc:hover{border-color:var(--a2-primary,#3b5bdb);box-shadow:0 2px 10px rgba(0,0,0,.06)}
.a2sb-svc.is-off{opacity:.6}
.a2sb-svc-head{display:flex;align-items:center;justify-content:space-between;gap:8px}
.a2sb-svc-name{font-size:16px;font-weight:600}
.a2sb-badge{font-size:11px;padding:2px 7px;border-radius:999px;background:#f1f3f5;color:#868e96}
.a2sb-svc-stats{font-size:13px;color:#868e96;display:flex;align-items:center;gap:7px}
.a2sb-svc-stats strong{color:inherit;font-weight:600}
.a2sb-dot{opacity:.5}
.a2sb-svc-go{margin-top:auto;font-size:13px;color:var(--a2-primary,#3b5bdb);font-weight:500}
</style>
@endsection
