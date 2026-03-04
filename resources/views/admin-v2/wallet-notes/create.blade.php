@extends('admin-v2.layouts.master')

@section('title','إضافة ملاحظة')
@section('body_class','admin-v2-wallet-notes')

@section('content')
<div class="a2-page">
  <div class="a2-card" style="max-width:820px;margin:0 auto;">

    <div class="a2-header">
      <div>
        <h2 class="a2-title">إضافة ملاحظة</h2>
        <div class="a2-hint">هذه الملاحظة ستظهر كاختيار داخل معاملات المحفظة</div>
      </div>
      <div class="a2-actionsbar">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.wallet-notes.index') }}">رجوع</a>
      </div>
    </div>

    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{!! implode('<br>', $errors->all()) !!}</div>
    @endif

    <form method="POST" action="{{ route('admin.wallet-notes.store') }}" style="display:grid;gap:12px;">
      @csrf

      <div>
        <div class="a2-hint" style="margin-bottom:6px;">العنوان</div>
        <input class="a2-input" name="title" value="{{ old('title') }}" required>
      </div>

      <div>
        <div class="a2-hint" style="margin-bottom:6px;">النص</div>
        <input class="a2-input" name="text" value="{{ old('text') }}" maxlength="255" required>
      </div>

      <div style="display:grid;grid-template-columns: 1fr 1fr; gap:12px;">
        <div>
          <div class="a2-hint" style="margin-bottom:6px;">الترتيب</div>
          <input class="a2-input" name="sort" type="number" min="0" value="{{ old('sort', 0) }}">
        </div>

        <div style="display:flex;align-items:flex-end;">
          <label style="display:flex;align-items:center;gap:10px;font-weight:900;">
            <input class="a2-checkbox" type="checkbox" name="is_active" value="1" @checked(old('is_active', 1))>
            نشط
          </label>
        </div>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button class="a2-btn a2-btn-primary" type="submit">حفظ</button>
      </div>
    </form>

  </div>
</div>
@endsection