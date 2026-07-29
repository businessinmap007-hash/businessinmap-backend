@extends('admin-v2.layouts.master')

@section('title', 'Service Branches')
@section('body_class', 'admin-v2 admin-v2-service-branches-service')

@section('content')
@php $serviceIdVal = (int) ($serviceId ?? 0); @endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <div class="a2sb-crumb">
                <a href="{{ route('admin.service-branches.index') }}">{{ __('الخدمات') }}</a>
                <span aria-hidden="true">›</span>
                <span>{{ $service->name_ar ?: ($service->name_en ?: $service->key) }}</span>
            </div>
            <h1 class="a2-page-title">{{ __('فروع خدمة') }} «{{ $service->name_ar ?: ($service->name_en ?: $service->key) }}»</h1>
            <div class="a2-page-subtitle">
                {{ __('فروع هذه الخدمة فقط. علّم ما يخص كل فرع من الأنواع — النوع يمكن أن يكون في أكثر من فرع.') }}
            </div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.service-branches.index') }}" class="a2-btn a2-btn-ghost">{{ __('← الخدمات') }}</a>
            <a href="{{ route('admin.platform-service-item-types.index', ['service_id' => $serviceIdVal]) }}" class="a2-btn a2-btn-ghost">{{ __('أنواع العناصر') }}</a>
            <button type="button" id="a2sbAddBranch" class="a2-btn a2-btn-primary">{{ __('+ فرع جديد') }}</button>
        </div>
    </div>

    <div id="a2sbFlash" class="a2-alert" style="display:none;"></div>

    <div id="a2sbBranches" class="a2sb-branches"></div>
    <div id="a2sbEmpty" class="a2-card a2-card--section" style="display:none;text-align:center;color:#868e96;">
        {{ __('لا توجد فروع لهذه الخدمة بعد. اضغط «+ فرع جديد».') }}
    </div>
</div>

