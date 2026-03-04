@extends('admin-v2.layouts.master')

@section('title','Edit Sponsor')
@section('body_class','admin-v2-sponsors')

@section('content')
@php
  $backUrl = route('admin.sponsors.index');
  $imgPath = (string)($sponsor->image ?? '');
@endphp

<div class="a2-page">

  <div class="a2-card">

    <div class="a2-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
      <h2 class="a2-title" style="margin:0;">تعديل الإعلان</h2>

      <div class="a2-actionsbar" style="display:flex;gap:10px;justify-content:flex-start;align-items:center;">
        <a class="a2-btn a2-btn-ghost" href="{{ $backUrl }}">رجوع</a>

        <form method="post" action="{{ route('admin.sponsors.toggleActive', ['sponsor' => $sponsor->id]) }}" style="margin:0;">
          @csrf
          <button type="submit" class="a2-btn a2-btn-ghost">
            {{ $sponsor->activated_at ? 'إيقاف' : 'تفعيل' }}
          </button>
        </form>

        <form method="post"
              action="{{ route('admin.sponsors.destroy', ['sponsor' => $sponsor->id]) }}"
              onsubmit="return confirm('حذف Sponsor؟');"
              style="margin:0;">
          @csrf
          @method('DELETE')
          <button type="submit" class="a2-btn a2-btn-danger">حذف</button>
        </form>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-grid" style="display:grid;grid-template-columns: 1fr 360px;gap:16px;align-items:start;">

      {{-- Form --}}
      <div>
        <form method="post" action="{{ route('admin.sponsors.update', $sponsor) }}" enctype="multipart/form-data">
          @csrf
          @method('PUT')

          <div class="spon-form">
            @include('admin-v2.sponsors._form', ['sponsor' => $sponsor])
          </div>

          <div class="a2-actionsbar" style="margin-top:14px;display:flex;gap:10px;">
            <button class="a2-btn a2-btn-primary" type="submit">تحديث</button>
            <a class="a2-btn a2-btn-ghost" href="{{ $backUrl }}">إلغاء</a>
          </div>
        </form>
      </div>

      {{-- Preview --}}
      <aside>
        <div class="a2-card" style="padding:14px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <div class="a2-hint">Preview</div>
            @php
              $activeNow = !is_null($sponsor->activated_at) && (is_null($sponsor->expire_at) || \Carbon\Carbon::parse($sponsor->expire_at)->gte(now()));
              $expired   = !is_null($sponsor->expire_at) && \Carbon\Carbon::parse($sponsor->expire_at)->lt(now());
            @endphp
            @if($activeNow)
              <span class="a2-badge a2-badge-success">Active</span>
            @elseif($expired)
              <span class="a2-badge a2-badge-danger">Expired</span>
            @else
              <span class="a2-badge a2-badge-muted">Inactive</span>
            @endif
          </div>

          <div style="display:flex;justify-content:center;margin-bottom:12px;">
            <x-admin-v2.image :path="$imgPath" size="220" radius="18px" />
          </div>

          <div style="display:grid;gap:8px;">
            <div style="display:flex;justify-content:space-between;gap:10px;">
              <div class="a2-hint">ID</div>
              <div class="a2-fw-700">{{ $sponsor->id }}</div>
            </div>

            <div style="display:flex;justify-content:space-between;gap:10px;">
              <div class="a2-hint">User</div>
              <div>{{ $sponsor->user_id ?? '—' }}</div>
            </div>

            <div style="display:flex;justify-content:space-between;gap:10px;">
              <div class="a2-hint">Price</div>
              <div>{{ $sponsor->price ?? '—' }}</div>
            </div>

            <div style="display:flex;justify-content:space-between;gap:10px;">
              <div class="a2-hint">Activated</div>
              <div dir="ltr">{{ $sponsor->activated_at ? \Carbon\Carbon::parse($sponsor->activated_at)->format('Y-m-d H:i') : '—' }}</div>
            </div>

            <div style="display:flex;justify-content:space-between;gap:10px;">
              <div class="a2-hint">Expire</div>
              <div dir="ltr">{{ $sponsor->expire_at ? \Carbon\Carbon::parse($sponsor->expire_at)->format('Y-m-d H:i') : '—' }}</div>
            </div>
          </div>
        </div>
      </aside>
    </div>

  </div>
</div>

<style>
/* Responsive */
@media (max-width: 1100px){
  .a2-grid{grid-template-columns: 1fr !important;}
}

/* =========================
   Sponsors form alignment
   - label right, field left
   - same widths/heights
   ========================= */
.spon-form{max-width: 720px;}
.spon-form .spon-row{
  display:grid;
  grid-template-columns: 170px 1fr;
  gap:12px;
  align-items:center;
  margin-bottom:12px;
}
.spon-form .spon-label{
  font-weight:800;
  color:var(--a2-text,#101828);
  text-align:right;
  line-height:1.2;
}
.spon-form .spon-help{
  font-size:12px;
  color:var(--a2-muted,#667085);
  margin-top:4px;
  font-weight:600;
}

.spon-form input[type="text"],
.spon-form input[type="number"],
.spon-form input[type="datetime-local"],
.spon-form input[type="file"],
.spon-form select,
.spon-form textarea{
  width:100%;
  min-height:44px;
  padding:10px 12px;
  border:1px solid var(--a2-border-2,#e6e8ee);
  border-radius:14px;
  background:#fff;
  outline:none;
  box-sizing:border-box;
}

.spon-form textarea{min-height:110px;resize:vertical}

/* file input looks weird by default */
.spon-form input[type="file"]{padding:8px 10px}

/* make date input consistent */
.spon-form input[type="datetime-local"]{
  direction:ltr;
  text-align:left;
}

/* small screens */
@media (max-width: 700px){
  .spon-form .spon-row{grid-template-columns: 1fr;}
  .spon-form .spon-label{text-align:right}
}
</style>

@endsection
