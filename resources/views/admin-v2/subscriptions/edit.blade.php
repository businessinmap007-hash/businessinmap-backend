@extends('admin-v2.layouts.master')

@section('title','تعديل سجل الاشتراك')
@section('body_class','admin-v2-subscriptions-edit')

@section('content')
@php
  $s = $subscription;

  $categoryIdNow = old('category_id', (string)($s->category_id ?? ''));
  $activeNow     = old('is_active', (string)((int)($s->is_active ?? 1)));
@endphp

<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
      <div>
        <h2 class="a2-title">تعديل سجل #{{ $s->id }}</h2>
        <div class="a2-muted" dir="ltr">
          Created: {{ $s->created_at ? $s->created_at->format('Y-m-d H:i') : '—' }}
        </div>
      </div>

      <div class="a2-actionsbar">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.subscriptions.show', $s->id) }}">رجوع</a>
        <form method="POST" action="{{ route('admin.subscriptions.toggle-active', $s->id) }}" style="display:inline;">
          @csrf
          <button class="a2-btn a2-btn-ghost" type="submit">
            {{ (int)$s->is_active ? 'إيقاف' : 'تفعيل' }}
          </button>
        </form>
      </div>
    </div>

    @if ($errors->any())
      <div class="a2-alert a2-alert-danger" style="margin:12px 0;">
        <div class="a2-fw-900" style="margin-bottom:6px;">يوجد أخطاء</div>
        <ul style="margin:0;padding-inline-start:18px;">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('admin.subscriptions.update', $s->id) }}" class="a2-form">
      @csrf
      @method('PUT')

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">

        <div>
          <label class="a2-label">القسم الرئيسي</label>
          <select class="a2-select" name="category_id">
            <option value="">—</option>

            @foreach(($categories ?? []) as $c)
              @php
                // ✅ عرض الأقسام الرئيسية فقط: parent_id = null أو 0
                $isRoot = ((int)($c->parent_id ?? 0) === 0);
                if (!$isRoot) continue;

                $catName = (string)($c->name_ar ?? '');
                $label = $catName !== '' ? $catName : ('#'.$c->id);
              @endphp

              <option value="{{ $c->id }}" @selected((string)$c->id === (string)$categoryIdNow)>
                {{ $label }}
              </option>
            @endforeach
          </select>

          <div class="a2-muted" style="margin-top:6px;font-size:12px;">
            يتم اختيار الأقسام الرئيسية فقط (Parent).
          </div>
        </div>

        <div>
          <label class="a2-label">الحالة</label>
          <select class="a2-select" name="is_active">
            <option value="1" @selected((string)$activeNow === '1')>Active</option>
            <option value="0" @selected((string)$activeNow === '0')>Off</option>
          </select>
        </div>

      </div>

      <div class="a2-actionsbar" style="margin-top:14px;">
        <button type="submit" class="a2-btn a2-btn-primary">حفظ</button>
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.subscriptions.show', $s->id) }}">إلغاء</a>
      </div>
    </form>

  </div>
</div>
@endsection