@extends('admin-v2.layouts.master')

@section('title','Users')

@section('content')
@php
    use Illuminate\Support\Str;

    $qVal        = (string)($q ?? '');
    $typeVal     = (string)($type ?? '');
    $subActiveVal= (string)($subActive ?? '');
    $activeVal   = (string)($active ?? '');
    $trashedVal  = (string)($trashed ?? '');

    $perPageVal  = (int)($perPage ?? 50);

    $sortNow = (string)($sort ?? 'id');
    $dirNow  = (string)($dir ?? 'desc');

    $qsKeep = [
        'q'          => $qVal,
        'type'       => $typeVal,
        'sub_active' => $subActiveVal,
        'active'     => $activeVal,
        'trashed'    => $trashedVal,
        'per_page'   => $perPageVal,
        'sort'       => $sortNow,
        'dir'        => $dirNow,
    ];

    $sortUrl = function(string $col) use ($qsKeep, $sortNow, $dirNow) {
        $nextDir = ($sortNow === $col && $dirNow === 'asc') ? 'desc' : 'asc';
        return route('admin.users.index', array_merge($qsKeep, [
            'sort' => $col,
            'dir'  => $nextDir,
        ]));
    };

    $arrow = function(string $col) use ($sortNow, $dirNow) {
        if ($sortNow !== $col) return '';
        return $dirNow === 'asc' ? ' ▲' : ' ▼';
    };

    $perPageOptions = $perPageOptions ?? [10,20,50,100];

    // ✅ hard limit to 10 chars + ...
    $limit10 = fn($v) => Str::limit((string)$v, 10, '...');
@endphp

<div class="a2-page">
    <div class="a2-card">

        <div class="a2-header">
            <h2 class="a2-title">المستخدمون</h2>
        </div>

        @if(session('success'))
            <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.users.index') }}" class="a2-toolbar">
            <div class="a2-filters">
                {{-- Search --}}
                <input class="a2-input"
                    name="q"
                    value="{{ $qVal }}"
                    placeholder="بحث بالاسم/الموبايل/الإيميل">

                <select class="a2-select" name="type">
                    <option value="" @selected($typeVal==='')>كل الأنواع</option>
                    <option value="client"   @selected($typeVal==='client')>Client</option>
                    <option value="business" @selected($typeVal==='business')>Business</option>
                    <option value="admin"    @selected($typeVal==='admin')>Admin</option>
                </select>

                <select class="a2-select" name="sub_active">
                    <option value=""  @selected($subActiveVal==='')>كل الاشتراكات</option>
                    <option value="1" @selected($subActiveVal==='1')>نشط</option>
                    <option value="0" @selected($subActiveVal==='0')>غير نشط</option>
                </select>

                <select class="a2-select" name="active">
                    <option value=""  @selected($activeVal==='')>كل الحالات</option>
                    <option value="1" @selected($activeVal==='1')>مفعل</option>
                    <option value="0" @selected($activeVal==='0')>غير مفعل</option>
                </select>

                <select class="a2-select" name="trashed">
                    <option value=""      @selected($trashedVal==='')>غير محذوفين</option>
                    <option value="with"  @selected($trashedVal==='with')>مع المحذوفين</option>
                    <option value="only"  @selected($trashedVal==='only')>المحذوفين فقط</option>
                </select>

                <select class="a2-select" name="per_page">
                    @foreach($perPageOptions as $n)
                        <option value="{{ $n }}" @selected((int)$perPageVal === (int)$n)>{{ $n }} / صفحة</option>
                    @endforeach
                </select>

                {{-- ✅ Actions group (keeps same row) --}}
                <div class="a2-actionsbar">
                    <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
                    <a href="{{ route('admin.users.index') }}" class="a2-btn a2-btn-ghost">تفريغ</a>
                </div>
            </div>
        </form>


        {{-- Bulk Actions --}}
        <form id="bulkForm" method="POST" action="{{ route('admin.users.bulkDestroy') }}">
            @csrf
            <input type="hidden" id="bulkMethod" name="_method" value="DELETE">

            <div class="a2-toolbar">
                <div class="a2-filters" style="justify-content:flex-start;">
                    <button type="submit"
                            class="a2-btn a2-btn-danger"
                            onclick="
                                document.getElementById('bulkForm').action='{{ route('admin.users.bulkDestroy') }}';
                                document.getElementById('bulkMethod').value='DELETE';
                                return confirm('تأكيد حذف (Soft) للمستخدمين المحددين؟');
                            ">
                        حذف (Soft)
                    </button>

                    <button type="submit"
                            class="a2-btn a2-btn-success"
                            onclick="
                                document.getElementById('bulkForm').action='{{ route('admin.users.bulkRestore') }}';
                                document.getElementById('bulkMethod').value='';
                                return confirm('تأكيد استرجاع المستخدمين المحددين؟');
                            ">
                        Restore
                    </button>

                    <button type="submit"
                            class="a2-btn a2-btn-dark"
                            onclick="
                                document.getElementById('bulkForm').action='{{ route('admin.users.bulkForceDelete') }}';
                                document.getElementById('bulkMethod').value='DELETE';
                                return confirm('⚠️ حذف نهائي للمستخدمين المحددين؟ لا يمكن التراجع!');
                            ">
                        حذف نهائي
                    </button>
                    <a href="#" id="bulkViewBtn" class="a2-btn a2-btn-ghost">View</a>
