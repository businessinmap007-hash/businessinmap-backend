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
        </div>
        <div class="a2-page-actions">
            <button type="button" class="a2-btn a2-btn-ghost" onclick="tlsAddSubList()">{{ __('+ قسم فرعي') }}</button>
            <button type="button" class="a2-btn a2-btn-primary" id="tls-add-btn" onclick="tlsTogglePicker()">{{ __('+ إضافة عنصر') }}</button>
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

    {{-- Direct items of this list. --}}
    <section class="tls-block">
        <h2 class="tls-h">{{ __('عناصر هذه القائمة') }} <span class="tls-h-count" id="tls-count">{{ count($directItems) }}</span></h2>

        {{-- Inline add-item panel: expands in place, never blocks the page. --}}
        <div class="tls-picker" id="tls-picker" hidden>
            <div class="tls-picker-head">
                <input type="search" id="tls-q" class="tls-search" placeholder="{{ __('ابحث في الخيارات وأنواع العناصر والتخصصات…') }}" autocomplete="off">
                <button type="button" class="tls-picker-close" onclick="tlsClosePicker()" title="{{ __('إغلاق') }}">×</button>
            </div>
            <div class="tls-results" id="tls-results"></div>
        </div>

        <div class="tls-chips" id="tls-items">
            @forelse($directItems as $it)
                <span class="tls-chip" data-item-id="{{ $it['id'] }}">
                    <span class="tls-chip-tag tls-tag-{{ $it['source'] }}">{{ $it['source_label'] }}</span>
                    <span class="tls-chip-name">{{ $it['name'] }}</span>
                    <button type="button" class="tls-chip-x" onclick="tlsRemoveItem({{ $it['id'] }})" title="{{ __('إزالة') }}">×</button>
                </span>
            @empty
                <div class="tls-empty" id="tls-empty">{{ __('لا عناصر مباشرة — أضف عناصر أو أنشئ أقسامًا فرعية.') }}</div>
            @endforelse
        </div>
    </section>
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
.tls-chip-x{border:none;background:transparent;color:#adb5bd;font-size:16px;line-height:1;cursor:pointer}
.tls-chip-x:hover{color:#e03131}
.tls-empty{color:#adb5bd;padding:16px;border:1px dashed #dee2e6;border-radius:10px;width:100%}
.tls-picker{border:1px solid var(--a2-border,#e3e6ea);border-radius:12px;background:var(--a2-surface,#fff);padding:12px;margin-bottom:14px}
.tls-picker-head{display:flex;align-items:center;gap:8px}
.tls-picker-close{border:none;background:#f1f3f5;color:#868e96;width:34px;height:34px;border-radius:8px;font-size:18px;line-height:1;cursor:pointer;flex:0 0 auto}
.tls-picker-close:hover{background:#fff0f0;color:#e03131}
.tls-search{flex:1;width:100%;padding:9px 12px;border:1px solid var(--a2-border,#e3e6ea);border-radius:10px;font-size:14px;box-sizing:border-box}
.tls-results{margin-top:10px;max-height:340px;overflow:auto;display:flex;flex-direction:column;gap:4px}
.tls-res{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:8px;cursor:pointer;border:1px solid transparent}
.tls-res:hover{background:#f8f9fa;border-color:#e9ecef}
.tls-res-tag{font-size:10px;padding:1px 6px;border-radius:999px;color:#fff}
.tls-res-name{font-size:13px}
.tls-res-hint{color:#adb5bd;font-size:13px;padding:10px}
</style>

<script>
(function () {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const root = document.getElementById('tls-root');
    const listId = root.dataset.listId;
    const listsBase = @json(route('admin.taxonomy-lab.lists.index', [], false));
    const thisBase = listsBase + '/' + listId;

    async function req(url, method, body) {
        const res = await fetch(url, {
            method,
            headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: body ? JSON.stringify(body) : undefined,
        });
        return res.json().catch(() => ({}));
    }

    // ---- items ----
    window.tlsRemoveItem = async function (itemId) {
        const out = await req(thisBase + '/items/' + itemId, 'DELETE');
        if (out.ok) {
            document.querySelector('.tls-chip[data-item-id="' + itemId + '"]')?.remove();
            bumpCount(-1);
        }
    };

    function bumpCount(delta) {
        const el = document.getElementById('tls-count');
        el.textContent = Math.max(0, parseInt(el.textContent || '0', 10) + delta);
        document.getElementById('tls-empty')?.remove();
    }

    function addChip(item) {
        const wrap = document.getElementById('tls-items');
        const el = document.createElement('span');
        el.className = 'tls-chip';
        el.dataset.itemId = item.id;
        el.innerHTML = '<span class="tls-chip-tag tls-tag-' + item.source + '"></span>'
            + '<span class="tls-chip-name"></span>'
            + '<button type="button" class="tls-chip-x">×</button>';
        el.querySelector('.tls-chip-tag').textContent = item.source_label || item.source;
        el.querySelector('.tls-chip-name').textContent = item.name;
        el.querySelector('.tls-chip-x').addEventListener('click', () => tlsRemoveItem(item.id));
        wrap.appendChild(el);
        bumpCount(1);
    }

    // ---- picker (inline, non-blocking) ----
    let searchTimer = null;
    window.tlsTogglePicker = function () {
        const p = document.getElementById('tls-picker');
        if (p.hidden) {
            p.hidden = false;
            const q = document.getElementById('tls-q');
            q.value = '';
            q.focus();
            loadPool('');
        } else {
            tlsClosePicker();
        }
    };
    window.tlsClosePicker = function () { document.getElementById('tls-picker').hidden = true; };

    document.getElementById('tls-q').addEventListener('input', function () {
        clearTimeout(searchTimer);
        const v = this.value;
        searchTimer = setTimeout(() => loadPool(v), 180);
    });
    document.getElementById('tls-q').addEventListener('keydown', function (e) {
        if (e.key === 'Escape') tlsClosePicker();
    });

    async function loadPool(q) {
        const box = document.getElementById('tls-results');
        box.innerHTML = '<div class="tls-res-hint">' + @json(__('جارٍ البحث…')) + '</div>';
        const out = await req(thisBase + '/pool?q=' + encodeURIComponent(q), 'GET');
        box.innerHTML = '';
        if (!out.ok || !out.results.length) {
            box.innerHTML = '<div class="tls-res-hint">' + @json(__('لا نتائج')) + '</div>';
            return;
        }
        out.results.forEach(r => {
            const row = document.createElement('div');
            row.className = 'tls-res';
            const tagColor = { item_type: '#0ca678', option: '#3b5bdb', category_child: '#e8590c' }[r.source] || '#868e96';
            row.innerHTML = '<span class="tls-res-tag" style="background:' + tagColor + '"></span><span class="tls-res-name"></span>';
            row.querySelector('.tls-res-tag').textContent = r.source_label;
            row.querySelector('.tls-res-name').textContent = r.name;
            row.addEventListener('click', async () => {
                const res = await req(thisBase + '/items', 'POST', { source: r.source, source_id: r.source_id });
                if (res.ok) { addChip(res.item); row.remove(); }
            });
            box.appendChild(row);
        });
    }

    // ---- sub-list ----
    window.tlsAddSubList = async function () {
        const name = prompt(@json(__('اسم القسم الفرعي')));
        if (!name) return;
        const out = await req(listsBase, 'POST', { name_ar: name, parent_id: parseInt(listId, 10) });
        if (out.ok) location.reload();
    };
})();
</script>
@endsection
