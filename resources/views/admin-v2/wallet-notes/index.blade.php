@extends('admin-v2.layouts.master')

@section('title','ملاحظات المعاملات')
@section('body_class','admin-v2-wallet-notes')

@section('content')
@php
  $qVal = (string)($q ?? '');
  $activeVal = (string)($active ?? '');
  $perPageVal = (int)($perPage ?? 50);

  $sortNow = (string)($sort ?? 'sort');
  $dirNow  = (string)($dir ?? 'asc');

  $qsKeep = [
    'q' => $qVal,
    'active' => $activeVal,
    'per_page' => $perPageVal,
    'sort' => $sortNow,
    'dir' => $dirNow,
  ];

  $sortUrl = function(string $col) use ($qsKeep, $sortNow, $dirNow) {
    $nextDir = ($sortNow === $col && $dirNow === 'asc') ? 'desc' : 'asc';
    return route('admin.wallet-notes.index', array_merge($qsKeep, ['sort'=>$col,'dir'=>$nextDir]));
  };

  $arrow = function(string $col) use ($sortNow, $dirNow) {
    if ($sortNow !== $col) return '';
    return $dirNow === 'asc' ? ' ▲' : ' ▼';
  };
@endphp

<div class="a2-page">
  <div class="a2-card">

    <div class="a2-header">
      <div>
        <h2 class="a2-title">ملاحظات المعاملات</h2>
        <div class="a2-hint">قائمة ثابتة لاختيار note_id بدل النص الحر</div>
      </div>

      <div class="a2-actionsbar">
        <a class="a2-btn a2-btn-primary" href="{{ route('admin.wallet-notes.create') }}">إضافة ملاحظة</a>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" class="a2-toolbar" action="{{ route('admin.wallet-notes.index') }}">
      <div class="a2-filters">
        <input class="a2-input" name="q" value="{{ $qVal }}" placeholder="بحث بالعنوان/النص/ID">

        <select class="a2-select" name="active">
          <option value=""  @selected($activeVal==='')>الكل</option>
          <option value="1" @selected($activeVal==='1')>نشط</option>
          <option value="0" @selected($activeVal==='0')>غير نشط</option>
        </select>

        <select class="a2-select" name="per_page">
          @foreach([10,20,50,100] as $n)
            <option value="{{ $n }}" @selected($perPageVal===$n)>{{ $n }}/صفحة</option>
          @endforeach
        </select>

        <button class="a2-btn a2-btn-ghost" type="submit">تطبيق</button>
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.wallet-notes.index') }}">مسح</a>
      </div>
    </form>

    <div class="a2-table-wrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th><a class="a2-link" href="{{ $sortUrl('id') }}">ID{!! $arrow('id') !!}</a></th>
            <th><a class="a2-link" href="{{ $sortUrl('title') }}">العنوان{!! $arrow('title') !!}</a></th>
            <th>النص</th>
            <th><a class="a2-link" href="{{ $sortUrl('sort') }}">ترتيب{!! $arrow('sort') !!}</a></th>
            <th><a class="a2-link" href="{{ $sortUrl('is_active') }}">الحالة{!! $arrow('is_active') !!}</a></th>
            <th>إجراءات</th>
          </tr>
        </thead>
        <tbody>
          @forelse($items as $it)
            <tr>
              <td class="a2-fw-900">{{ $it->id }}</td>
              <td class="a2-fw-900">{{ $it->title }}</td>
              <td><span class="a2-clip" title="{{ $it->text }}">{{ $it->text }}</span></td>
              <td>{{ (int)$it->sort }}</td>
              <td>
                @if($it->is_active)
                  <span class="a2-pill a2-pill-active">نشط</span>
                @else
                  <span class="a2-pill a2-pill-inactive">غير نشط</span>
                @endif
              </td>
              <td>
                <div class="a2-actions">
                  <a class="a2-link" href="{{ route('admin.wallet-notes.edit', $it) }}">تعديل</a>

                  <form method="POST" action="{{ route('admin.wallet-notes.destroy', $it) }}"
                        onsubmit="return confirm('حذف هذه الملاحظة؟');">
                    @csrf
                    @method('DELETE')
                    <button class="a2-link a2-link-danger" type="submit">حذف</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="a2-empty-cell">لا توجد بيانات</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div style="margin-top:12px;">
      {{ $items->links() }}
    </div>

  </div>
</div>
@endsection