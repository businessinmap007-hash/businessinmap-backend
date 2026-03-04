@extends('admin-v2.layouts.master')

@section('title','تعديل ملاحظة')
@section('body_class','admin-v2-wallet-notes')

@section('content')
<div class="a2-page">
  <div class="a2-card" style="max-width:820px;margin:0 auto;">

    <div class="a2-header">
      <div>
        <h2 class="a2-title">تعديل ملاحظة</h2>
        <div class="a2-hint">ID #{{ $walletNote->id }}</div>
      </div>
      <div class="a2-actionsbar">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.wallet-notes.index') }}">رجوع</a>
      </div>
    </div>

    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{!! implode('<br>', $errors->all()) !!}</div>
    @endif

    <form method="POST" action="{{ route('admin.wallet-notes.update', $walletNote) }}" style="display:grid;gap:12px;">
      @csrf
      @method('PUT')

      <div>
        <div class="a2-hint" style="margin-bottom:6px;">العنوان</div>
        <input
          class="a2-input"
          name="title"
          value="{{ old('title', $walletNote->title) }}"
          required
          style="width:100%;"
        >
      </div>

      <div>
        <div class="a2-hint" style="margin-bottom:6px;">النص</div>

        {{-- ✅ textarea بدل input علشان يظهر النص كله --}}
        <textarea
          class="a2-input"
          name="text"
          rows="4"
          maxlength="255"
          required
          style="width:100%;height:auto;min-height:110px;line-height:1.8;resize:vertical;padding-top:10px;padding-bottom:10px;"
        >{{ old('text', $walletNote->text) }}</textarea>

        <div class="a2-hint" style="margin-top:6px;">حد أقصى 255 حرف</div>
      </div>

      <div style="display:grid;grid-template-columns: 1fr 1fr; gap:12px;">
        <div>
          <div class="a2-hint" style="margin-bottom:6px;">الترتيب</div>
          <input
            class="a2-input"
            name="sort"
            type="number"
            min="0"
            value="{{ old('sort', (int)$walletNote->sort) }}"
            style="width:100%;"
          >
        </div>

        <div style="display:flex;align-items:flex-end;">
          <label style="display:flex;align-items:center;gap:10px;font-weight:900;">
            <input
              class="a2-checkbox"
              type="checkbox"
              name="is_active"
              value="1"
              @checked(old('is_active', $walletNote->is_active))
            >
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