{{--
  Two-column transfer picker (shared).
  Expects:
    $ttAll        collection/array of ['id'=>int,'name'=>string]  (the source column)
    $ttSelected   array of ids already assigned
    $ttSaveUrl    relative URL to POST the batch save to
    $ttIdsKey     JSON key to send the id array under (e.g. 'ids' or 'option_ids')
    $ttExtra      (optional) assoc array merged into the POST body (e.g. ['source'=>'item_type'])
    $ttSourceLabel / $ttTargetLabel  column titles
--}}
<div class="tt" id="tt"
     data-save-url="{{ $ttSaveUrl }}"
     data-ids-key="{{ $ttIdsKey }}"
     data-extra='@json($ttExtra ?? (object)[])'>
    <div class="tt-bar">
        <span class="tt-count"><b id="tt-picked">0</b> / {{ count($ttAll) }} {{ __('مختار') }}</span>
        <span class="tt-dirty" id="tt-dirty" hidden>{{ __('• تغييرات غير محفوظة') }}</span>
        <button type="button" class="a2-btn a2-btn-primary tt-save" id="tt-save" onclick="ttSave()">{{ __('حفظ') }}</button>
    </div>

    <div class="tt-cols">
        <div class="tt-col">
            <div class="tt-head">
                <span>{{ $ttSourceLabel ?? __('كل العناصر') }}</span>
                <input type="search" id="tt-search" class="tt-search" placeholder="{{ __('بحث…') }}" autocomplete="off">
            </div>
            <div class="tt-list" id="tt-source"></div>
        </div>
        <div class="tt-col">
            <div class="tt-head"><span>{{ $ttTargetLabel ?? __('المختار') }}</span></div>
            <div class="tt-list" id="tt-target"></div>
        </div>
    </div>
</div>

<style>
.tt{margin-top:12px}
.tt-bar{display:flex;align-items:center;gap:14px;margin-bottom:10px}
.tt-count{font-size:13px;color:#495057}
.tt-count b{color:#1c7ed6}
.tt-dirty{font-size:12px;color:#e8590c;font-weight:600}
.tt-save{margin-inline-start:auto}
.tt-cols{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.tt-col{border:1px solid var(--a2-border,#e3e6ea);border-radius:12px;background:var(--a2-surface,#fff);display:flex;flex-direction:column;min-height:420px}
.tt-head{display:flex;align-items:center;gap:10px;padding:10px 12px;border-bottom:1px solid var(--a2-border,#eef0f2);font-weight:600;font-size:13px}
.tt-search{flex:1;padding:7px 10px;border:1px solid var(--a2-border,#e3e6ea);border-radius:8px;font-size:13px;box-sizing:border-box}
.tt-list{padding:8px;overflow:auto;max-height:60vh;display:flex;flex-direction:column;gap:4px}
.tt-item{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid transparent;border-radius:8px;cursor:pointer;font-size:13px;user-select:none}
.tt-item:hover{background:#f5f7ff;border-color:#e7ecff}
.tt-item .tt-x{margin-inline-start:auto;color:#adb5bd;font-size:16px;line-height:1}
.tt-item.is-picked{opacity:.45;cursor:default;background:#f1f3f5}
.tt-item.is-picked:hover{background:#f1f3f5;border-color:transparent}
.tt-item.is-picked .tt-tick{color:#2f9e44;font-weight:700}
.tt-empty{color:#adb5bd;padding:16px;text-align:center;font-size:13px}
</style>

<script>
(function () {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const root = document.getElementById('tt');
    const ALL = @json(collect($ttAll)->values());
    let selected = new Set(@json(array_map('intval', $ttSelected instanceof \Illuminate\Support\Collection ? $ttSelected->all() : $ttSelected)));
    let saved = new Set(selected);              // baseline to detect unsaved changes
    const byId = Object.fromEntries(ALL.map(x => [x.id, x]));

    const source = document.getElementById('tt-source');
    const target = document.getElementById('tt-target');
    const search = document.getElementById('tt-search');

    function isDirty() {
        if (selected.size !== saved.size) return true;
        for (const id of selected) if (!saved.has(id)) return true;
        return false;
    }
    function refreshBar() {
        document.getElementById('tt-picked').textContent = selected.size;
        document.getElementById('tt-dirty').hidden = !isDirty();
    }
    function renderSource() {
        const q = (search.value || '').trim();
        source.innerHTML = '';
        const rows = ALL.filter(x => q === '' || x.name.includes(q));
        if (!rows.length) { source.innerHTML = '<div class="tt-empty">' + @json(__('لا نتائج')) + '</div>'; return; }
        rows.forEach(x => {
            const el = document.createElement('div');
            const picked = selected.has(x.id);
            el.className = 'tt-item' + (picked ? ' is-picked' : '');
            el.innerHTML = (picked ? '<span class="tt-tick">✓</span>' : '') + '<span class="tt-name"></span>';
            el.querySelector('.tt-name').textContent = x.name;
            if (!picked) el.addEventListener('click', () => { selected.add(x.id); renderAll(); });
            source.appendChild(el);
        });
    }
    function renderTarget() {
        target.innerHTML = '';
        if (!selected.size) { target.innerHTML = '<div class="tt-empty">' + @json(__('اضغط عنصرًا من اليمين لإضافته')) + '</div>'; return; }
        [...selected].forEach(id => {
            const x = byId[id]; if (!x) return;
            const el = document.createElement('div');
            el.className = 'tt-item';
            el.innerHTML = '<span class="tt-name"></span><span class="tt-x">×</span>';
            el.querySelector('.tt-name').textContent = x.name;
            el.addEventListener('click', () => { selected.delete(id); renderAll(); });
            target.appendChild(el);
        });
    }
    function renderAll() { renderSource(); renderTarget(); refreshBar(); }

    search.addEventListener('input', renderSource);

    window.ttSave = async function () {
        const btn = document.getElementById('tt-save');
        btn.disabled = true; btn.textContent = @json(__('جارٍ الحفظ…'));
        const body = Object.assign({}, JSON.parse(root.dataset.extra || '{}'));
        body[root.dataset.idsKey] = [...selected];
        try {
            const res = await fetch(root.dataset.saveUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(body),
            });
            const out = await res.json().catch(() => ({}));
            if (out.ok) { saved = new Set(selected); refreshBar(); btn.textContent = @json(__('تم الحفظ ✓')); }
            else { btn.textContent = @json(__('تعذّر الحفظ')); }
        } catch (e) { btn.textContent = @json(__('تعذّر الحفظ')); }
        setTimeout(() => { btn.disabled = false; btn.textContent = @json(__('حفظ')); }, 1200);
    };

    renderAll();
})();
</script>
