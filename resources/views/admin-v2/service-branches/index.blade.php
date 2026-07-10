@extends('admin-v2.layouts.master')

@section('title', 'Service Branch Board')
@section('body_class', 'admin-v2 admin-v2-service-branches-index')

@section('content')
@php
    $serviceIdVal = (int) ($serviceId ?? 0);
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تنظيم فروع الخدمة</h1>
            <div class="a2-page-subtitle">
                اختر الخدمة، ثم الفروع اللي تشتغل عليها، وعلّم كل نوع في الفروع اللي يتبعها. النوع ممكن يكون في أكتر من فرع (زي «غرفة» تحت فنادق ووحدات سكنية).
            </div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.platform-service-item-groups.index') }}" class="a2-btn a2-btn-ghost">إدارة الفروع</a>
            <a href="{{ route('admin.platform-service-item-types.index', ['service_id' => $serviceIdVal]) }}" class="a2-btn a2-btn-ghost">أنواع العناصر</a>
        </div>
    </div>

    <div id="a2sbFlash" class="a2-alert" style="display:none;"></div>

    <div class="a2-card a2-card--section">
        <form method="GET" action="{{ route('admin.service-branches.index') }}" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <label class="a2-label" for="service_id" style="margin:0;">الخدمة</label>
            <select id="service_id" name="service_id" class="a2-select" style="width:auto; min-width:220px;" onchange="this.form.submit()">
                @foreach($services as $s)
                    <option value="{{ $s->id }}" @selected($serviceIdVal === (int) $s->id)>
                        {{ $s->name_ar ?: ($s->name_en ?: $s->key) }}@if(! $s->is_active) — (غير مفعّلة)@endif
                    </option>
                @endforeach
            </select>
            <span class="a2-hint" style="margin:0;">خدمة واحدة في المرة.</span>
        </form>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">اختر الفروع للعرض في المصفوفة</div>
                <div class="a2-card-sub">اضغط الفرع لإظهار/إخفاء عموده. الرقم = عدد أنواع هذه الخدمة داخله.</div>
            </div>
            <button type="button" id="a2sbAddBranch" class="a2-btn a2-btn-primary a2-btn-sm"><i class="ti ti-plus"></i> فرع</button>
        </div>
        <div id="a2sbChips" style="display:flex; flex-wrap:wrap; gap:8px; padding-top:6px;"></div>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">المصفوفة — علّم فروع كل نوع</div>
                <div class="a2-card-sub">النوع ممكن يتبع أكتر من فرع. لو متبوّب في فرع غير معروض بيظهر تحته «أيضًا في: …».</div>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <span id="a2sbSaveState" class="a2-hint" style="margin:0;">محفوظ تلقائيًا</span>
                <button type="button" id="a2sbSaveAll" class="a2-btn a2-btn-primary a2-btn-sm">
                    <i class="ti ti-device-floppy"></i> حفظ الكل
                </button>
                <div style="position:relative;">
                    <i class="ti ti-search" style="position:absolute; right:10px; top:9px; opacity:.5;"></i>
                    <input type="text" id="a2sbSearch" class="a2-input" placeholder="ابحث عن نوع…" style="width:220px; padding-right:30px;" autocomplete="off">
                </div>
            </div>
        </div>

        <div id="a2sbMatrixWrap" style="overflow-x:auto; border:1px solid var(--a2-border, #e5e7eb); border-radius:8px;">
            <table id="a2sbMatrix" style="border-collapse:collapse; width:100%; font-size:13px; min-width:max-content;"></table>
        </div>
        <div id="a2sbNoTypes" class="a2-hint" style="display:none; padding:12px 4px;"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const serviceId = @json($serviceIdVal);
    // Relative URLs (third arg false) so the auto-save fetch() stays same-origin
    // regardless of APP_URL — an absolute URL breaks AJAX cross-origin when the
    // panel is browsed on a different host/port than APP_URL.
    const URLS = {
        toggle: @json(route('admin.service-branches.toggle', [], false)),
        save: @json(route('admin.service-branches.save', [], false)),
        store: @json(route('admin.service-branches.branches.store', [], false)),
        renameTpl: @json(route('admin.service-branches.branches.rename', ['platformServiceItemGroup' => '__ID__'], false)),
        destroyTpl: @json(route('admin.service-branches.branches.destroy', ['platformServiceItemGroup' => '__ID__'], false)),
    };

    let branches = @json($branches).map(b => ({ id: Number(b.id), name: b.name, count: Number(b.count_here || 0) }));
    let types = @json($types).map(t => ({ id: Number(t.id), key: t.key, name: t.name, groupIds: (t.group_ids || []).map(Number), is_active: !!t.is_active }));

    const selected = new Set(branches.filter(b => b.count > 0).map(b => b.id));
    if (selected.size === 0) branches.slice(0, 4).forEach(b => selected.add(b.id));

    const chipsEl = document.getElementById('a2sbChips');
    const table = document.getElementById('a2sbMatrix');
    const flash = document.getElementById('a2sbFlash');
    const noTypes = document.getElementById('a2sbNoTypes');
    const searchEl = document.getElementById('a2sbSearch');

    const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const byId = id => branches.find(b => b.id === id);

    function notify(msg, ok) {
        flash.textContent = msg;
        flash.className = 'a2-alert ' + (ok ? 'a2-alert-success' : 'a2-alert-danger');
        flash.style.display = 'block';
        clearTimeout(notify._t);
        notify._t = setTimeout(() => { flash.style.display = 'none'; }, 3000);
    }

    const saveStateEl = document.getElementById('a2sbSaveState');
    const saveAllBtn = document.getElementById('a2sbSaveAll');

    // Reflect auto-save health next to the save button. 'dirty' means an auto-save
    // failed, so "حفظ الكل" becomes the recovery action.
    function setSaveState(kind) {
        if (!saveStateEl) return;
        const map = {
            saved: ['محفوظ تلقائيًا', 'var(--a2-muted, #6b7280)'],
            saving: ['جارٍ الحفظ…', 'var(--a2-muted, #6b7280)'],
            confirmed: ['تم حفظ الكل ✓', 'var(--a2-success, #16a34a)'],
            dirty: ['تغييرات لم تُحفظ — اضغط «حفظ الكل»', 'var(--a2-danger, #dc2626)'],
        };
        const [text, color] = map[kind] || map.saved;
        saveStateEl.textContent = text;
        saveStateEl.style.color = color;
    }

    async function api(url, body, method) {
        const res = await fetch(url, {
            method: method || 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
            body: body ? JSON.stringify(body) : null,
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || data.ok !== true) {
            const m = data && (data.message || (data.errors && Object.values(data.errors)[0][0]));
            throw new Error(m || 'تعذر تنفيذ العملية.');
        }
        return data;
    }

    function recount() {
        branches.forEach(b => { b.count = types.filter(t => t.groupIds.includes(b.id)).length; });
    }

    function renderChips() {
        if (!branches.length) {
            chipsEl.innerHTML = '<span class="a2-hint">لا توجد فروع بعد — أضف فرع من زر «+ فرع».</span>';
            return;
        }
        chipsEl.innerHTML = branches.map(b => {
            const on = selected.has(b.id);
            return '<span class="a2sb-chip" data-branch="' + b.id + '" ' +
                'style="display:inline-flex; align-items:center; gap:8px; cursor:pointer; border-radius:18px; padding:5px 12px; border:1px solid ' +
                (on ? 'var(--a2-primary, #2563eb)' : 'var(--a2-border, #e5e7eb)') + '; ' +
                'background:' + (on ? 'var(--a2-primary-soft, rgba(37,99,235,.10))' : 'transparent') + ';">' +
                '<i class="ti ' + (on ? 'ti-eye' : 'ti-eye-off') + '" style="opacity:.7;"></i>' +
                '<span class="a2sb-chip-name">' + esc(b.name) + '</span>' +
                '<span style="font-weight:700;">' + b.count + '</span>' +
                '<button type="button" class="a2sb-rename" title="إعادة تسمية" style="border:0;background:none;cursor:pointer;padding:0;opacity:.6;"><i class="ti ti-pencil"></i></button>' +
                '<button type="button" class="a2sb-del" title="حذف الفرع" style="border:0;background:none;cursor:pointer;padding:0;opacity:.6;"><i class="ti ti-x"></i></button>' +
                '</span>';
        }).join('');
    }

    function renderMatrix() {
        const q = searchEl.value.trim().toLowerCase();
        const cols = branches.filter(b => selected.has(b.id));
        const rows = types.filter(t => !q || (t.key + ' ' + t.name).toLowerCase().indexOf(q) !== -1);

        if (!types.length) {
            table.innerHTML = '';
            noTypes.textContent = 'لا توجد أنواع عناصر لهذه الخدمة. أضفها من «أنواع العناصر».';
            noTypes.style.display = 'block';
            return;
        }
        noTypes.style.display = rows.length ? 'none' : 'block';
        if (!rows.length) noTypes.textContent = 'لا توجد نتائج مطابقة للبحث.';

        const sticky = 'position:sticky; right:0; background:var(--a2-surface, #fff); z-index:1;';
        const head = '<thead><tr>' +
            '<th style="text-align:right; padding:8px 10px; border-bottom:1px solid var(--a2-border,#e5e7eb); ' + sticky + '">نوع العنصر</th>' +
            cols.map(b =>
                '<th style="padding:6px 10px; border-bottom:1px solid var(--a2-border,#e5e7eb); white-space:nowrap;">' +
                '<div>' + esc(b.name) + '</div>' +
                '<div style="font-size:11px; color:var(--a2-primary,#2563eb);">' + b.count + ' نوع</div>' +
                '</th>'
            ).join('') + '</tr></thead>';

        const body = '<tbody>' + rows.map(t => {
            const hidden = t.groupIds.filter(id => !selected.has(id)).map(id => (byId(id) || {}).name).filter(Boolean);
            const hiddenTag = hidden.length ? '<div style="font-size:11px; color:#b45309;">أيضًا في: ' + esc(hidden.join('، ')) + '</div>' : '';
            const cells = cols.map(b =>
                '<td style="text-align:center; padding:8px 10px; border-bottom:1px solid var(--a2-border,#eee);">' +
                '<input type="checkbox" data-type="' + t.id + '" data-b="' + b.id + '" ' + (t.groupIds.includes(b.id) ? 'checked' : '') + ' style="width:16px;height:16px;cursor:pointer;"></td>'
            ).join('');
            return '<tr>' +
                '<td style="padding:8px 10px; border-bottom:1px solid var(--a2-border,#eee); ' + sticky + '">' +
                '<div style="font-weight:500;">' + esc(t.name) + (t.is_active ? '' : ' <span class="a2-pill a2-pill-inactive">غير مفعّل</span>') + '</div>' +
                '<div dir="ltr" style="font-size:11px; color:var(--a2-muted,#6b7280);">' + esc(t.key) + '</div>' + hiddenTag +
                '</td>' + cells + '</tr>';
        }).join('') + '</tbody>';

        table.innerHTML = head + body;
    }

    function render() { recount(); renderChips(); renderMatrix(); }

    chipsEl.addEventListener('click', async (e) => {
        const chip = e.target.closest('.a2sb-chip');
        if (!chip) return;
        const id = Number(chip.dataset.branch);

        if (e.target.closest('.a2sb-rename')) {
            const b = byId(id); const name = (window.prompt('اسم الفرع (بالعربي)', b.name) || '').trim();
            if (!name || name === b.name) return;
            try { const d = await api(URLS.renameTpl.replace('__ID__', id), { name_ar: name }); b.name = d.name; render(); notify('تم تحديث الاسم.', true); }
            catch (err) { notify(err.message, false); }
            return;
        }
        if (e.target.closest('.a2sb-del')) {
            if (!window.confirm('حذف الفرع؟ الأنواع اللي فيه هتفقد الفرع ده.')) return;
            try {
                await api(URLS.destroyTpl.replace('__ID__', id), null, 'DELETE');
                types.forEach(t => { t.groupIds = t.groupIds.filter(g => g !== id); });
                branches = branches.filter(b => b.id !== id); selected.delete(id);
                render(); notify('تم حذف الفرع.', true);
            } catch (err) { notify(err.message, false); }
            return;
        }
        if (selected.has(id)) selected.delete(id); else selected.add(id);
        render();
    });

    table.addEventListener('change', async (e) => {
        const box = e.target.closest('input[type=checkbox][data-type]');
        if (!box) return;
        const typeId = Number(box.dataset.type);
        const groupId = Number(box.dataset.b);
        const attached = box.checked;
        const t = types.find(x => x.id === typeId);
        // Optimistically reflect the change locally so "حفظ الكل" can recover it
        // even if this auto-save call fails.
        t.groupIds = attached ? Array.from(new Set([...t.groupIds, groupId])) : t.groupIds.filter(g => g !== groupId);
        setSaveState('saving');
        try {
            await api(URLS.toggle, { service_id: serviceId, item_type_id: typeId, group_id: groupId, attached });
            recount(); renderChips();
            renderMatrix();
            notify('تم الحفظ.', true);
            setSaveState('saved');
        } catch (err) {
            // Keep the optimistic local state; the row stays checked and the user
            // can retry the whole board with "حفظ الكل".
            notify(err.message, false);
            setSaveState('dirty');
        }
    });

    saveAllBtn?.addEventListener('click', async () => {
        saveAllBtn.disabled = true;
        setSaveState('saving');
        try {
            const payload = { service_id: serviceId, types: types.map(t => ({ item_type_id: t.id, group_ids: t.groupIds })) };
            const d = await api(URLS.save, payload);
            recount(); renderChips(); renderMatrix();
            notify('تم حفظ كل التغييرات (' + (d.saved ?? types.length) + ' نوع).', true);
            setSaveState('confirmed');
        } catch (err) {
            notify(err.message, false);
            setSaveState('dirty');
        } finally {
            saveAllBtn.disabled = false;
        }
    });

    document.getElementById('a2sbAddBranch').addEventListener('click', async () => {
        const name = (window.prompt('اسم الفرع (بالعربي)') || '').trim();
        if (!name) return;
        try {
            const d = await api(URLS.store, { name_ar: name });
            branches.push({ id: Number(d.id), name: d.name, count: 0 });
            selected.add(Number(d.id));
            render(); notify('تم إنشاء الفرع.', true);
        } catch (err) { notify(err.message, false); }
    });

    searchEl.addEventListener('input', renderMatrix);

    render();
})();
</script>
@endpush
