@extends('admin-v2.layouts.master')

@section('title','Categories')
@section('body_class','admin-v2 admin-v2-categories-index')

@section('content')
@php
    $rootIdInt = (int) ($rootId ?? 0);
    $qVal = (string) ($q ?? '');
    $activeVal = (string) ($active ?? '');
    $perPageVal = (int) ($perPage ?? 50);

    $sortNow = (string) ($sort ?? 'reorder');
    $dirNow  = (string) ($dir ?? 'asc');

    $qsKeep = [
        'root_id'  => $rootIdInt,
        'q'        => $qVal,
        'active'   => $activeVal,
        'per_page' => $perPageVal,
        'sort'     => $sortNow,
        'dir'      => $dirNow,
    ];

    $sortUrl = function (string $col) use ($qsKeep, $sortNow, $dirNow) {
        $nextDir = ($sortNow === $col && $dirNow === 'asc') ? 'desc' : 'asc';

        return route('admin.categories.index', array_merge($qsKeep, [
            'sort' => $col,
            'dir'  => $nextDir,
        ]));
    };

    $arrow = function (string $col) use ($sortNow, $dirNow) {
        if ($sortNow !== $col) {
            return '';
        }

        return $dirNow === 'asc' ? ' ▲' : ' ▼';
    };

    $nameOf = function ($cat) {
        $ar = (string) ($cat->name_ar ?? '');
        $en = (string) ($cat->name_en ?? '');

        return $ar !== '' ? $ar : ($en !== '' ? $en : '—');
    };
@endphp

