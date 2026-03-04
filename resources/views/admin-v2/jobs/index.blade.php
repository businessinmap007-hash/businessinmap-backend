@extends('admin-v2.layouts.master')

@section('title','Jobs')
@section('body_class','admin-v2-jobs')

@section('content')
@php
    $qVal = (string)($q ?? '');
    $expireVal = (string)($expire ?? '');
    $perPageVal = (int)($perPage ?? 50);

    $sortNow = (string)($sort ?? 'id');
    $dirNow  = (string)($dir ?? 'desc');

    $qsKeep = [
        'q' => $qVal,
        'expire' => $expireVal,
        'per_page' => $perPageVal,
        'sort' => $sortNow,
        'dir' => $dirNow,
    ];

    $sortUrl = function(string $col) use ($qsKeep, $sortNow, $dirNow) {
        $nextDir = ($sortNow === $col && $dirNow === 'asc') ? 'desc' : 'asc';
        return route('admin.jobs.index', array_merge($qsKeep, [
            'sort' => $col,
            'dir'  => $nextDir,
        ]));
    };

    $arrow = function(string $col) use ($sortNow, $dirNow) {
        if ($sortNow !== $col) return '';
        return $dirNow === 'asc' ? ' ▲' : ' ▼';
    };

    $deleteTpl = route('admin.jobs.destroy', ['post' => '__ID__']);
@endphp

<div class="a2-page">
    <div class="a2-card">

        <div class="a2-header">
            <h2 class="a2-title">الوظائف</h2>
        </div>

        {{-- ✅ FIX --}}
        @if(session('success'))
            <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
        @endif

        {{-- Toolbar / Filters --}}
        <form method="GET" action="{{ route('admin.jobs.index') }}" class="a2-toolbar">
            <div class="a2-filters">

                <input class="a2-input" name="q" value="{{ $qVal }}" placeholder="بحث بالاسم">

                <select class="a2-select" name="expire">
                    @foreach(($expireOptions ?? []) as $k => $label)
                        <option value="{{ $k }}" {{ ((string)$expireVal === (string)$k) ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>

                <select class="a2-select" name="per_page">
                    @foreach(($perPageOptions ?? []) as $n)
                        <option value="{{ $n }}" {{ ((int)$perPageVal === (int)$n) ? 'selected' : '' }}>
                            {{ $n }} / صفحة
                        </option>
                    @endforeach
                </select>

                <div class="a2-actionsbar">
                    <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
                    <a class="a2-btn a2-btn-ghost" href="{{ route('admin.jobs.index') }}">تفريغ</a>
                    
                    {{-- ✅ Bulk Delete (Modal) --}}
                    <button type="button" id="btnBulkDelete" class="a2-btn a2-btn-danger" disabled>
                        حذف المحدد
                    </button>

                    <button type="button" id="btnBulkSelectAll" class="a2-btn a2-btn-ghost">
                        تحديد الكل
                    </button>
                </div>

            </div>

            <input type="hidden" name="sort" value="{{ $sortNow }}">
            <input type="hidden" name="dir" value="{{ $dirNow }}">
        </form>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                <tr>
                    <th style="width:56px;">
                        <input type="checkbox" id="chkAll" class="a2-checkbox" title="تحديد الكل">
                    </th>

                    <th style="width:90px;">
                        <a class="a2-link" href="{{ $sortUrl('id') }}">ID{!! $arrow('id') !!}</a>
                    </th>

                    <th>العنوان (AR)</th>
                    <th>العنوان (EN)</th>

                    <th style="width:120px;">
                        <a class="a2-link" href="{{ $sortUrl('share_count') }}">Shares{!! $arrow('share_count') !!}</a>
                    </th>

                    <th style="width:200px;">
                        <a class="a2-link" href="{{ $sortUrl('expire_at') }}">Expire{!! $arrow('expire_at') !!}</a>
                    </th>
                </tr>
                </thead>

                <tbody>
                @forelse($posts as $p)
                    @php $viewUrl = route('admin.jobs.show', ['post' => $p->id] + $qsKeep); @endphp
                    <tr>
                        <td>
                            <input type="checkbox" class="a2-checkbox js-row-check" value="{{ $p->id }}" data-id="{{ $p->id }}">
                        </td>

                        <td>{{ $p->id }}</td>

                        <td class="a2-clip a2-clip--name">
                            <a class="a2-link" href="{{ $viewUrl }}">{{ $p->title_ar ?: '—' }}</a>
                        </td>

                        <td class="a2-text-left" dir="ltr">
                            <a class="a2-link" href="{{ $viewUrl }}">{{ $p->title_en ?: '—' }}</a>
                        </td>

                        <td>{{ (int)($p->share_count ?? 0) }}</td>

                        <td dir="ltr">
                            {{ $p->expire_at ? \Carbon\Carbon::parse($p->expire_at)->format('Y-m-d') : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="a2-empty-cell">لا يوجد بيانات</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($posts, 'links'))
            <div class="a2-paginate">
                {{ $posts->links() }}
            </div>
        @endif

    </div>
</div>

{{-- ✅ Modal تأكيد الحذف --}}
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

{{-- ✅ CSS بسيط للمودال (لو عندك a2-modal جاهز في admin.css احذف هذا الجزء) --}}
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

  // modal
  const modal = document.getElementById('bulkDeleteModal');
  const modalCount = document.getElementById('bulkDeleteCount');
  const btnConfirm = document.getElementById('btnConfirmBulkDelete');

  function rowChecks(){
    return Array.from(document.querySelectorAll('.js-row-check'));
  }

  function selectedIds(){
    return rowChecks().filter(ch => ch.checked).map(ch => ch.value);
  }

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
          headers: {
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest'
          }
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
