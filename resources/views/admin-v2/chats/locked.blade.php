@extends('admin-v2.layouts.master')

@section('title','Chat — locked')
@section('body_class','admin-v2-chats')

@section('content')
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ $kind }} <span class="a2-hint">#{{ $thread->id }}</span></h2>
        <div class="a2-hint">
          {{ __('الأطراف') }}:
          @foreach($thread->participants as $p)
            <span>{{ $p->user?->name ?? ('#'.$p->user_id) }} ({{ $p->role }})</span>@if(!$loop->last) — @endif
          @endforeach
        </div>
      </div>
      <div>
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.chats.index') }}">{{ __('رجوع') }}</a>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    <div class="a2-alert a2-alert-muted" style="margin-top:10px;">
      🔒 {{ __('محتوى هذه المحادثة مشفّر ولا يظهر إلا بموافقة أطرافها، أو باطّلاع عدد كافٍ من المشرفين.') }}
    </div>

    <div style="margin-top:16px;">
      <div class="a2-title" style="font-size:14px;">{{ __('موافقة الأطراف') }}</div>
      <div style="margin-top:6px;display:flex;flex-direction:column;gap:4px;">
        @foreach($decisions as $userId => $decision)
          @php
            $participant = $thread->participants->firstWhere('user_id', $userId);
            [$label, $kindBadge] = match($decision) {
                'approved' => [__('وافق'), 'success'],
                'declined' => [__('رفض'), 'danger'],
                default => [__('لم يردّ بعد'), 'muted'],
            };
          @endphp
          <div>
            {{ $participant?->user?->name ?? ('#'.$userId) }}
            <span class="a2-badge a2-badge-{{ $kindBadge }}" style="margin-inline-start:6px">{{ $label }}</span>
          </div>
        @endforeach
      </div>
    </div>

    <div style="margin-top:16px;">
      <div class="a2-title" style="font-size:14px;">{{ __('موافقة المشرفين') }}</div>
      <div class="a2-hint" style="margin-top:4px;">
        {{ __('عدد الموافقين') }}: {{ $approvalCount }} / {{ $quorum }}
      </div>
    </div>

    <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;">
      @if(!$alreadyApproved)
        <form method="POST" action="{{ route('admin.chats.approve-access', $thread) }}">
          @csrf
          <button class="a2-btn a2-btn-primary" type="submit">{{ __('أوافق على الاطلاع') }}</button>
        </form>
      @else
        <div class="a2-hint">{{ __('لقد وافقت بالفعل على الاطلاع على هذه المحادثة.') }}</div>
      @endif

      <form method="POST" action="{{ route('admin.chats.request-consent', $thread) }}">
        @csrf
        <button class="a2-btn a2-btn-ghost" type="submit">{{ __('طلب موافقة الطرفين') }}</button>
      </form>
    </div>
  </div>
</div>
@endsection
