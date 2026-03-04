@extends('admin-v2.layouts.master')

@section('title','Sponsors')
@section('body_class','admin-v2-sponsors')

@section('content')
@php
    $qVal       = (string)($q ?? '');
    $typeVal    = (string)($type ?? '');
    $statusVal  = (string)($status ?? '');
    $perPageVal = (int)($perPage ?? 50);

    $sortNow = (string)($sort ?? 'id');
    $dirNow  = (string)($dir ?? 'desc');

    $typeOptions = [
        ''     => 'الكل',
        'paid' => 'paid',
        'free' => 'free',
    ];

    $statusOptions = [
        ''         => 'الكل',
        'active'   => 'Active',
        'inactive' => 'Inactive',
        'expired'  => 'Expired',
    ];

    $perPageOptions = [10, 20, 50, 100];

    $qsKeep = [
        'q'        => $qVal,
        'type'     => $typeVal,
        'status'   => $statusVal,
        'per_page' => $perPageVal,
        'sort'     => $sortNow,
        'dir'      => $dirNow,
    ];

    $sortUrl = function(string $col) use ($qsKeep, $sortNow, $dirNow) {
        $nextDir = ($sortNow === $col && $dirNow === 'asc') ? 'desc' : 'asc';
        return route('admin.sponsors.index', array_merge($qsKeep, [
            'sort' => $col,
            'dir'  => $nextDir,
        ]));
    };

    $arrow = function(string $col) use ($sortNow, $dirNow) {
        if ($sortNow !== $col) return '';
        return $dirNow === 'asc' ? ' ▲' : ' ▼';
    };

    $deleteTpl = route('admin.sponsors.destroy', ['sponsor' => '__ID__']);

    $isActiveNow = function($s) {
        return !is_null($s->activated_at)
            && (is_null($s->expire_at) || \Carbon\Carbon::parse($s->expire_at)->gte(now()));
    };

    $isExpired = function($s) {
        return !is_null($s->expire_at) && \Carbon\Carbon::parse($s->expire_at)->lt(now());
    };
@endphp

