@extends('admin-v2.layouts.master')

@section('title', 'Taxonomy Lab')
@section('body_class', 'admin-v2 admin-v2-taxonomy-lab')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('🧪 مختبر تنظيم الخدمات والخيارات') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('مساحة معزولة نعيد فيها بناء تنظيم الخدمات والخيارات من جديد — خطوة خطوة. تعمل على جداول نسخ (_new) فقط؛ الجداول الحيّة والشاشات الحالية لا تتأثر حتى نقرّر الاستبدال.') }}
            </div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.taxonomy-lab.lists.index') }}" class="a2-btn a2-btn-primary">{{ __('القوائم الموحّدة ←') }}</a>
            <form method="POST" action="{{ route('admin.taxonomy-lab.reset') }}"
                  onsubmit="return confirm('{{ __('سيُعاد نسخ الذرّات من الجداول الحيّة وتُفرَّغ كل التجميعات المبنية يدويًا. متابعة؟') }}');">
                @csrf
                <button type="submit" class="a2-btn a2-btn-ghost">{{ __('إعادة ضبط المستودع من الحيّ') }}</button>
            </form>
        </div>
    </div>

    @if(session('status'))
        <div class="a2-alert a2-alert-ok" style="margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    <div class="tlab-flow">
        <span class="tlab-flow-step is-done">{{ __('١ · نسخ الذرّات') }}</span>
        <span class="tlab-flow-arrow" aria-hidden="true">←</span>
        <span class="tlab-flow-step is-active">{{ __('٢ · بناء التجميعات خطوة خطوة') }}</span>
        <span class="tlab-flow-arrow" aria-hidden="true">←</span>
        <span class="tlab-flow-step">{{ __('٣ · الاستبدال بالجداول الحيّة') }}</span>
    </div>

    <div class="tlab-grid">
        {{-- SERVICES rebuild --}}
        <section class="tlab-card">
            <header class="tlab-card-head">
                <h2 class="tlab-card-title">{{ __('الخدمات') }}</h2>
                <span class="tlab-card-tag">platform_services_new</span>
            </header>
            <p class="tlab-card-note">
                {{ __('نُسخت الخدمات وأنواع عناصرها كما هي. الفروع (التجميعات) فارغة — نبنيها من جديد ونوزّع الأنواع عليها.') }}
            </p>
            <div class="tlab-metrics">
                <div class="tlab-metric"><span class="tlab-num">{{ number_format($stats['services']) }}</span><span class="tlab-lbl">{{ __('خدمات') }}</span></div>
                <div class="tlab-metric"><span class="tlab-num">{{ number_format($stats['serviceTypes']) }}</span><span class="tlab-lbl">{{ __('أنواع عناصر') }}</span></div>
                <div class="tlab-metric"><span class="tlab-num">{{ number_format($stats['serviceBranches']) }}</span><span class="tlab-lbl">{{ __('فروع مبنية') }}</span></div>
            </div>
            <div class="tlab-progress">
                @php $tp = $stats['serviceTypes'] > 0 ? round($stats['typesGrouped'] * 100 / $stats['serviceTypes']) : 0; @endphp
                <div class="tlab-bar"><span style="width:{{ $tp }}%"></span></div>
                <div class="tlab-progress-lbl">
                    {{ __('مُجمّعة') }}: <strong>{{ number_format($stats['typesGrouped']) }}</strong>
                    · {{ __('متبقٍّ') }}: <strong>{{ number_format($stats['typesUngrouped']) }}</strong>
                    ({{ $tp }}%)
                </div>
            </div>
            <div class="tlab-card-foot">
                <a class="tlab-cta" href="{{ route('admin.taxonomy-lab.lists.index') }}">{{ __('نظّمها في القوائم الموحّدة') }} <span aria-hidden="true">←</span></a>
            </div>
        </section>

        {{-- OPTIONS rebuild --}}
        <section class="tlab-card">
            <header class="tlab-card-head">
                <h2 class="tlab-card-title">{{ __('الخيارات') }}</h2>
                <span class="tlab-card-tag">options_new</span>
            </header>
            <p class="tlab-card-note">
                {{ __('نُسخت الخيارات وروابطها بالأبناء كما هي. مجموعات الخيارات فارغة والخيارات بلا مجموعة — نعيد تجميعها من جديد بشكل منظّم.') }}
            </p>
            <div class="tlab-metrics">
                <div class="tlab-metric"><span class="tlab-num">{{ number_format($stats['options']) }}</span><span class="tlab-lbl">{{ __('خيارات') }}</span></div>
                <div class="tlab-metric"><span class="tlab-num">{{ number_format($stats['optionGroups']) }}</span><span class="tlab-lbl">{{ __('مجموعات مبنية') }}</span></div>
                <div class="tlab-metric"><span class="tlab-num">{{ number_format($stats['childLinks']) }}</span><span class="tlab-lbl">{{ __('روابط بالأبناء') }}</span></div>
            </div>
            <div class="tlab-progress">
                @php $op = $stats['options'] > 0 ? round($stats['optionsGrouped'] * 100 / $stats['options']) : 0; @endphp
                <div class="tlab-bar"><span style="width:{{ $op }}%"></span></div>
                <div class="tlab-progress-lbl">
                    {{ __('مُجمّعة') }}: <strong>{{ number_format($stats['optionsGrouped']) }}</strong>
                    · {{ __('متبقٍّ') }}: <strong>{{ number_format($stats['optionsUngrouped']) }}</strong>
                    ({{ $op }}%)
                </div>
            </div>
            <div class="tlab-card-foot">
                <a class="tlab-cta" href="{{ route('admin.taxonomy-lab.lists.index') }}">{{ __('نظّمها في القوائم الموحّدة') }} <span aria-hidden="true">←</span></a>
            </div>
        </section>
    </div>

    <div class="tlab-hint">
        {{ __('ملاحظة: زر «إعادة الضبط» ينسخ الذرّات من جديد ويمسح ما بنيته من تجميعات — استخدمه للبدء من صفحة نظيفة.') }}
    </div>
