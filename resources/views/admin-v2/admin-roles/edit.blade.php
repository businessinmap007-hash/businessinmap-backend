@extends('admin-v2.layouts.master')

@section('title','تعديل صلاحيات مشرف')
@section('body_class','admin-v2-admin-roles')

@section('content')
<div class="a2-page">
  <div class="a2-card" style="max-width:820px">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">صلاحيات: {{ $target->name }}</h2>
        <div class="a2-hint">{{ $target->email }}</div>
      </div>
      <a class="a2-btn a2-btn-sm" href="{{ route('admin.admin-roles.index') }}">رجوع</a>
    </div>

    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('admin.admin-roles.update', $target->id) }}">
      @csrf
      @method('PUT')

      @foreach($labels as $ability => $label)
        @php
          $isHeld = in_array($ability, $held, true);
          $canGrant = in_array($ability, $grantable, true);
        @endphp
        <div class="a2-field" style="margin-bottom:14px; opacity: {{ $canGrant ? '1' : '.55' }}">
          <label class="a2-label" style="display:flex; gap:8px; align-items:flex-start; cursor:{{ $canGrant ? 'pointer' : 'not-allowed' }}">
            <input type="checkbox"
                   name="abilities[]"
                   value="{{ $ability }}"
                   @checked($isHeld)
                   @disabled(! $canGrant)
                   style="margin-top:4px">
            <span>
              <span style="font-weight:600">{{ $label }}</span>
              <div class="a2-hint">{{ $hints[$ability] ?? '' }}</div>
              @unless($canGrant)
                <div class="a2-hint" style="color:var(--a2-danger, #b42318)">
                  لا تملك هذه الصلاحية بنفسك، فلا يمكنك منحها.
                </div>
              @endunless
            </span>
          </label>

          {{-- A disabled checkbox posts nothing at all, which would otherwise
               read as "remove it". Nothing is re-posted here on purpose: the
               server keeps abilities outside the actor's scope exactly as they
               were, which is the right place for that rule to live. --}}
        </div>
      @endforeach

      <div style="margin-top:20px; display:flex; gap:8px">
        <button type="submit" class="a2-btn a2-btn-primary">حفظ</button>
        <a class="a2-btn" href="{{ route('admin.admin-roles.index') }}">إلغاء</a>
      </div>
    </form>
  </div>
</div>
@endsection
