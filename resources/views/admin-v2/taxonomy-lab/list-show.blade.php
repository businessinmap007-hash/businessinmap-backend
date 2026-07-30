@extends('admin-v2.layouts.master')

@section('title', 'Taxonomy Lab — ' . $list->displayName('ar'))
@section('body_class', 'admin-v2 admin-v2-taxonomy-lab-list')

@section('content')
<div class="a2-page" id="tls-root" data-list-id="{{ $list->id }}">
    <div class="a2-page-head">
        <div>
            <nav class="tls-crumb">
                <a href="{{ route('admin.taxonomy-lab.lists.index') }}">{{ __('القوائم') }}</a>
                @foreach($breadcrumb as $c)
                    <span class="tls-sep" aria-hidden="true">/</span>
                    @if($loop->last)
                        <span class="tls-here">{{ $c['name'] }}</span>
                    @else
                        <a href="{{ route('admin.taxonomy-lab.lists.show', $c['id']) }}">{{ $c['name'] }}</a>
                    @endif
                @endforeach
            </nav>
            <h1 class="a2-page-title">{{ $list->displayName('ar') }}</h1>
            <div class="a2-page-subtitle">{{ __('اضغط الخدمة في العمود الأيمن لإضافتها لهذا الفرع، ثم احفظ.') }}</div>
        </div>
        <div class="a2-page-actions">
            <button type="button" class="a2-btn a2-btn-ghost" onclick="tlsAddSubList()">{{ __('+ قسم فرعي') }}</button>
        </div>
    </div>

    {{-- Sub-lists: each opens its own internal page. --}}
    @if(count($subLists))
    <section class="tls-block">
        <h2 class="tls-h">{{ __('الأقسام الفرعية') }}</h2>
        <div class="tls-sub-grid">
            @foreach($subLists as $s)
                <a class="tls-sub" href="{{ route('admin.taxonomy-lab.lists.show', $s['id']) }}">
                    <div class="tls-sub-head">
                        <span class="tls-sub-name">{{ $s['name'] }}</span>
                        <span class="tls-sub-count">{{ number_format(count($s['items'])) }} {{ __('عنصر') }}</span>
                    </div>
                    <div class="tls-sub-preview">
                        @foreach(array_slice($s['items'], 0, 6) as $it)
                            <span class="tls-mini">{{ $it['name'] }}</span>
                        @endforeach
                        @if(count($s['items']) > 6)<span class="tls-mini tls-more">+{{ count($s['items']) - 6 }}</span>@endif
                    </div>
                    <span class="tls-sub-go">{{ __('فتح الصفحة الداخلية') }} <span aria-hidden="true">←</span></span>
                </a>
            @endforeach
        </div>
    </section>
    @endif

    {{-- The two-column services transfer (item types). --}}
    <section class="tls-block">
        <h2 class="tls-h">{{ __('خدمات هذا الفرع') }}</h2>
        @include('admin-v2.taxonomy-lab._transfer', [
            'ttAll' => $allTypes,
            'ttSelected' => $selectedTypes,
            'ttSaveUrl' => route('admin.taxonomy-lab.lists.items.sync', $list->id, false),
            'ttIdsKey' => 'ids',
            'ttExtra' => ['source' => 'item_type'],
            'ttSourceLabel' => __('كل الخدمات'),
            'ttTargetLabel' => __('خدمات الفرع'),
        ])
    </section>

    {{-- Non-service items (options / specialties) attached to this list — read-only here. --}}
    @if(count($otherItems))
    <section class="tls-block">
        <h2 class="tls-h">{{ __('عناصر أخرى مرتبطة (خيارات/تخصصات) — للعلم') }} <span class="tls-h-count">{{ count($otherItems) }}</span></h2>
        <div class="tls-chips">
            @foreach($otherItems as $it)
                <span class="tls-chip">
                    <span class="tls-chip-tag tls-tag-{{ $it['source'] }}">{{ $it['source_label'] }}</span>
                    <span class="tls-chip-name">{{ $it['name'] }}</span>
                </span>
            @endforeach
        </div>
    </section>
    @endif
</div>

<style>
.tls-crumb{font-size:13px;color:#868e96;margin-bottom:6px;display:flex;flex-wrap:wrap;align-items:center;gap:6px}
.tls-crumb a{color:var(--a2-primary,#3b5bdb);text-decoration:none}
.tls-sep{opacity:.5}
.tls-here{color:inherit;font-weight:600}
.tls-block{margin-top:18px}
.tls-h{font-size:14px;font-weight:700;margin:0 0 10px;display:flex;align-items:center;gap:8px}
.tls-h-count{font-size:12px;font-weight:600;color:#868e96;background:#f1f3f5;border-radius:999px;padding:1px 8px}
.tls-sub-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px}
.tls-sub{display:flex;flex-direction:column;gap:10px;padding:16px;border:1px solid var(--a2-border,#e3e6ea);border-radius:12px;background:var(--a2-surface,#fff);text-decoration:none;color:inherit;transition:border-color .12s,box-shadow .12s}
.tls-sub:hover{border-color:var(--a2-primary,#3b5bdb);box-shadow:0 2px 10px rgba(0,0,0,.06)}
.tls-sub-head{display:flex;align-items:center;justify-content:space-between;gap:8px}
.tls-sub-name{font-size:15px;font-weight:600}
.tls-sub-count{font-size:12px;color:#868e96;white-space:nowrap}
.tls-sub-preview{display:flex;flex-wrap:wrap;gap:5px;min-height:22px}
.tls-mini{font-size:11px;padding:2px 7px;border-radius:6px;background:#f1f3f5;color:#495057}
.tls-mini.tls-more{background:#edf2ff;color:#3b5bdb}
.tls-sub-go{margin-top:auto;font-size:13px;color:var(--a2-primary,#3b5bdb);font-weight:500}
.tls-chips{display:flex;flex-wrap:wrap;gap:8px}
.tls-chip{display:inline-flex;align-items:center;gap:7px;padding:5px 8px 5px 5px;border:1px solid var(--a2-border,#e3e6ea);border-radius:999px;background:var(--a2-surface,#fff);font-size:13px}
.tls-chip-tag{font-size:10px;padding:1px 6px;border-radius:999px;color:#fff}
.tls-tag-option{background:#3b5bdb}
.tls-tag-item_type{background:#0ca678}
.tls-tag-category_child{background:#e8590c}
.tls-chip-name{font-weight:500}
</style>

<script>
(function () {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const listId = document.getElementById('tls-root').dataset.listId;
    const listsBase = @json(route('admin.taxonomy-lab.lists.index', [], false));

    window.tlsAddSubList = async function () {
        const name = prompt(@json(__('اسم القسم الفرعي')));
        if (!name) return;
        const res = await fetch(listsBase, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ name_ar: name, parent_id: parseInt(listId, 10) }),
        });
        const out = await res.json().catch(() => ({}));
        if (out.ok) location.reload();
    };
})();
</script>
@endsection