</div>

<style>
.tlab-flow{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:4px 0 18px}
.tlab-flow-step{font-size:13px;padding:6px 12px;border-radius:999px;background:#f1f3f5;color:#868e96}
.tlab-flow-step.is-done{background:#e6fcf5;color:#0ca678}
.tlab-flow-step.is-active{background:#edf2ff;color:#3b5bdb;font-weight:600}
.tlab-flow-arrow{color:#ced4da}
.tlab-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px}
.tlab-card{display:flex;flex-direction:column;gap:12px;padding:18px;border:1px solid var(--a2-border,#e3e6ea);border-radius:14px;background:var(--a2-surface,#fff)}
.tlab-card-head{display:flex;align-items:center;justify-content:space-between;gap:8px}
.tlab-card-title{font-size:17px;font-weight:700;margin:0}
.tlab-card-tag{font-size:11px;font-family:ui-monospace,monospace;padding:3px 8px;border-radius:6px;background:#f1f3f5;color:#868e96}
.tlab-card-note{font-size:13px;color:#868e96;margin:0;line-height:1.6}
.tlab-metrics{display:flex;gap:10px}
.tlab-metric{flex:1;display:flex;flex-direction:column;gap:2px;padding:10px 12px;border-radius:10px;background:#f8f9fa;text-align:center}
.tlab-num{font-size:22px;font-weight:700;line-height:1.1}
.tlab-lbl{font-size:12px;color:#868e96}
.tlab-progress{display:flex;flex-direction:column;gap:6px}
.tlab-bar{height:8px;border-radius:999px;background:#f1f3f5;overflow:hidden}
.tlab-bar>span{display:block;height:100%;background:var(--a2-primary,#3b5bdb);border-radius:999px;transition:width .2s}
.tlab-progress-lbl{font-size:12px;color:#868e96}
.tlab-card-foot{margin-top:auto}
.tlab-soon{display:inline-block;font-size:12px;padding:5px 10px;border-radius:8px;border:1px dashed #ced4da;color:#adb5bd}
.tlab-cta{display:inline-block;font-size:13px;font-weight:500;color:var(--a2-primary,#3b5bdb);text-decoration:none}
.tlab-hint{margin-top:16px;font-size:12px;color:#adb5bd}
.a2-alert-ok{padding:10px 14px;border-radius:10px;background:#e6fcf5;color:#0ca678;border:1px solid #c3fae8}
</style>
@endsection
