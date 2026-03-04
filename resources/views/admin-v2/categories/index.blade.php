
@extends('admin-v2.layouts.master')

@section('title','Categories')

@section('content')
@php
    $rootIdInt = (int)($rootId ?? 0);
    $qVal = (string)($q ?? '');
    $activeVal = (string)($active ?? '');
    $perPageVal = (int)($perPage ?? 50);

    $sortNow = (string)($sort ?? 'reorder');
    $dirNow  = (string)($dir ?? 'asc');

    $qsKeep = [
        'root_id'  => $rootIdInt,
        'q'        => $qVal,
        'active'   => $activeVal,
        'per_page' => $perPageVal,
        'sort'     => $sortNow,
        'dir'      => $dirNow,
    ];

    $sortUrl = function(string $col) use ($qsKeep, $sortNow, $dirNow) {
        $nextDir = ($sortNow === $col && $dirNow === 'asc') ? 'desc' : 'asc';
        return route('admin.categories.index', array_merge($qsKeep, [
            'sort' => $col,
            'dir'  => $nextDir,
        ]));
    };

    $arrow = function(string $col) use ($sortNow, $dirNow) {
        if ($sortNow !== $col) return '';
        return $dirNow === 'asc' ? ' ▲' : ' ▼';
    };

    $nameOf = function($cat) {
        $ar = (string)($cat->name_ar ?? '');
        $en = (string)($cat->name_en ?? '');
        return $ar !== '' ? $ar : ($en !== '' ? $en : '—');
    };
@endphp