<style>
.a2sb-crumb{font-size:12px;color:#868e96;display:flex;gap:6px;align-items:center;margin-bottom:4px}
.a2sb-crumb a{color:var(--a2-primary,#3b5bdb);text-decoration:none}
.a2sb-branches{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px}
.a2sb-branch{border:1px solid var(--a2-border,#e3e6ea);border-radius:12px;background:var(--a2-surface,#fff);padding:14px;display:flex;flex-direction:column;gap:10px}
.a2sb-b-head{display:flex;align-items:center;gap:8px}
.a2sb-b-name{font-size:15px;font-weight:600;flex:1}
.a2sb-b-count{font-size:12px;color:#868e96;background:#f1f3f5;border-radius:999px;padding:2px 8px}
.a2sb-icon{border:none;background:transparent;cursor:pointer;color:#868e96;font-size:14px;padding:3px 5px;border-radius:6px}
.a2sb-icon:hover{background:#f1f3f5;color:#495057}
.a2sb-chips{display:flex;flex-wrap:wrap;gap:6px;min-height:8px}
.a2sb-chip{display:inline-flex;align-items:center;gap:5px;font-size:12.5px;background:#eef2ff;color:#3b4cc0;border-radius:999px;padding:3px 6px 3px 10px}
.a2sb-chip.is-off{background:#f1f3f5;color:#868e96}
.a2sb-chip button{border:none;background:transparent;cursor:pointer;color:inherit;opacity:.7;font-size:13px;line-height:1;padding:0 2px}
.a2sb-chip button:hover{opacity:1}
.a2sb-empty-chips{font-size:12px;color:#adb5bd}
.a2sb-add{position:relative}
.a2sb-add-btn{font-size:12.5px;color:var(--a2-primary,#3b5bdb);background:transparent;border:1px dashed var(--a2-border,#ccd0d5);border-radius:8px;padding:5px 10px;cursor:pointer;width:100%}
.a2sb-add-btn:hover{border-color:var(--a2-primary,#3b5bdb)}
.a2sb-pop{position:absolute;z-index:20;top:calc(100% + 4px);inset-inline-start:0;width:100%;max-height:240px;overflow:auto;background:#fff;border:1px solid var(--a2-border,#e3e6ea);border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,.1);padding:6px}
.a2sb-pop input{width:100%;box-sizing:border-box;padding:6px 8px;border:1px solid var(--a2-border,#e3e6ea);border-radius:7px;margin-bottom:6px;font-size:13px}
.a2sb-pop-item{display:block;width:100%;text-align:start;background:transparent;border:none;padding:6px 8px;border-radius:6px;font-size:13px;cursor:pointer;color:inherit}
.a2sb-pop-item:hover{background:#eef2ff}
.a2sb-pop-empty{font-size:12px;color:#adb5bd;padding:6px 8px}
</style>

<script>
(function () {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const serviceId = @json($serviceIdVal);
    const URLS = {
        toggle: @json(route('admin.service-branches.toggle', [], false)),
        store: @json(route('admin.service-branches.branches.store', [], false)),
        renameTpl: @json(route('admin.service-branches.branches.rename', ['platformServiceItemGroup' => '__ID__'], false)),
        destroyTpl: @json(route('admin.service-branches.branches.destroy', ['platformServiceItemGroup' => '__ID__'], false)),
    };

    let branches = @json($branches).map(b => ({ id: Number(b.id), name: b.name, typeIds: (b.type_ids || []).map(Number) }));
    const types = @json($types).map(t => ({ id: Number(t.id), name: t.name, is_active: !!t.is_active }));
    const typeById = id => types.find(t => t.id === id);

    const wrap = document.getElementById('a2sbBranches');
    const emptyEl = document.getElementById('a2sbEmpty');
    const flash = document.getElementById('a2sbFlash');
    const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    function notify(msg, ok) {
        flash.textContent = msg;
        flash.className = 'a2-alert ' + (ok ? 'a2-alert-success' : 'a2-alert-danger');
        flash.style.display = 'block';
        clearTimeout(notify._t);
        notify._t = setTimeout(() => { flash.style.display = 'none'; }, 3000);
    }

    async function api(url, body, method) {
        const res = await fetch(url, {
            method: method || 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
            body: body ? JSON.stringify(body) : null,
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || data.ok !== true) {
            const msg = (data && (data.message || (data.errors && Object.values(data.errors)[0][0]))) || 'تعذّر تنفيذ العملية';
            throw new Error(msg);
        }
        return data;
    }

    function render() {
        emptyEl.style.display = branches.length ? 'none' : 'block';
        wrap.innerHTML = branches.map(b => {
            const chips = b.typeIds.length
                ? b.typeIds.map(id => {
                    const t = typeById(id);
                    if (!t) return '';
                    return '<span class="a2sb-chip ' + (t.is_active ? '' : 'is-off') + '">' + esc(t.name)
                        + '<button type="button" data-detach="' + b.id + ':' + id + '" title="إزالة">×</button></span>';
                }).join('')
                : '<span class="a2sb-empty-chips">لا أنواع بعد</span>';
            return '<div class="a2sb-branch" data-branch="' + b.id + '">'
                + '<div class="a2sb-b-head">'
                +   '<span class="a2sb-b-name">' + esc(b.name) + '</span>'
                +   '<span class="a2sb-b-count">' + b.typeIds.length + ' ' + 'نوع</span>'
                +   '<button type="button" class="a2sb-icon" data-rename="' + b.id + '" title="إعادة تسمية">✎</button>'
                +   '<button type="button" class="a2sb-icon" data-destroy="' + b.id + '" title="حذف الفرع">🗑</button>'
                + '</div>'
                + '<div class="a2sb-chips">' + chips + '</div>'
                + '<div class="a2sb-add"><button type="button" class="a2sb-add-btn" data-add="' + b.id + '">+ أضف نوع</button></div>'
                + '</div>';
        }).join('');
    }

    async function detach(branchId, typeId) {
        try {
            await api(URLS.toggle, { service_id: serviceId, item_type_id: typeId, group_id: branchId, attached: false });
            const b = branches.find(x => x.id === branchId);
            if (b) b.typeIds = b.typeIds.filter(id => id !== typeId);
            render();
        } catch (e) { notify(e.message, false); }
    }

    async function attach(branchId, typeId) {
        try {
            await api(URLS.toggle, { service_id: serviceId, item_type_id: typeId, group_id: branchId, attached: true });
            const b = branches.find(x => x.id === branchId);
            if (b && !b.typeIds.includes(typeId)) b.typeIds.push(typeId);
            render();
        } catch (e) { notify(e.message, false); }
    }

    function openAdd(branchId, anchor) {
        document.querySelectorAll('.a2sb-pop').forEach(p => p.remove());
        const b = branches.find(x => x.id === branchId);
        if (!b) return;
        const available = types.filter(t => !b.typeIds.includes(t.id));
        const pop = document.createElement('div');
        pop.className = 'a2sb-pop';
        const items = available.length
            ? available.map(t => '<button type="button" class="a2sb-pop-item" data-pick="' + t.id + '">' + esc(t.name) + (t.is_active ? '' : ' (غير مفعّل)') + '</button>').join('')
            : '<div class="a2sb-pop-empty">كل الأنواع مضافة بالفعل.</div>';
        pop.innerHTML = '<input type="text" placeholder="بحث…" />' + '<div class="a2sb-pop-list">' + items + '</div>';
        anchor.parentElement.appendChild(pop);
        const input = pop.querySelector('input');
        const list = pop.querySelector('.a2sb-pop-list');
        input.focus();
        input.addEventListener('input', () => {
            const q = input.value.trim().toLowerCase();
            list.querySelectorAll('.a2sb-pop-item').forEach(el => {
                el.style.display = el.textContent.toLowerCase().indexOf(q) !== -1 ? 'block' : 'none';
            });
        });
        list.addEventListener('click', e => {
            const pick = e.target.closest('[data-pick]');
            if (!pick) return;
            pop.remove();
            attach(branchId, Number(pick.getAttribute('data-pick')));
        });
    }

    document.addEventListener('click', e => {
        const det = e.target.closest('[data-detach]');
        if (det) { const [b, t] = det.getAttribute('data-detach').split(':').map(Number); detach(b, t); return; }

        const add = e.target.closest('[data-add]');
        if (add) { e.stopPropagation(); openAdd(Number(add.getAttribute('data-add')), add); return; }

        const ren = e.target.closest('[data-rename]');
        if (ren) { renameBranch(Number(ren.getAttribute('data-rename'))); return; }

        const del = e.target.closest('[data-destroy]');
        if (del) { destroyBranch(Number(del.getAttribute('data-destroy'))); return; }

        if (!e.target.closest('.a2sb-pop') && !e.target.closest('[data-add]')) {
            document.querySelectorAll('.a2sb-pop').forEach(p => p.remove());
        }
    });

    async function renameBranch(branchId) {
        const b = branches.find(x => x.id === branchId);
        if (!b) return;
        const name = window.prompt('الاسم الجديد للفرع:', b.name);
        if (name === null || name.trim() === '' || name.trim() === b.name) return;
        try {
            const d = await api(URLS.renameTpl.replace('__ID__', branchId), { name_ar: name.trim() });
            b.name = d.name || name.trim();
            render();
            notify('تم تعديل الاسم', true);
        } catch (e) { notify(e.message, false); }
    }

    async function destroyBranch(branchId) {
        const b = branches.find(x => x.id === branchId);
        if (!b) return;
        if (!window.confirm('حذف الفرع «' + b.name + '»؟ الأنواع بداخله لن تُحذف، فقط تخرج من هذا الفرع.')) return;
        try {
            await api(URLS.destroyTpl.replace('__ID__', branchId), null, 'DELETE');
            branches = branches.filter(x => x.id !== branchId);
            render();
            notify('تم حذف الفرع', true);
        } catch (e) { notify(e.message, false); }
    }

    document.getElementById('a2sbAddBranch').addEventListener('click', async () => {
        const name = window.prompt('اسم الفرع الجديد:');
        if (name === null || name.trim() === '') return;
        try {
            const d = await api(URLS.store, { name_ar: name.trim(), service_id: serviceId });
            branches.push({ id: Number(d.id), name: d.name || name.trim(), typeIds: [] });
            render();
            notify('تمت إضافة الفرع', true);
        } catch (e) { notify(e.message, false); }
    });

    render();
})();
</script>
@endsection
