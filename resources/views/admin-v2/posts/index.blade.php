@extends('admin-v2.layouts.master')

@section('title','Posts')
@section('body_class','admin-v2-posts')

@section('content')
@php
    $qVal = (string)($q ?? '');
    $activeVal = (string)($active ?? '');
    $perPageVal = (int)($perPage ?? 50);

    $sortNow = (string)($sort ?? 'id');
    $dirNow  = (string)($dir ?? 'desc');

    $qsKeep = [
        'q' => $qVal,
        'active' => $activeVal,
        'per_page' => $perPageVal,
        'sort' => $sortNow,
        'dir' => $dirNow,
    ];

    $sortUrl = function(string $col) use ($qsKeep, $sortNow, $dirNow) {
        $nextDir = ($sortNow === $col && $dirNow === 'asc') ? 'desc' : 'asc';
        return route('admin.posts.index', array_merge($qsKeep, [
            'sort' => $col,
            'dir'  => $nextDir,
        ]));
    };

    $arrow = function(string $col) use ($sortNow, $dirNow) {
        if ($sortNow !== $col) return '';
        return $dirNow === 'asc' ? ' ▲' : ' ▼';
    };

    // route templates for JS bulk actions
    $toggleTpl  = route('admin.posts.toggleActive', ['post' => '__ID__']);
@endphp

<div class="a2-page">
    <div class="a2-card">

        <div class="a2-header">
            <h2 class="a2-title">المنشورات</h2>
        </div>

        @if(session('success'))
            <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
        @endif

        {{-- Toolbar / Filters --}}
        <form method="GET" action="{{ route('admin.posts.index') }}" class="a2-toolbar">
            <div class="a2-filters">

                <input class="a2-input" name="q" value="{{ $qVal }}" placeholder="بحث بالاسم">

                {{-- ✅ حذفنا type filter بالكامل --}}

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

                <div class="a2-actionsbar">
                    <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>

                    <a class="a2-btn a2-btn-ghost" href="{{ route('admin.posts.index') }}">تفريغ</a>

                    {{-- ✅ حذفنا زر الإضافة create --}}

                    {{-- Bulk Toggle --}}
                    <button type="button" id="btnBulkToggle" class="a2-btn a2-btn-ghost" disabled>
                        تفعيل / تعطيل
                    </button>

                    {{-- Select all --}}
                    <button type="button" id="btnBulkSelectAll" class="a2-btn a2-btn-ghost">
                        تحديد الكل
                    </button>
                </div>

            </div>
        </form>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                <tr>
                    {{-- Checkbox --}}
                    <th style="width:56px;">
                        <input type="checkbox" id="chkAll" class="a2-checkbox" title="تحديد الكل">
                    </th>

                    <th style="width:90px;">
                        <a class="a2-link" href="{{ $sortUrl('id') }}">ID{!! $arrow('id') !!}</a>
                    </th>

                    <th>العنوان (AR)</th>
                    <th>العنوان (EN)</th>

                    {{-- ✅ حذفنا عمود Type --}}

                    <th style="width:120px;">
                        <a class="a2-link" href="{{ $sortUrl('share_count') }}">Shares{!! $arrow('share_count') !!}</a>
                    </th>

                    <th style="width:200px;">
                        <a class="a2-link" href="{{ $sortUrl('expire_at') }}">Expire{!! $arrow('expire_at') !!}</a>
                    </th>

                    <th style="width:110px;">Status</th>
                </tr>
                </thead>

                <tbody>
                @forelse($posts as $p)
                    @php
                        $isActive = (int)($p->is_active ?? 0) === 1;

                        // click titles -> VIEW (show)
                        $viewUrl = route('admin.posts.show', ['post' => $p->id] + $qsKeep);
                    @endphp
                    <tr>
                        <td>
                            <input type="checkbox"
                                   class="a2-checkbox js-row-check"
                                   value="{{ $p->id }}"
                                   data-id="{{ $p->id }}">
                        </td>

                        <td>{{ $p->id }}</td>

                        <td class="a2-clip a2-clip--name">
                            <a class="a2-link" href="{{ $viewUrl }}">{{ $p->title_ar ?: '—' }}</a>
                        </td>

                        <td class="a2-text-left" dir="ltr">
                            <a class="a2-link" href="{{ $viewUrl }}">{{ $p->title_en ?: '—' }}</a>
                        </td>

                        {{-- ✅ حذفنا عرض type هنا --}}

                        <td>{{ (int)($p->share_count ?? 0) }}</td>

                        <td dir="ltr">
                            {{ $p->expire_at ? \Carbon\Carbon::parse($p->expire_at)->format('Y-m-d') : '—' }}
                        </td>

                        <td>
                            <button
                                type="button"
                                class="a2-pill {{ $isActive ? 'a2-pill-active' : 'a2-pill-inactive' }} js-toggle-active"
                                data-url="{{ route('admin.posts.toggleActive', $p) }}"
                                data-state="{{ $isActive ? 1 : 0 }}"
                                aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                                title="تغيير الحالة"
                                style="border:none;cursor:pointer"
                            >
                                {{ $isActive ? 'Active' : 'Inactive' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="a2-empty-cell">لا يوجد بيانات</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if(method_exists($posts, 'links'))
            <div class="a2-paginate">
                {{ $posts->links() }}
            </div>
        @endif

    </div>
</div>

<script>
(function(){
  const csrf = @json(csrf_token());
  const toggleTpl  = @json($toggleTpl);

  const chkAll = document.getElementById('chkAll');
  const btnToggle = document.getElementById('btnBulkToggle');
  const btnSelectAll = document.getElementById('btnBulkSelectAll');

  function rowChecks(){
    return Array.from(document.querySelectorAll('.js-row-check'));
  }

  function selectedIds(){
    return rowChecks().filter(ch => ch.checked).map(ch => ch.value);
  }

  function refreshButtons(){
    const ids = selectedIds();
    const has = ids.length > 0;
    if (btnToggle) btnToggle.disabled = !has;

    const checks = rowChecks();
    const allChecked = checks.length > 0 && checks.every(ch => ch.checked);
    if (chkAll){
      chkAll.checked = allChecked;
      chkAll.indeterminate = has && !allChecked;
    }
  }

  chkAll?.addEventListener('change', function(){
    rowChecks().forEach(ch => ch.checked = chkAll.checked);
    refreshButtons();
  });

  document.addEventListener('change', function(e){
    if(e.target && e.target.classList.contains('js-row-check')){
      refreshButtons();
    }
  });

  btnSelectAll?.addEventListener('click', function(){
    const checks = rowChecks();
    const allChecked = checks.length > 0 && checks.every(ch => ch.checked);
    checks.forEach(ch => ch.checked = !allChecked);
    refreshButtons();
  });

  async function okFetch(url, options){
    const res = await fetch(url, options);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return true;
  }

  // bulk toggle active
  btnToggle?.addEventListener('click', async function(){
    const ids = selectedIds();
    if(!ids.length) return;

    if(!confirm('تأكيد تفعيل/تعطيل العناصر المحددة؟')) return;

    btnToggle.disabled = true;

    try{
      for(const id of ids){
        const url = toggleTpl.replace('__ID__', id);
        await okFetch(url, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
      }
      location.reload();
    }catch(err){
      alert('حدث خطأ أثناء تغيير الحالة. تأكد من الروت toggleActive.');
      refreshButtons();
    }
  });

  // init
  refreshButtons();
})();
</script>

@endsection
