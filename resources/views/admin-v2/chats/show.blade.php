@extends('admin-v2.layouts.master')

@section('title','Chat')
@section('body_class','admin-v2-chats')

@section('content')
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ $kind }}{{ $thread->title ? ' — '.$thread->title : '' }} <span class="a2-hint">#{{ $thread->id }}</span></h2>
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

    <div style="max-height:520px;overflow-y:auto;display:flex;flex-direction:column;gap:8px;padding:8px;background:rgba(0,0,0,.03);border-radius:8px;">
      @forelse($thread->messages->sortBy('id') as $message)
        @if($message->kind === \App\Models\ThreadMessage::KIND_SYSTEM)
          <div class="a2-hint" style="text-align:center;font-style:italic;">{{ $message->body }}</div>
        @else
          <div style="background:#fff;border-radius:8px;padding:8px 10px;">
            <div class="a2-hint">
              {{ $message->sender?->name ?? ('#'.$message->sender_id) }}
              — {{ optional($message->created_at)->format('Y-m-d H:i') }}
            </div>
            @if(trim((string) $message->body) !== '')
              <div style="margin-top:4px;">{{ $message->body }}</div>
            @endif
            @if($message->attachments->isNotEmpty())
              <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px;">
                @foreach($message->attachments as $att)
                  <a href="{{ $att->adminUrl() }}" target="_blank" rel="noopener" title="{{ $att->original_name }}">
                    <img src="{{ $att->adminUrl() }}" alt="{{ $att->original_name }}"
                         style="width:76px;height:76px;object-fit:cover;border-radius:6px;border:1px solid var(--a2-border,#e5e7eb);">
                  </a>
                @endforeach
              </div>
            @endif
          </div>
        @endif
      @empty
        <div class="a2-hint" style="text-align:center;">{{ __('لا توجد رسائل.') }}</div>
      @endforelse
    </div>
  </div>
</div>
@endsection