<div class="a2-page">
    <div class="a2-page-actions" style="margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;">
        <a href="{{ route('admin.categories.create', $rootIdInt > 0 ? ['parent_id' => $rootIdInt, 'root_id' => $rootIdInt] : []) }}"
           class="a2-btn a2-btn-primary">
            + إضافة قسم
        </a>

        @if($rootIdInt > 0)
            <a href="{{ route('admin.categories.create', ['parent_id' => $rootIdInt, 'root_id' => $rootIdInt]) }}"
               class="a2-btn a2-btn-ghost">
                + إضافة فرعي
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="a2-card">
        <form method="GET" action="{{ route('admin.categories.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" type="text" name="q" value="{{ $qVal }}" placeholder="بحث داخل الأقسام الفرعية">

            <select class="a2-select a2-filter-md" name="root_id">
                <option value="0" @selected($rootIdInt === 0)>كل الأقسام الرئيسية</option>
                @foreach(($roots ?? []) as $r)
                    <option value="{{ $r->id }}" @selected($rootIdInt === (int) $r->id)>
                        #{{ $r->id }} - {{ $nameOf($r) }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="sort">
                <option value="id" @selected($sortNow === 'id')>ID</option>
                <option value="name_ar" @selected($sortNow === 'name_ar')>الاسم العربي</option>
                <option value="name_en" @selected($sortNow === 'name_en')>الاسم الإنجليزي</option>
                <option value="reorder" @selected($sortNow === 'reorder')>الترتيب</option>
            </select>

            <select class="a2-select a2-filter-sm" name="dir">
                <option value="desc" @selected($dirNow === 'desc')>DESC</option>
                <option value="asc" @selected($dirNow === 'asc')>ASC</option>
            </select>

            <select class="a2-select a2-filter-sm" name="active">
                @foreach(($activeOptions ?? []) as $k => $label)
                    <option value="{{ $k }}" @selected((string) $activeVal === (string) $k)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach(($perPageOptions ?? []) as $n)
                    <option value="{{ $n }}" @selected((int) $perPageVal === (int) $n)>
                        {{ $n }} / صفحة
                    </option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>

                <a class="a2-btn a2-btn-ghost"
                   href="{{ route('admin.categories.index', $rootIdInt > 0 ? ['root_id' => $rootIdInt] : []) }}">
                    تفريغ
                </a>
            </div>
        </form>
    </div>

    @if($rootIdInt > 0 && !empty($root))
        <div class="a2-card a2-card--section">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">القسم الرئيسي المختار</div>
                    <div class="a2-card-sub">معلومات القسم الذي يتم عرض فروعه الآن</div>
                </div>

                <div class="a2-page-actions">
                    <a class="a2-btn a2-btn-ghost"
                       href="{{ route('admin.categories.edit', ['category' => $root->id, 'root_id' => $root->id]) }}">
                        تعديل القسم الرئيسي
                    </a>
                </div>
            </div>

            <div class="a2-form-grid">
                <div style="display:flex;align-items:flex-start;gap:14px;">
                    <x-admin-v2.image :path="$root->image" size="68" radius="16px" />

                    <div>
                        <div style="font-weight:900;font-size:18px;">
                            {{ $root->name_ar ?: ($root->name_en ?: '—') }}
                            <span class="a2-muted">#{{ $root->id }}</span>
                        </div>

                        @if(!empty($root->name_en))
                            <div class="a2-page-subtitle" style="margin-top:6px;">
                                EN: {{ $root->name_en }}
                            </div>
                        @endif

                        @if(!empty($root->slug))
                            <div class="a2-page-subtitle" style="margin-top:4px;">
                                Slug: <span dir="ltr">{{ $root->slug }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div>
                    <div class="a2-form-grid">
                        <div class="a2-card a2-card--soft">
                            <div class="a2-card-sub">السعر الشهري</div>
                            <div style="font-size:20px;font-weight:900;margin-top:6px;">
                                {{ $root->per_month ?? '—' }}
                            </div>
                        </div>

                        <div class="a2-card a2-card--soft">
                            <div class="a2-card-sub">السعر السنوي</div>
                            <div style="font-size:20px;font-weight:900;margin-top:6px;">
                                {{ $root->per_year ?? '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($rootIdInt === 0)
        <div class="a2-alert a2-alert-warning">
            اختر قسمًا رئيسيًا من القائمة بالأعلى لعرض وإدارة الأقسام الفرعية التابعة له.
        </div>
    @else
        <form method="POST" action="{{ route('admin.categories.services-bulk.apply') }}" id="bulkServicesForm">
            @csrf
            <input type="hidden" name="root_id" value="{{ $rootIdInt }}">

            <div class="a2-card a2-card--section" style="margin-bottom:16px;">
                <div class="a2-card-head">
                    <div>
                        <div class="a2-card-title">ربط الخدمات كمجموعة</div>
                        <div class="a2-card-sub">حدد تصنيفات فرعية من الجدول ثم اختر الخدمات ونوع العملية</div>
                    </div>
                </div>

                <div class="a2-form-grid">
                    <div class="a2-form-group a2-field-full">
                        <label class="a2-label">الخدمات</label>
                        <div class="a2-check-grid">
                            @foreach(($platformServices ?? []) as $service)
                                <label class="a2-check-card">
                                    <input type="checkbox" name="platform_service_ids[]" value="{{ $service->id }}">
                                    <span>
                                        <strong>{{ $service->name_ar ?: ($service->name_en ?: $service->key) }}</strong>
                                        <small dir="ltr">{{ $service->key }}</small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="a2-form-group">
                        <label class="a2-label">نوع العملية</label>
                        <select class="a2-select" name="mode">
                            <option value="append">Append</option>
                            <option value="replace">Replace</option>
                            <option value="remove">Remove</option>
                        </select>
                    </div>

                    <div class="a2-form-group" style="display:flex;align-items:end;">
                        <button type="submit" class="a2-btn a2-btn-primary" id="bulkApplyBtn">
                            تطبيق على المحدد
                        </button>
                    </div>
                </div>
            </div>

            <div class="a2-card">
                <div class="a2-card-head">
                    <div>
                        <div class="a2-card-title">الأقسام الفرعية</div>
                        <div class="a2-card-sub">
                            @if(isset($children) && method_exists($children, 'total'))
                                إجمالي النتائج: {{ $children->total() }}
                            @else
                                عرض الأقسام الفرعية التابعة للقسم الرئيسي المختار
                            @endif
                        </div>
                    </div>
                </div>

                <div class="a2-table-wrap">
                    <table class="a2-table">
                        <thead>
                        <tr>
                            <th style="width:44px;">
                                <input type="checkbox" id="checkAllChildren">
                            </th>

                            <th style="width:90px;">
                                <a class="a2-link" href="{{ $sortUrl('id') }}">ID{!! $arrow('id') !!}</a>
                            </th>

                            <th style="width:96px;">الصورة</th>

                            <th>
                                <a class="a2-link" href="{{ $sortUrl('name_ar') }}">الاسم (AR){!! $arrow('name_ar') !!}</a>
                            </th>

                            <th>
                                <a class="a2-link" href="{{ $sortUrl('name_en') }}">الاسم (EN){!! $arrow('name_en') !!}</a>
                            </th>

                            <th style="width:180px;">
                                <a class="a2-link" href="{{ $sortUrl('reorder') }}">Order{!! $arrow('reorder') !!}</a>
                            </th>

                            <th style="width:120px;">Status</th>
                            <th style="width:110px;">Options</th>
                            <th style="width:220px;">Actions</th>
                        </tr>
                        </thead>

                        <tbody>
                        @forelse($children as $c)
                            @php
                                $isActive = (int) ($c->is_active ?? 0) === 1;
                            @endphp
                            <tr>
                                <td>
                                    <input type="checkbox" class="js-child-check" name="category_ids[]" value="{{ $c->id }}">
                                </td>

                                <td>{{ $c->id }}</td>

                                <td>
                                    <div style="display:flex;justify-content:center;">
                                        <x-admin-v2.image :path="$c->image" size="46" radius="12px" />
                                    </div>
                                </td>

                                <td class="a2-fw-700">{{ $c->name_ar ?: '—' }}</td>

                                <td dir="ltr">{{ $c->name_en ?: '—' }}</td>

                                <td>
                                    <div style="display:flex;align-items:center;justify-content:center;gap:8px;">
                                        <input
                                            class="a2-input js-reorder"
                                            type="number"
                                            value="{{ (int) ($c->reorder ?? 0) }}"
                                            style="width:90px;text-align:center;"
                                            min="0"
                                            step="1"
                                            data-id="{{ $c->id }}"
                                            data-url="{{ route('admin.categories.reorder', $c->id) }}"
                                        >
                                        <span class="a2-page-subtitle js-reorder-status" style="margin:0;min-width:68px;text-align:left;"></span>
                                    </div>
                                </td>

                                <td>
                                    <button
                                        type="button"
                                        class="a2-pill {{ $isActive ? 'a2-pill-active' : 'a2-pill-inactive' }} js-toggle-active"
                                        data-url="{{ route('admin.categories.toggleActive', $c->id) }}"
                                        data-state="{{ $isActive ? 1 : 0 }}"
                                        aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                                        title="تغيير الحالة"
                                        style="border:none;cursor:pointer"
                                    >
                                        {{ $isActive ? 'Active' : 'Inactive' }}
                                    </button>
                                </td>

                                <td>
                                    <a class="a2-btn a2-btn-ghost a2-btn-sm"
                                       href="{{ route('admin.categories.options.edit', $c->id) }}">
                                        Options
                                    </a>
                                </td>

                                <td>
                                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:nowrap;">
                                        <a class="a2-btn a2-btn-ghost a2-btn-sm"
                                           href="{{ route('admin.categories.edit', ['category' => $c->id] + $qsKeep) }}">
                                            Edit
                                        </a>

                                        <form method="POST"
                                              action="{{ route('admin.categories.destroy', $c->id) }}"
                                              onsubmit="return confirm('تأكيد حذف القسم الفرعي؟');"
                                              style="margin:0;">
                                            @csrf
                                            @method('DELETE')
                                            @foreach($qsKeep as $k => $v)
                                                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                            @endforeach

                                            <button type="submit" class="a2-btn a2-btn-danger a2-btn-sm">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="a2-empty-cell">لا يوجد أقسام فرعية</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if(isset($children) && method_exists($children, 'links'))
                    <div style="margin-top:14px;">
                        {{ $children->links() }}
                    </div>
                @endif
            </div>
        </form>
    @endif
</div>

@push('scripts')
<script>
(function () {
    const token = @json(csrf_token());

    const timers = new Map();
    const lastSent = new Map();
    const inflight = new Map();

    function setStatus(el, text, type) {
        if (!el) return;

        el.textContent = text || '';
        el.style.opacity = text ? '1' : '0.7';
        el.style.color =
            type === 'ok'  ? '#16a34a' :
            type === 'err' ? '#ef4444' :
            type === 'wait' ? '#667085' : '';
    }

    async function saveReorder(input) {
        const id = input.dataset.id;
        const url = input.dataset.url;
        const status = input.parentElement.querySelector('.js-reorder-status');
        const value = String(input.value ?? '').trim();

        if (!id || !url || value === '') {
            setStatus(status, 'Error', 'err');
            return;
        }

        if (lastSent.get(id) === value) {
            setStatus(status, 'Saved', 'ok');
            return;
        }

        if (inflight.has(id)) {
            inflight.get(id).abort();
            inflight.delete(id);
        }

        const controller = new AbortController();
        inflight.set(id, controller);

        setStatus(status, 'Saving…', 'wait');

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ reorder: value }),
                signal: controller.signal,
            });

            inflight.delete(id);

            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }

            lastSent.set(id, value);
            setStatus(status, 'Saved', 'ok');

            setTimeout(() => {
                if (status.textContent === 'Saved') {
                    setStatus(status, '', '');
                }
            }, 1200);
        } catch (e) {
            if (e.name === 'AbortError') return;
            setStatus(status, 'Error', 'err');
        }
    }

    function bindReorder() {
        document.querySelectorAll('input.js-reorder').forEach((input) => {
            function flushAndSave() {
                const id = input.dataset.id;

                if (timers.has(id)) {
                    clearTimeout(timers.get(id));
                    timers.delete(id);
                }

                saveReorder(input);
            }

            input.addEventListener('input', () => {
                const id = input.dataset.id;
                const status = input.parentElement.querySelector('.js-reorder-status');

                setStatus(status, '…', 'wait');

                if (timers.has(id)) {
                    clearTimeout(timers.get(id));
                }

                timers.set(id, setTimeout(() => saveReorder(input), 600));
            });

            input.addEventListener('change', flushAndSave);

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    flushAndSave();
                    input.blur();
                }
            });

            input.addEventListener('blur', flushAndSave);
        });
    }

    async function toggleActive(btn) {
        const url = btn.dataset.url;
        if (!url) return;

        if (btn.dataset.loading === '1') return;
        btn.dataset.loading = '1';

        const oldState = btn.dataset.state === '1';
        btn.style.opacity = '0.7';

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({}),
            });

            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }

            const data = await res.json().catch(() => ({}));
            const newState = (data.is_active !== undefined)
                ? (String(data.is_active) === '1')
                : (!oldState);

            btn.dataset.state = newState ? '1' : '0';
            btn.setAttribute('aria-pressed', newState ? 'true' : 'false');
            btn.textContent = newState ? 'Active' : 'Inactive';

            btn.classList.toggle('a2-pill-active', newState);
            btn.classList.toggle('a2-pill-inactive', !newState);
        } catch (e) {
            btn.dataset.state = oldState ? '1' : '0';
            alert('حدث خطأ أثناء تغيير الحالة');
        } finally {
            btn.style.opacity = '1';
            btn.dataset.loading = '0';
        }
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-toggle-active');
        if (!btn) return;

        e.preventDefault();
        toggleActive(btn);
    });

    const checkAll = document.getElementById('checkAllChildren');
    const childChecks = document.querySelectorAll('.js-child-check');
    const bulkForm = document.getElementById('bulkServicesForm');

    if (checkAll && childChecks.length) {
        checkAll.addEventListener('change', function () {
            childChecks.forEach(function (el) {
                el.checked = checkAll.checked;
            });
        });
    }

    if (bulkForm) {
        bulkForm.addEventListener('submit', function (e) {
            const checkedChildren = document.querySelectorAll('.js-child-check:checked').length;
            const checkedServices = bulkForm.querySelectorAll('input[name="platform_service_ids[]"]:checked').length;

            if (checkedChildren === 0) {
                e.preventDefault();
                alert('حدد تصنيفًا فرعيًا واحدًا على الأقل.');
                return;
            }

            if (checkedServices === 0) {
                e.preventDefault();
                alert('حدد خدمة واحدة على الأقل.');
                return;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindReorder);
    } else {
        bindReorder();
    }
})();
</script>
@endpush
@endsection