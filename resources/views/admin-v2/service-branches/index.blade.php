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
                اختر الخدمة، ثم وزّع كل نوع عنصر على فرعه من القائمة. الفروع مخزن مشترك — الفرع اللي فيه أنواع من أكتر من خدمة يبقى «عابر».
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.platform-service-item-groups.index') }}" class="a2-btn a2-btn-ghost">إدارة الفروع</a>
            <a href="{{ route('admin.platform-service-item-types.index', ['service_id' => $serviceIdVal]) }}" class="a2-btn a2-btn-ghost">أنواع العناصر</a>
        </div>
    </div>

    <div id="a2sbFlash" class="a2-alert" style="display:none;"></div>

    <div class="a2-card a2-card--section">
        <form method="GET" action="{{ route('admin.service-branches.index') }}" class="a2-form-row" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <label class="a2-label" for="service_id" style="margin:0;">الخدمة</label>
            <select id="service_id" name="service_id" class="a2-select" style="width:auto; min-width:220px;" onchange="this.form.submit()">
                @foreach($services as $s)
                    <option value="{{ $s->id }}" @selected($serviceIdVal === (int) $s->id)>
                        {{ $s->name_ar ?: ($s->name_en ?: $s->key) }}@if(! $s->is_active) — (غير مفعّلة)@endif
                    </option>
                @endforeach
            </select>
            <span class="a2-hint" style="margin:0;">خدمة واحدة في المرة، فزيادة الخدمات مش بتزحّم الشاشة.</span>
        </form>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">فروع الخدمة</div>
                <div class="a2-card-sub">الرقم = عدد أنواع هذه الخدمة داخل الفرع. «عابر» = الفرع مستخدم في خدمات أخرى كمان.</div>
            </div>
            <button type="button" id="a2sbAddBranch" class="a2-btn a2-btn-primary a2-btn-sm">
                <i class="ti ti-plus"></i> فرع
            </button>
        </div>

        <div id="a2sbChips" style="display:flex; flex-wrap:wrap; gap:8px; padding-top:6px;">
            @forelse($branches as $b)
                <span class="a2sb-chip" data-branch="{{ $b['id'] }}"
                      style="display:inline-flex; align-items:center; gap:8px; background:var(--a2-surface, #fff); border:1px solid var(--a2-border, #e5e7eb); border-radius:18px; padding:5px 12px;">
                    <span class="a2sb-chip-name">{{ $b['name'] }}</span>
                    <span class="a2sb-chip-count" style="font-weight:700;">{{ $b['count_here'] }}</span>
                    @if(! empty($b['cross']))
                        <span class="a2-pill a2-pill-sub" title="مستخدم في: {{ implode('، ', $b['cross']) }}">عابر</span>
                    @endif
                    <button type="button" class="a2sb-rename" title="إعادة تسمية" style="border:0; background:none; cursor:pointer; padding:0; opacity:.7;"><i class="ti ti-pencil"></i></button>
                    <button type="button" class="a2sb-del" title="حذف الفرع" style="border:0; background:none; cursor:pointer; padding:0; opacity:.7;"><i class="ti ti-x"></i></button>
                </span>
            @empty
                <span class="a2-hint a2sb-empty">لا توجد فروع بعد — أضف فرع من زر «+ فرع».</span>
            @endforelse
        </div>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">أنواع العناصر — عيّن فرع كل نوع</div>
                <div class="a2-card-sub">كل نوع له فرع واحد فقط. «بدون فرع» = النوع لسه مش متبوّب.</div>
            </div>
            <div style="position:relative;">
                <i class="ti ti-search" style="position:absolute; right:10px; top:9px; opacity:.5;"></i>
                <input type="text" id="a2sbSearch" class="a2-input" placeholder="ابحث عن نوع عنصر…" style="width:240px; padding-right:30px;" autocomplete="off">
            </div>
        </div>

        <div id="a2sbList">
            @forelse($types as $t)
                <div class="a2sb-row" data-text="{{ mb_strtolower($t['key'] . ' ' . $t['name']) }}"
                     style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:9px 4px; border-bottom:1px solid var(--a2-border, #eee);">
                    <span style="display:flex; align-items:center; gap:8px; min-width:0;">
                        <span style="font-weight:500;">{{ $t['name'] }}</span>
                        <span dir="ltr" class="a2-hint" style="margin:0;">{{ $t['key'] }}</span>
                        @if(! $t['is_active'])<span class="a2-pill a2-pill-inactive">غير مفعّل</span>@endif
                    </span>
                    <select class="a2-select a2sb-assign" data-type="{{ $t['id'] }}" data-prev="{{ $t['group_id'] ?? '' }}" style="width:200px;">
                        <option value="">— بدون فرع —</option>
                        @foreach($branches as $b)
                            <option value="{{ $b['id'] }}" @selected(($t['group_id'] ?? null) === $b['id'])>{{ $b['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            @empty
                <div class="a2-hint" style="padding:12px 4px;">لا توجد أنواع عناصر لهذه الخدمة. أضفها من «أنواع العناصر».</div>
            @endforelse
        </div>
        <div id="a2sbNoResults" class="a2-hint" style="display:none; padding:12px 4px;">لا توجد نتائج مطابقة للبحث.</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const serviceId = @json($serviceIdVal);
    const URLS = {
        assign: @json(route('admin.service-branches.assign')),
        store: @json(route('admin.service-branches.branches.store')),
        renameTpl: @json(route('admin.service-branches.branches.rename', ['platformServiceItemGroup' => '__ID__'])),
        destroyTpl: @json(route('admin.service-branches.branches.destroy', ['platformServiceItemGroup' => '__ID__'])),
    };

    const chips = document.getElementById('a2sbChips');
    const list = document.getElementById('a2sbList');
    const flash = document.getElementById('a2sbFlash');

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
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
            },
            body: body ? JSON.stringify(body) : null,
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || data.ok !== true) {
            const m = data && (data.message || (data.errors && Object.values(data.errors)[0][0]));
            throw new Error(m || 'تعذر تنفيذ العملية.');
        }
        return data;
    }

    function updateCounts(counts) {
        counts = counts || {};
        chips.querySelectorAll('.a2sb-chip').forEach(chip => {
            const id = chip.dataset.branch;
            chip.querySelector('.a2sb-chip-count').textContent = counts[id] || 0;
        });
    }

    list.addEventListener('change', async (e) => {
        const sel = e.target.closest('.a2sb-assign');
        if (!sel) return;
        const prev = sel.dataset.prev || '';
        try {
            const data = await api(URLS.assign, {
                service_id: serviceId,
                item_type_id: Number(sel.dataset.type),
                group_id: sel.value ? Number(sel.value) : null,
            });
            sel.dataset.prev = sel.value;
            updateCounts(data.counts);
            notify('تم الحفظ.', true);
        } catch (err) {
            sel.value = prev;
            notify(err.message, false);
        }
    });

    document.getElementById('a2sbAddBranch').addEventListener('click', async () => {
        const name = (window.prompt('اسم الفرع (بالعربي)') || '').trim();
        if (!name) return;
        try {
            const data = await api(URLS.store, { name_ar: name });
            addBranchEverywhere(data.id, data.name);
            notify('تم إنشاء الفرع.', true);
        } catch (err) { notify(err.message, false); }
    });

    chips.addEventListener('click', async (e) => {
        const chip = e.target.closest('.a2sb-chip');
        if (!chip) return;
        const id = chip.dataset.branch;

        if (e.target.closest('.a2sb-rename')) {
            const cur = chip.querySelector('.a2sb-chip-name').textContent.trim();
            const name = (window.prompt('اسم الفرع (بالعربي)', cur) || '').trim();
            if (!name || name === cur) return;
            try {
                const data = await api(URLS.renameTpl.replace('__ID__', id), { name_ar: name });
                chip.querySelector('.a2sb-chip-name').textContent = data.name;
                list.querySelectorAll('.a2sb-assign option[value="' + id + '"]').forEach(o => { o.textContent = data.name; });
                notify('تم تحديث الاسم.', true);
            } catch (err) { notify(err.message, false); }
            return;
        }

        if (e.target.closest('.a2sb-del')) {
            if (!window.confirm('حذف الفرع؟ الأنواع اللي فيه هترجع «بدون فرع».')) return;
            try {
                await api(URLS.destroyTpl.replace('__ID__', id), null, 'DELETE');
                chip.remove();
                list.querySelectorAll('.a2sb-assign').forEach(sel => {
                    const opt = sel.querySelector('option[value="' + id + '"]');
                    if (!opt) return;
                    if (sel.value === id) { sel.value = ''; sel.dataset.prev = ''; }
                    opt.remove();
                });
                notify('تم حذف الفرع.', true);
            } catch (err) { notify(err.message, false); }
        }
    });

    function addBranchEverywhere(id, name) {
        const empty = chips.querySelector('.a2sb-empty');
        if (empty) empty.remove();

        const chip = document.createElement('span');
        chip.className = 'a2sb-chip';
        chip.dataset.branch = id;
        chip.setAttribute('style', 'display:inline-flex; align-items:center; gap:8px; background:var(--a2-surface, #fff); border:1px solid var(--a2-border, #e5e7eb); border-radius:18px; padding:5px 12px;');
        chip.innerHTML =
            '<span class="a2sb-chip-name"></span>' +
            '<span class="a2sb-chip-count" style="font-weight:700;">0</span>' +
            '<button type="button" class="a2sb-rename" title="إعادة تسمية" style="border:0;background:none;cursor:pointer;padding:0;opacity:.7;"><i class="ti ti-pencil"></i></button>' +
            '<button type="button" class="a2sb-del" title="حذف الفرع" style="border:0;background:none;cursor:pointer;padding:0;opacity:.7;"><i class="ti ti-x"></i></button>';
        chip.querySelector('.a2sb-chip-name').textContent = name;
        chips.appendChild(chip);

        list.querySelectorAll('.a2sb-assign').forEach(sel => {
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = name;
            sel.appendChild(opt);
        });
    }

    const search = document.getElementById('a2sbSearch');
    const noRes = document.getElementById('a2sbNoResults');
    search.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        let shown = 0;
        list.querySelectorAll('.a2sb-row').forEach(row => {
            const match = !q || (row.dataset.text || '').indexOf(q) !== -1;
            row.style.display = match ? 'flex' : 'none';
            if (match) shown++;
        });
        noRes.style.display = (q && shown === 0) ? 'block' : 'none';
    });
})();
</script>
@endpush
