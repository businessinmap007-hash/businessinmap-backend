@extends('admin-v2.layouts.master')

@section('title','عرض سجل الاشتراك')
@section('body_class','admin-v2-subscriptions-show')

@section('content')
@php
  $s = $subscription;
  $user = $s->user;
  $userLabel = (string)($user->name ?? '');
  $userLabel = $userLabel !== '' ? $userLabel : ('#'.(int)$s->user_id);

  $userShowUrl = null;
  if ($s->user_id) {
    try { $userShowUrl = route('admin.users.show', $s->user_id); } catch (\Throwable $e) {}
  }

  $couponTxt = (string)($s->coupon_id ?? '');
  $codeTypeTxt = (string)($s->code_type ?? '');
@endphp

<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
      <div>
        <h2 class="a2-title">سجل اشتراك #{{ $s->id }}</h2>
        <div class="a2-muted" dir="ltr">
          Created: {{ $s->created_at ? $s->created_at->format('Y-m-d H:i') : '—' }}
          @if($s->updated_at) • Updated: {{ $s->updated_at->format('Y-m-d H:i') }} @endif
        </div>
      </div>

      <div class="a2-actionsbar">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.subscriptions.index') }}">رجوع</a>
        <a class="a2-btn a2-btn-primary" href="{{ route('admin.subscriptions.edit', $s->id) }}">تعديل</a>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="a2-card" style="box-shadow:none;border:1px solid var(--a2-border);">
        <div class="a2-header">
          <h3 class="a2-title" style="font-size:16px;">بيانات السجل</h3>
        </div>

        <div class="a2-body" style="padding:14px;">
          <div class="a2-row">
            <div class="a2-muted">Active</div>
            <div>
              @if((int)$s->is_active === 1)
                <span class="a2-badge a2-badge-success">Active</span>
              @else
                <span class="a2-badge a2-badge-muted">Off</span>
              @endif
            </div>
          </div>

          <div class="a2-row">
            <div class="a2-muted">Category</div>
            <div>{{ $s->category_id ? '#'.$s->category_id : '—' }}</div>
          </div>

          <div class="a2-row">
            <div class="a2-muted">coupon_id</div>
            <div class="a2-clip a2-clip-14" title="{{ $couponTxt }}">{{ $couponTxt !== '' ? $couponTxt : '—' }}</div>
          </div>

          <div class="a2-row">
            <div class="a2-muted">code_type</div>
            <div class="a2-clip a2-clip-14" title="{{ $codeTypeTxt }}">{{ $codeTypeTxt !== '' ? $codeTypeTxt : '—' }}</div>
          </div>
        </div>
      </div>

      <div class="a2-card" style="box-shadow:none;border:1px solid var(--a2-border);">
        <div class="a2-header">
          <h3 class="a2-title" style="font-size:16px;">المستخدم</h3>
        </div>

        <div class="a2-body" style="padding:14px;">
          <div class="a2-row">
            <div class="a2-muted">User</div>
            <div>
              @if($userShowUrl)
                <a class="a2-link" href="{{ $userShowUrl }}">{{ $userLabel }}</a>
              @else
                {{ $userLabel }}
              @endif
            </div>
          </div>

          <div class="a2-row">
            <div class="a2-muted">User ID</div>
            <div dir="ltr">#{{ (int)$s->user_id }}</div>
          </div>

         

          <div class="a2-row">
            <div class="a2-muted">Type</div>
            <div>{{ $user?->type ?? '—' }}</div>
          </div>
        </div>
      </div>
    </div>

    <div class="a2-actionsbar" style="margin-top:12px;">
      <form method="POST" action="{{ route('admin.subscriptions.toggle-active', $s->id) }}">
        @csrf
        <button class="a2-btn a2-btn-ghost" type="submit">
          {{ (int)$s->is_active ? 'إيقاف' : 'تفعيل' }}
        </button>
      </form>
    </div>

  </div>
</div>
@endsection