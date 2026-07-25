@extends('admin-v2.layouts.master')

@section('title','Chats')
@section('body_class','admin-v2-chats')

@section('content')
@php
  $ctrl = app(\App\Http\Controllers\AdminV2\ChatAdminController::class);
  $partyNames = function($thread) {
      return $thread->participants->map(fn($p) => $p->user?->name ?? ('#'.$p->user_id))->filter()->implode('، ');
  };
  $types = [
      '' => __('الكل'),
      'dispute' => __('غرف النزاع'),
      'operation' => __('محادثات العمليات'),
      'direct' => __('مباشرة'),
      'group' => __('مجموعات'),
  ];
@endphp
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('المحادثات (إشراف)') }}</h2>
        <div class="a2-hint">
          {{ __('كل المحادثات على المنصّة في مكان واحد — غرف النزاع ومحادثات العمليات والمباشرة والمجموعات. النصوص مشفّرة عند التخزين، ولا يطّلع عليها إلا من له صلاحية الحكم. العرض هنا للقراءة فقط.') }}
        </div>
      </div>
    </div>

    <div class="a2-filterbar" style="margin-bottom:12px;">
      @foreach($types as $key => $label)
        <a class="a2-btn {{ $type === $key ? 'a2-btn-primary' : 'a2-btn-ghost' }}"
           href="{{ route('admin.chats.index', array_filter(['type' => $key])) }}">{{ $label }}</a>
      @endforeach
    </div>

    <div class="a2-table-wrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>#</th>
            <th>{{ __('النوع') }}</th>
            <th>{{ __('الأطراف') }}</th>
            <th>{{ __('الرسائل') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('آخر نشاط') }}</th>
            <th>{{ __('إجراء') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $thread)
            <tr>
              <td>{{ $thread->id }}</td>
              <td>{{ $ctrl->kindOf($thread) }}{{ $thread->title ? ' — '.$thread->title : '' }}</td>
              <td style="max-width:320px;white-space:normal;">{{ $partyNames($thread) }}</td>
              <td>{{ $thread->messages_count }}</td>
              <td>{{ $thread->isLocked() ? __('مغلقة') : __('مفتوحة') }}</td>
              <td>{{ optional($thread->last_message_at)->format('Y-m-d H:i') ?: '—' }}</td>
              <td><a class="a2-btn a2-btn-ghost" href="{{ route('admin.chats.show', $thread) }}">{{ __('عرض') }}</a></td>
            </tr>
          @empty
            <tr><td colspan="7" class="a2-empty">{{ __('لا توجد محادثات.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div style="margin-top:12px;">{{ $rows->links() }}</div>
  </div>
</div>
@endsection
