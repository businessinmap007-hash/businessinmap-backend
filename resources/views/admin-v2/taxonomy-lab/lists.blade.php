@extends('admin-v2.layouts.master')

@section('title', 'Taxonomy Lab — Lists')
@section('body_class', 'admin-v2 admin-v2-taxonomy-lab-lists')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('القوائم الموحّدة') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('أقسام منظّمة نبنيها من جديد — كل قسم يضمّ عناصره (من الخيارات أو أنواع العناصر)، ويمكن أن يحوي أقسامًا فرعية. اضغط أي قائمة لفتح صفحتها الداخلية.') }}
            </div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.taxonomy-lab.index') }}" class="a2-btn a2-btn-ghost">{{ __('← المختبر') }}</a>
            <button type="button" class="a2-btn a2-btn-primary" onclick="tlabCreateList(null)">{{ __('+ قائمة جديدة') }}</button>
        </div>
    </div>

    <div class="tll-grid" id="tll-grid">
        @forelse($lists as $l)
            <div class="tll-card" data-id="{{ $l['id'] }}">
                <a class="tll-card-main" href="{{ route('admin.taxonomy-lab.lists.show', $l['id']) }}">
                    <span class="tll-name">{{ $l['name'] }}</span>
                    <span class="tll-stats">
                        <strong>{{ number_format($l['items']) }}</strong> {{ __('عنصر') }}
                        @if($l['children'])<span class="tll-dot">·</span> <strong>{{ $l['children'] }}</strong> {{ __('قسم فرعي') }}@endif
                    </span>
                    <span class="tll-go">{{ __('فتح') }} <span aria-hidden="true">←</span></span>
                </a>
                <button type="button" class="tll-del" title="{{ __('حذف') }}" onclick="tlabDeleteList({{ $l['id'] }})">×</button>
            </div>
        @empty
            <div class="tll-empty">{{ __('لا توجد قوائم بعد — أنشئ أول قائمة.') }}</div>
        @endforelse
    </div>
</div>

<style>
.tll-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;margin-top:6px}
.tll-card{position:relative;border:1px solid var(--a2-border,#e3e6ea);border-radius:12px;background:var(--a2-surface,#fff);transition:border-color .12s,box-shadow .12s}
.tll-card:hover{border-color:var(--a2-primary,#3b5bdb);box-shadow:0 2px 10px rgba(0,0,0,.06)}
.tll-card-main{display:flex;flex-direction:column;gap:8px;padding:16px;text-decoration:none;color:inherit}
.tll-name{font-size:16px;font-weight:600}
.tll-stats{font-size:13px;color:#868e96}
.tll-stats strong{color:inherit}
.tll-dot{opacity:.5;margin:0 3px}
.tll-go{margin-top:6px;font-size:13px;color:var(--a2-primary,#3b5bdb);font-weight:500}
.tll-del{position:absolute;top:8px;left:8px;width:26px;height:26px;border:none;border-radius:8px;background:#f8f9fa;color:#adb5bd;font-size:18px;line-height:1;cursor:pointer}
.tll-del:hover{background:#fff0f0;color:#e03131}
.tll-empty{grid-column:1/-1;padding:40px;text-align:center;color:#adb5bd;border:1px dashed #dee2e6;border-radius:12px}
</style>

<script>
(function () {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const base = @json(route('admin.taxonomy-lab.lists.index', [], false));

    async function req(url, method, body) {
        const res = await fetch(url, {
            method,
            headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: body ? JSON.stringify(body) : undefined,
        });
        return res.json().catch(() => ({}));
    }

    window.tlabCreateList = async function (parentId) {
        const name = prompt(@json(__('اسم القائمة الجديدة')));
        if (!name) return;
        const out = await req(base, 'POST', { name_ar: name, parent_id: parentId });
        if (out.ok) location.reload();
        else alert(@json(__('تعذّر الإنشاء')));
    };

    window.tlabDeleteList = async function (id) {
        if (!confirm(@json(__('حذف هذه القائمة وكل ما بداخلها؟')))) return;
        const out = await req(base + '/' + id, 'DELETE');
        if (out.ok) document.querySelector('.tll-card[data-id="' + id + '"]')?.remove();
    };
})();
</script>
@endsection