<div class="a2-page" dir="rtl">
    <div class="a2-card">

        <div class="a2-header">
            <h2 class="a2-title">Sponsors</h2>
        </div>

        @if(session('success'))
            <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
        @endif

        {{-- Toolbar / Filters (مثل jobs) --}}
        <form method="GET" action="{{ route('admin.sponsors.index') }}" class="a2-toolbar">
            <div class="a2-filters">

                <input class="a2-input" name="q" value="{{ $qVal }}" placeholder="بحث بـ ID أو user_id أو price">

                <select class="a2-select" name="type">
                    @foreach($typeOptions as $k => $label)
                        <option value="{{ $k }}" @selected((string)$typeVal === (string)$k)>{{ $label }}</option>
                    @endforeach
                </select>

                <select class="a2-select" name="status">
                    @foreach($statusOptions as $k => $label)
                        <option value="{{ $k }}" @selected((string)$statusVal === (string)$k)>{{ $label }}</option>
                    @endforeach
                </select>

                <select class="a2-select" name="per_page">
                    @foreach($perPageOptions as $n)
                        <option value="{{ $n }}" @selected((int)$perPageVal === (int)$n)>{{ $n }} / صفحة</option>
                    @endforeach
                </select>

                <div class="a2-actionsbar">
                    <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
                    <a class="a2-btn a2-btn-ghost" href="{{ route('admin.sponsors.index') }}">تفريغ</a>

                    <button type="button" id="btnBulkDelete" class="a2-btn a2-btn-danger" disabled>حذف المحدد</button>
                    <button type="button" id="btnBulkSelectAll" class="a2-btn a2-btn-ghost">تحديد الكل</button>
                </div>

            </div>

            <input type="hidden" name="sort" value="{{ $sortNow }}">
            <input type="hidden" name="dir" value="{{ $dirNow }}">
        </form>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                <tr>
                    {{-- ✅ خلي checkbox أول عمود مثل jobs --}}
                    <th style="width:56px;">
                        <input type="checkbox" id="chkAll" class="a2-checkbox" title="تحديد الكل">
                    </th>

                    <th style="width:90px;">
                        <a class="a2-link" href="{{ $sortUrl('id') }}">ID{!! $arrow('id') !!}</a>
                    </th>

                    <th style="width:110px;">Image</th>

                    <th style="width:110px;">
                        <a class="a2-link" href="{{ $sortUrl('user_id') }}">User{!! $arrow('user_id') !!}</a>
                    </th>

                    <th style="width:170px;">
                        <a class="a2-link" href="{{ $sortUrl('type') }}">Type{!! $arrow('type') !!}</a>
                    </th>

                    <th style="width:190px;">
                        <a class="a2-link" href="{{ $sortUrl('activated_at') }}">Activated{!! $arrow('activated_at') !!}</a>
                    </th>

                    <th style="width:190px;">
                        <a class="a2-link" href="{{ $sortUrl('expire_at') }}">Expire{!! $arrow('expire_at') !!}</a>
                    </th>

                    <th style="width:120px;">Price</th>

                    <th style="width:320px;">Actions</th>
                </tr>
                </thead>

                <tbody>
                @forelse($items as $s)
                    @php
                        $activeNow = $isActiveNow($s);
                        $expired   = $isExpired($s);
                        $editUrl   = route('admin.sponsors.edit', ['sponsor' => $s->id] + $qsKeep);
                    @endphp

                    <tr>
                        <td>
                            <input type="checkbox" class="a2-checkbox js-row-check" value="{{ $s->id }}" data-id="{{ $s->id }}">
                        </td>

                        <td>{{ $s->id }}</td>

                        <td>
                            <x-admin-v2.image :path="$s->image" size="42" radius="12px" />
                        </td>

                        <td>{{ $s->user_id ?? '—' }}</td>

                        <td>
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                <span class="a2-badge">{{ $s->type }}</span>

                                @if($activeNow)
                                    <span class="a2-badge a2-badge-success">Active</span>
                                @elseif($expired)
                                    <span class="a2-badge a2-badge-danger">Expired</span>
                                @else
                                    <span class="a2-badge a2-badge-muted">Inactive</span>
                                @endif
                            </div>
                        </td>

                        <td dir="ltr">
                            {{ $s->activated_at ? \Carbon\Carbon::parse($s->activated_at)->format('Y-m-d H:i') : '—' }}
                        </td>

                        <td dir="ltr">
                            {{ $s->expire_at ? \Carbon\Carbon::parse($s->expire_at)->format('Y-m-d H:i') : '—' }}
                        </td>

                        <td>{{ $s->price ?? '—' }}</td>

                        <td style="white-space:nowrap;">
                            <div class="a2-actions" style="display:flex;gap:10px;justify-content:flex-start;align-items:center;flex-wrap:nowrap;">

                                <form method="post"
                                      action="{{ route('admin.sponsors.destroy', ['sponsor' => $s->id]) }}"
                                      onsubmit="return confirm('حذف Sponsor؟');"
                                      style="margin:0;">
                                    @csrf
                                    @method('DELETE')
                                    <button class="a2-btn a2-btn-danger" type="submit" style="min-width:78px;">حذف</button>
                                </form>

                                <a class="a2-btn a2-btn-ghost"
                                   style="min-width:78px;text-align:center;"
                                   href="{{ $editUrl }}">تعديل</a>

                                <form method="post"
                                      action="{{ route('admin.sponsors.toggleActive', ['sponsor' => $s->id]) }}"
                                      style="margin:0;">
                                    @csrf
                                    <button type="submit" class="a2-btn a2-btn-ghost" style="min-width:78px;">
                                        {{ $s->activated_at ? 'إيقاف' : 'تفعيل' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="a2-empty-cell">لا يوجد بيانات</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($items, 'links'))
            <div class="a2-paginate">
                {{ $items->links() }}
            </div>
        @endif

    </div>
</div>

{{-- Modal تأكيد الحذف (مثل jobs) --}}
<div id="bulkDeleteModal" class="a2-modal" aria-hidden="true" style="display:none;">
  <div class="a2-modal-backdrop" data-close="1"></div>

  <div class="a2-modal-card" role="dialog" aria-modal="true" aria-labelledby="bulkDeleteTitle">
    <div class="a2-modal-head">
      <div id="bulkDeleteTitle" class="a2-modal-title">تأكيد الحذف</div>
      <button type="button" class="a2-modal-x" data-close="1" aria-label="Close">×</button>
    </div>

    <div class="a2-modal-body">
      <div style="margin-bottom:8px;">سيتم حذف العناصر المحددة.</div>
      <div class="a2-hint" id="bulkDeleteCount">—</div>
    </div>

    <div class="a2-modal-actions">
      <button type="button" class="a2-btn a2-btn-ghost" data-close="1">إلغاء</button>
      <button type="button" id="btnConfirmBulkDelete" class="a2-btn a2-btn-danger">حذف</button>
    </div>
  </div>
</div>

<style>
  .a2-modal{position:fixed;inset:0;z-index:9999}
  .a2-modal-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45)}
  .a2-modal-card{
    position:relative;
    width:min(520px, calc(100% - 24px));
    margin:80px auto 0;
    background:var(--a2-card,#fff);
    border:1px solid var(--a2-border,#eee);
    border-radius:18px;
    box-shadow:0 20px 60px rgba(0,0,0,.25);
    overflow:hidden;
  }
  .a2-modal-head{
    display:flex;align-items:center;justify-content:space-between;
    padding:14px 16px;border-bottom:1px solid var(--a2-border,#eee);
  }
  .a2-modal-title{font-weight:900}
  .a2-modal-x{
    width:36px;height:36px;border-radius:10px;
    border:1px solid var(--a2-border,#eee);
    background:transparent;cursor:pointer;font-size:20px;line-height:1;
  }
  .a2-modal-body{padding:14px 16px}
  .a2-modal-actions{display:flex;gap:10px;justify-content:flex-end;padding:14px 16px;border-top:1px solid var(--a2-border,#eee)}
</style>

<script>
(function(){
  const csrf = @json(csrf_token());
  const deleteTpl = @json($deleteTpl);

  const chkAll = document.getElementById('chkAll');
  const btnDelete = document.getElementById('btnBulkDelete');
  const btnSelectAll = document.getElementById('btnBulkSelectAll');

  const modal = document.getElementById('bulkDeleteModal');
  const modalCount = document.getElementById('bulkDeleteCount');
  const btnConfirm = document.getElementById('btnConfirmBulkDelete');

  function rowChecks(){ return Array.from(document.querySelectorAll('.js-row-check')); }
  function selectedIds(){ return rowChecks().filter(ch => ch.checked).map(ch => ch.value); }

  function refresh(){
    const ids = selectedIds();
    const has = ids.length > 0;
    btnDelete.disabled = !has;

    const checks = rowChecks();
    const allChecked = checks.length > 0 && checks.every(ch => ch.checked);
    chkAll.checked = allChecked;
    chkAll.indeterminate = has && !allChecked;
  }

  chkAll?.addEventListener('change', function(){
    rowChecks().forEach(ch => ch.checked = chkAll.checked);
    refresh();
  });

  document.addEventListener('change', function(e){
    if(e.target && e.target.classList.contains('js-row-check')) refresh();
  });

  btnSelectAll?.addEventListener('click', function(){
    const checks = rowChecks();
    const allChecked = checks.length > 0 && checks.every(ch => ch.checked);
    checks.forEach(ch => ch.checked = !allChecked);
    refresh();
  });

  function openModal(){
    const ids = selectedIds();
    modalCount.textContent = `عدد العناصر المحددة: ${ids.length}`;
    modal.style.display = 'block';
    modal.setAttribute('aria-hidden','false');
    document.body.style.overflow = 'hidden';
  }

  function closeModal(){
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden','true');
    document.body.style.overflow = '';
  }

  modal.addEventListener('click', function(e){
    if(e.target && e.target.getAttribute('data-close') === '1') closeModal();
  });

  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape' && modal.style.display === 'block') closeModal();
  });

  btnDelete?.addEventListener('click', function(){
    if(btnDelete.disabled) return;
    openModal();
  });

  async function okFetch(url, options){
    const res = await fetch(url, options);
    if(!res.ok) throw new Error('HTTP ' + res.status);
    return true;
  }

  btnConfirm?.addEventListener('click', async function(){
    const ids = selectedIds();
    if(!ids.length) { closeModal(); return; }

    btnConfirm.disabled = true;

    try{
      for(const id of ids){
        const url = deleteTpl.replace('__ID__', id);
        await okFetch(url, {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }
        });
      }
      closeModal();
      location.reload();
    }catch(err){
      alert('حدث خطأ أثناء الحذف.');
      btnConfirm.disabled = false;
    }
  });

  refresh();
})();
</script>
@endsection