<div class="a2-page">
    <div class="a2-card">

        <div class="a2-header">
            <h2 class="a2-title">الأقسام الفرعية</h2>
        </div>

        @if(session('success'))
            <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.categories.index') }}" class="a2-toolbar">
            <div class="a2-filters">

                <select class="a2-select" name="root_id" onchange="this.form.submit()">
                    <option value="0" @selected($rootIdInt===0)>اختر قسم رئيسي (تخصص)</option>
                    @foreach(($roots ?? []) as $r)
                        <option value="{{ $r->id }}" @selected($rootIdInt === (int)$r->id)>
                            #{{ $r->id }} - {{ $nameOf($r) }}
                        </option>
                    @endforeach
                </select>

                <input class="a2-input"
                       name="q"
                       value="{{ $qVal }}"
                       placeholder="بحث داخل الفرعيات">

                <select class="a2-select" name="active">
                    @foreach(($activeOptions ?? []) as $k => $label)
                        <option value="{{ $k }}" @selected((string)$activeVal === (string)$k)>{{ $label }}</option>
                    @endforeach
                </select>

                <select class="a2-select" name="per_page">
                    @foreach(($perPageOptions ?? []) as $n)
                        <option value="{{ $n }}" @selected((int)$perPageVal === (int)$n)>{{ $n }} / صفحة</option>
                    @endforeach
                </select>

                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>

                <a class="a2-btn a2-btn-ghost"
                   href="{{ route('admin.categories.index', ['root_id' => $rootIdInt]) }}">
                    تفريغ
                </a>

                @if($rootIdInt > 0)
                    <a href="{{ route('admin.categories.create', ['parent_id' => $rootIdInt] + $qsKeep) }}"
                       class="a2-btn a2-btn-primary">
                        + إضافة فرعي
                    </a>
                @endif
            </div>
        </form>

        {{-- Root card --}}
        @if($rootIdInt > 0 && !empty($root))
            <div class="a2-card a2-root">
                <div class="a2-root-row">

                    <div class="a2-root-left">
                        <x-admin-v2.image :path="$root->image" size="64" radius="14px" />

                        <div>
                            <div class="a2-root-title">
                                {{ $root->name_ar ?: ($root->name_en ?: '—') }}
                                <span class="a2-root-id">(#{{ $root->id }})</span>
                            </div>

                            @if(!empty($root->name_en))
                                <div class="a2-hint a2-root-meta">EN: {{ $root->name_en }}</div>
                            @endif

                            <div class="a2-root-prices">
                                <span>شهري: <b>{{ $root->per_month ?? '—' }}</b></span>
                                <span>سنوي: <b>{{ $root->per_year ?? '—' }}</b></span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <a class="a2-btn a2-btn-ghost"
                           href="{{ route('admin.categories.edit', ['category' => $root->id, 'root_id' => $root->id]) }}">
                            تعديل القسم الرئيسي
                        </a>
                    </div>

                </div>
            </div>
        @endif

        @if($rootIdInt === 0)
            <div class="a2-alert a2-alert-warning">
                اختر قسم رئيسي من القائمة بالأعلى لعرض وإدارة الأقسام الفرعية الخاصة به.
            </div>
        @else
            <div class="a2-table-wrap">

                <table class="a2-table">
                    <thead>
                    <tr>
                        <th style="width:90px;">
                            <a class="a2-link" href="{{ $sortUrl('id') }}">ID{!! $arrow('id') !!}</a>
                        </th>

                        <th>
                            <a class="a2-link" href="{{ $sortUrl('name_ar') }}">الاسم (AR){!! $arrow('name_ar') !!}</a>
                        </th>

                        <th>
                            <a class="a2-link" href="{{ $sortUrl('name_en') }}">الاسم (EN){!! $arrow('name_en') !!}</a>
                        </th>

                        <th style="width:160px;">
                            <a class="a2-link" href="{{ $sortUrl('reorder') }}">Order{!! $arrow('reorder') !!}</a>
                        </th>

                        <th style="width:120px;">Status</th>
                        <th style="width:220px;">Actions</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse($children as $c)
                        @php $isActive = (int)($c->is_active ?? 0) === 1; @endphp
                        <tr>
                            <td>{{ $c->id }}</td>

                            <td class="a2-text-right a2-fw-700">{{ $c->name_ar ?: '—' }}</td>

                            <td class="a2-text-center" dir="ltr">{{ $c->name_en ?: '—' }}</td>

                            {{-- reorder autosave --}}
                            <td>
                                <div style="display:flex;align-items:center;justify-content:center;gap:8px;">
                                    <input
                                        class="a2-input js-reorder"
                                        type="number"
                                        value="{{ (int)($c->reorder ?? 0) }}"
                                        style="width:90px;text-align:center;"
                                        min="0"
                                        step="1"
                                        data-id="{{ $c->id }}"
                                        data-url="{{ route('admin.categories.reorder', $c->id) }}"
                                    >
                                    <span class="a2-hint js-reorder-status" style="min-width:68px;text-align:left;"></span>
                                </div>
                            </td>

                            {{-- ✅ Status toggle AJAX (click pill) --}}
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

                            <td style="width:260px;">
                                <div class="a2-actions" style="display:flex;gap:10px;justify-content:flex-start;align-items:center;flex-wrap:nowrap;">
                                    <a class="a2-btn a2-btn-ghost"
                                       style="min-width:78px;text-align:center;"
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
                                        <button type="submit"
                                                class="a2-btn a2-btn-ghost a2-btn-danger"
                                                style="min-width:78px;">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="6" class="a2-empty-cell">لا يوجد أقسام فرعية</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>

            </div>
        @endif

    </div>
</div>

@push('scripts')
<script>
(function(){
  const token = @json(csrf_token());

  // ============================
  // Reorder autosave
  // ============================
  const timers = new Map();
  const lastSent = new Map();
  const inflight = new Map();

  function setStatus(el, text, type){
    if (!el) return;
    el.textContent = text || '';
    el.style.opacity = text ? '1' : '0.7';
    el.style.color =
      type === 'ok'  ? '#16a34a' :
      type === 'err' ? '#ef4444' :
      type === 'wait'? '#667085' : '';
  }

  async function saveReorder(input){
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
      if (!res.ok) throw new Error('HTTP ' + res.status);

      lastSent.set(id, value);
      setStatus(status, 'Saved', 'ok');
      setTimeout(() => {
        if (status.textContent === 'Saved') setStatus(status, '', '');
      }, 1200);

    } catch (e) {
      if (e.name === 'AbortError') return;
      setStatus(status, 'Error', 'err');
    }
  }

  function bindReorder(){
    document.querySelectorAll('input.js-reorder').forEach((input) => {

      function flushAndSave(){
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

        if (timers.has(id)) clearTimeout(timers.get(id));
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

  // ============================
  // Toggle Active (pill click) AJAX
  // ============================
  async function toggleActive(btn){
    const url = btn.dataset.url;
    if (!url) return;

    if (btn.dataset.loading === '1') return;
    btn.dataset.loading = '1';

    const oldState = btn.dataset.state === '1';
    btn.style.opacity = '0.7';

    try{
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({}),
      });

      if (!res.ok) throw new Error('HTTP ' + res.status);

      const data = await res.json().catch(() => ({}));
      const newState = (data.is_active !== undefined) ? (String(data.is_active) === '1') : (!oldState);

      btn.dataset.state = newState ? '1' : '0';
      btn.setAttribute('aria-pressed', newState ? 'true' : 'false');
      btn.textContent = newState ? 'Active' : 'Inactive';

      btn.classList.toggle('a2-pill-active', newState);
      btn.classList.toggle('a2-pill-inactive', !newState);

    }catch(e){
      btn.dataset.state = oldState ? '1' : '0';
      alert('حدث خطأ أثناء تغيير الحالة');
    }finally{
      btn.style.opacity = '1';
      btn.dataset.loading = '0';
    }
  }

  document.addEventListener('click', function(e){
    const btn = e.target.closest('.js-toggle-active');
    if (!btn) return;
    e.preventDefault();
    toggleActive(btn);
  });

  // Init
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      bindReorder();
    });
  } else {
    bindReorder();
  }
})();
</script>
@endpush

@endsection
