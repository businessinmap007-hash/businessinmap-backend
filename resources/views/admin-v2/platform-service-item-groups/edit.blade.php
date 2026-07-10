@extends('admin-v2.layouts.master')

@section('title', 'Edit Item Group')
@section('body_class', 'admin-v2 admin-v2-platform-service-item-groups-edit')

@section('content')
<div class="a2-page a2-page-narrow">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل فرع</h1>
            <div class="a2-page-subtitle">
                {{ $row->name_ar ?: ($row->name_en ?: $row->key) }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.service-branches.index') }}" class="a2-btn a2-btn-ghost">لوحة التنظيم</a>
            <a href="{{ route('admin.platform-service-item-groups.index', ['service_id' => $row->platform_service_id]) }}" class="a2-btn a2-btn-ghost">رجوع</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.platform-service-item-groups.update', $row) }}">
        @csrf
        @method('PUT')

        @include('admin-v2.platform-service-item-groups._form', [
            'row' => $row,
            'services' => $services,
        ])
    </form>

    <div id="a2gFlash" class="a2-alert" style="display:none;"></div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">عناصر هذا الفرع (<span id="a2gMemberCount">0</span>)</div>
                <div class="a2-card-sub">أنواع العناصر المتبوّبة داخل هذا الفرع. الإزالة لا تحذف النوع نفسه.</div>
            </div>
        </div>
        <div id="a2gMembers"></div>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">أضف عناصر لهذا الفرع</div>
                <div class="a2-card-sub">اختر من أنواع الفروع الأخرى (النوع ممكن يكون في أكتر من فرع).</div>
            </div>
            <div style="position:relative;">
                <i class="ti ti-search" style="position:absolute; right:10px; top:9px; opacity:.5;"></i>
                <input type="text" id="a2gSearch" class="a2-input" placeholder="ابحث…" style="width:200px; padding-right:30px;" autocomplete="off">
            </div>
        </div>
        <div id="a2gAvailable"></div>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">أضف عنصر جديد</div>
                <div class="a2-card-sub">أنشئ نوع عنصر جديد وأضفه لهذا الفرع مباشرة.</div>
            </div>
        </div>
        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label">الخدمة</label>
                <select id="a2gNewService" class="a2-select">
                    @foreach(($services ?? []) as $s)
                        <option value="{{ $s->id }}">{{ $s->name_ar ?: ($s->name_en ?: $s->key) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="a2-form-group">
                <label class="a2-label">Key</label>
                <input id="a2gNewKey" class="a2-input" dir="ltr" placeholder="single_room">
            </div>
            <div class="a2-form-group">
                <label class="a2-label">الاسم العربي</label>
                <input id="a2gNewNameAr" class="a2-input" placeholder="غرفة فردية">
            </div>
            <div class="a2-form-group">
                <label class="a2-label">الاسم الإنجليزي</label>
                <input id="a2gNewNameEn" class="a2-input" dir="ltr" placeholder="Single Room">
            </div>
        </div>
        <div style="margin-top:10px;">
            <button type="button" id="a2gCreate" class="a2-btn a2-btn-primary"><i class="ti ti-plus"></i> إنشاء وإضافة</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const groupId = @json((int) $row->id);
    const URLS = {
        attach: @json(route('admin.platform-service-item-groups.types.attach', $row, false)),
        detach: @json(route('admin.platform-service-item-groups.types.detach', $row, false)),
        create: @json(route('admin.platform-service-item-groups.types.create', $row, false)),
    };

    let types = @json($allTypes).map(t => ({ id: Number(t.id), key: t.key, name: t.name, service_id: Number(t.service_id), service_name: t.service_name, is_active: !!t.is_active, groupIds: (t.group_ids || []).map(Number) }));
    const branches = @json($branches).map(b => ({ id: Number(b.id), name: b.name }));

    const membersEl = document.getElementById('a2gMembers');
    const availEl = document.getElementById('a2gAvailable');
    const countEl = document.getElementById('a2gMemberCount');
    const flash = document.getElementById('a2gFlash');
    const searchEl = document.getElementById('a2gSearch');

    const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    function notify(msg, ok) {
        flash.textContent = msg;
        flash.className = 'a2-alert ' + (ok ? 'a2-alert-success' : 'a2-alert-danger');
        flash.style.display = 'block';
        clearTimeout(notify._t);
        notify._t = setTimeout(() => { flash.style.display = 'none'; }, 3000);
    }

    async function api(url, body) {
        const res = await fetch(url, {
            method: 'POST',
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

    function typeMeta(t) {
        return '<span dir="ltr" style="font-size:11px; color:var(--a2-muted,#6b7280);">' + esc(t.key) + '</span>' +
            (t.service_name ? ' <span class="a2-pill a2-pill-sub">' + esc(t.service_name) + '</span>' : '') +
            (t.is_active ? '' : ' <span class="a2-pill a2-pill-inactive">غير مفعّل</span>');
    }

    function rowHtml(t, actionBtn) {
        return '<div style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:8px 4px; border-bottom:1px solid var(--a2-border,#eee);">' +
            '<span style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;"><span style="font-weight:500;">' + esc(t.name) + '</span>' + typeMeta(t) + '</span>' +
            actionBtn + '</div>';
    }

    function renderMembers() {
        const members = types.filter(t => t.groupIds.includes(groupId));
        countEl.textContent = members.length;
        membersEl.innerHTML = members.length
            ? members.map(t => rowHtml(t, '<button type="button" class="a2-btn a2-btn-ghost a2-btn-sm a2g-remove" data-id="' + t.id + '"><i class="ti ti-x"></i> إزالة</button>')).join('')
            : '<div class="a2-hint" style="padding:10px 4px;">لا يوجد عناصر في هذا الفرع بعد.</div>';
    }

    function renderAvailable() {
        const q = searchEl.value.trim().toLowerCase();
        const match = t => !q || (t.key + ' ' + t.name).toLowerCase().indexOf(q) !== -1;
        const available = types.filter(t => !t.groupIds.includes(groupId) && match(t));

        const sections = [];
        branches.filter(b => b.id !== groupId).forEach(b => {
            const list = available.filter(t => t.groupIds.includes(b.id));
            if (list.length) sections.push({ title: b.name, list });
        });
        const noBranch = available.filter(t => t.groupIds.length === 0);
        if (noBranch.length) sections.push({ title: 'بدون فرع', list: noBranch });

        if (!sections.length) {
            availEl.innerHTML = '<div class="a2-hint" style="padding:10px 4px;">' + (q ? 'لا نتائج مطابقة.' : 'كل الأنواع مضافة بالفعل.') + '</div>';
            return;
        }

        availEl.innerHTML = sections.map(sec =>
            '<div style="margin-bottom:10px;">' +
            '<div class="a2-section" style="font-weight:700; padding:6px 4px; color:var(--a2-muted,#6b7280);">' + esc(sec.title) + ' <span style="font-weight:400;">(' + sec.list.length + ')</span></div>' +
            sec.list.map(t => rowHtml(t, '<button type="button" class="a2-btn a2-btn-ghost a2-btn-sm a2g-add" data-id="' + t.id + '"><i class="ti ti-plus"></i> إضافة</button>')).join('') +
            '</div>'
        ).join('');
    }

    function render() { renderMembers(); renderAvailable(); }

    membersEl.addEventListener('click', async (e) => {
        const btn = e.target.closest('.a2g-remove');
        if (!btn) return;
        const id = Number(btn.dataset.id);
        try {
            await api(URLS.detach, { item_type_id: id });
            const t = types.find(x => x.id === id);
            if (t) t.groupIds = t.groupIds.filter(g => g !== groupId);
            render(); notify('تمت الإزالة.', true);
        } catch (err) { notify(err.message, false); }
    });

    availEl.addEventListener('click', async (e) => {
        const btn = e.target.closest('.a2g-add');
        if (!btn) return;
        const id = Number(btn.dataset.id);
        try {
            await api(URLS.attach, { item_type_id: id });
            const t = types.find(x => x.id === id);
            if (t && !t.groupIds.includes(groupId)) t.groupIds.push(groupId);
            render(); notify('تمت الإضافة.', true);
        } catch (err) { notify(err.message, false); }
    });

    searchEl.addEventListener('input', renderAvailable);

    document.getElementById('a2gCreate').addEventListener('click', async () => {
        const payload = {
            platform_service_id: Number(document.getElementById('a2gNewService').value || 0),
            key: (document.getElementById('a2gNewKey').value || '').trim(),
            name_ar: (document.getElementById('a2gNewNameAr').value || '').trim(),
            name_en: (document.getElementById('a2gNewNameEn').value || '').trim(),
        };
        if (!payload.platform_service_id || !payload.key || !payload.name_ar) {
            notify('اختر الخدمة واكتب المفتاح والاسم العربي.', false);
            return;
        }
        try {
            const d = await api(URLS.create, payload);
            types.push({ id: Number(d.type.id), key: d.type.key, name: d.type.name, service_id: Number(d.type.service_id), service_name: d.type.service_name, is_active: !!d.type.is_active, groupIds: (d.type.group_ids || []).map(Number) });
            document.getElementById('a2gNewKey').value = '';
            document.getElementById('a2gNewNameAr').value = '';
            document.getElementById('a2gNewNameEn').value = '';
            render(); notify('تم إنشاء النوع وإضافته.', true);
        } catch (err) { notify(err.message, false); }
    });

    render();
})();
</script>
@endpush