<a href="#" id="bulkEditBtn" class="a2-btn a2-btn-ghost">Edit</a>


                    <span class="a2-hint" style="margin-inline-start:10px;">اختر مستخدمين ثم اختر العملية.</span>
                </div>
            </div>

            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                    <tr>
                        <th style="width:60px;">
                            <input class="a2-checkbox" type="checkbox"
                                   onclick="document.querySelectorAll('.cb-user:not([disabled])').forEach(cb => cb.checked = this.checked)">
                        </th>

                        <th style="width:90px;">
                            <a class="a2-link" href="{{ $sortUrl('id') }}">ID{!! $arrow('id') !!}</a>
                        </th>

                        <th style="width:180px;">
                            <a class="a2-link" href="{{ $sortUrl('name') }}">الاسم{!! $arrow('name') !!}</a>
                        </th>

                        <th style="width:170px;">
                            <a class="a2-link" href="{{ $sortUrl('phone') }}">الموبايل{!! $arrow('phone') !!}</a>
                        </th>

                        <th style="width: 160px;">
                            {{-- ✅ FIX: class name was wrong --}}
                            <a class="a2-link " href="{{ $sortUrl('email') }}">Email{!! $arrow('email') !!}</a>
                        </th>

                        <th style="width:120px;">
                            <a class="a2-link" href="{{ $sortUrl('type') }}">Type{!! $arrow('type') !!}</a>
                        </th>

                        <th style="width:120px center;">Activation</th>
                        <th style="width: 120px;">Subscription</th>
                        
                    </tr>
                    </thead>

                    <tbody>
                    @forelse($users as $u)
                        @php
                            $sub = $u->latestSubscription ?? null;
                            $isMe = auth()->check() && (int)auth()->id() === (int)$u->id;
                            $isTrashed = method_exists($u,'trashed') ? $u->trashed() : false;

                            $nameFull  = (string)($u->name ?? '');
                            $emailFull = (string)($u->email ?? '');
                        @endphp

                        <tr class="{{ $isTrashed ? 'a2-row-trashed' : '' }}">
                            <td>
                                <input class="a2-checkbox cb-user"
                                       type="checkbox"
                                       name="ids[]"
                                       value="{{ $u->id }}"
                                       @disabled($isMe)
                                       title="{{ $isMe ? 'لا يمكن تنفيذ العملية على حسابك الحالي' : '' }}">
                            </td>

                            <td>{{ $u->id }}</td>

                            {{-- ✅ name limited to 10 chars --}}
                            <td class="a2-text-right a2-fw-700">
                                <span class="a2-clip a2-clip--name" title="{{ $nameFull }}">
                                    {{ $limit10($nameFull) }}
                                </span>
                            </td>

                            <td dir="ltr">{{ $u->phone }}</td>

                            {{-- ✅ email limited to 10 chars --}}
                            <td dir="ltr">
                                <span class="a2-clip a2-clip--email" title="{{ $emailFull }}">
                                    {{ $limit10($emailFull) }}
                                </span>
                            </td>

                            <td>{{ $u->type }}</td>

                            <td>
                                @if(!empty($u->activated_at))
                                    <span class="a2-pill a2-pill-active">Active</span>
                                @else
                                    <span class="a2-pill a2-pill-inactive">Inactive</span>
                                @endif
                            </td>

                            <td>
                                @if(!$sub)
                                    <span class="a2-pill a2-pill-sub-none">None</span>
                                @elseif((int)($sub->is_active ?? 0) === 1)
                                    <span class="a2-pill a2-pill-sub-active">Active</span>
                                @else
                                    <span class="a2-pill a2-pill-sub-expired">Expired</span>
                                @endif
                            </td>

                            
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="a2-empty-cell">لا يوجد نتائج</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- pagination تحت الجدول --}}
            <div style="padding:12px;">
                {{ $users->links() }}
            </div>
        </form>

    </div>
</div>

<script>
(function(){
  function getSelectedIds(){
    return Array.from(document.querySelectorAll('.cb-user:checked'))
      .map(cb => cb.value)
      .filter(Boolean);
  }

  function requireSingleSelection(){
    const ids = getSelectedIds();
    if (ids.length === 0){
      alert('اختر مستخدم واحد أولاً.');
      return null;
    }
    if (ids.length > 1){
      alert('اختر مستخدم واحد فقط لتنفيذ View / Edit.');
      return null;
    }
    return ids[0];
  }

  const viewBtn = document.getElementById('bulkViewBtn');
  const editBtn = document.getElementById('bulkEditBtn');

  if (viewBtn){
    viewBtn.addEventListener('click', function(e){
      e.preventDefault();
      const id = requireSingleSelection();
      if (!id) return;
      window.location.href = @json(url('/admin/users')) + '/' + id;
    });
  }

  if (editBtn){
    editBtn.addEventListener('click', function(e){
      e.preventDefault();
      const id = requireSingleSelection();
      if (!id) return;
      window.location.href = @json(url('/admin/users')) + '/' + id + '/edit';
    });
  }
})();
</script>


@endsection
