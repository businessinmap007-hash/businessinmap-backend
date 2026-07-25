@extends('admin-v2.layouts.master')

@section('title','Operation Chats')
@section('body_class','admin-v2-operation-chats')

@section('content')
@php
  use App\Models\Order;
  $typeLabel = fn($t) => $t === Order::class ? __('طلب') : __('حجز');
  $partyName = function($thread, $role) {
      $p = $thread->participants->firstWhere('role', $role);
      return $p && $p->user ? $p->user->name : '—';
  };
@endphp
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('محادثات العمليات المنتهية') }}</h2>
        <div class="a2-hint">
          {{ __('محادثات العميل والتاجر التي مرّ على اكتمال عمليتها أكثر من مدة الحفظ (٧ أيام)، فأصبحت للقراءة فقط ويمكن حذفها. تُحذف المحادثة كاملة بمرفقاتها؛ لا يُعاد فتحها.') }}
        </div>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="a2-alert a2-alert-danger">{{ session('error') }}</div>
    @endif

    <div class="a2-table-wrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>#</th>
            <th>{{ __('نوع العملية') }}</th>
            <th>{{ __('رقم العملية') }}</th>
            <th>{{ __('العميل') }}</th>
            <th>{{ __('التاجر') }}</th>
            <th>{{ __('الرسائل') }}</th>
            <th>{{ __('انتهت الحماية') }}</th>
            <th>{{ __('إجراء') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $thread)
            <tr>
              <td>{{ $thread->id }}</td>
              <td>{{ $typeLabel($thread->subject_type) }}</td>
              <td>#{{ $thread->subject_id }}</td>
              <td>{{ $partyName($thread, 'client') }}</td>
              <td>{{ $partyName($thread, 'business') }}</td>
              <td>{{ $thread->messages_count }}</td>
              <td>{{ optional($thread->retain_until)->format('Y-m-d') }}</td>
              <td>
                <form method="POST" action="{{ route('admin.operation-chats.destroy', $thread) }}"
                      onsubmit="return confirm('{{ __('حذف هذه المحادثة نهائيًا؟') }}');" style="display:inline;">
                  @csrf @method('DELETE')
                  <button class="a2-btn a2-btn-danger" type="submit">{{ __('حذف') }}</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="a2-empty">{{ __('لا توجد محادثات منتهية للحذف.') }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div style="margin-top:12px;">{{ $rows->links() }}</div>
  </div>
</div>
@endsection
