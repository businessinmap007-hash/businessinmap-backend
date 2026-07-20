@extends('admin-v2.layouts.master')

@section('title','Held Deletions')
@section('body_class','admin-v2-held-deletions')

@section('content')
@php $money = fn($v) => number_format((float)$v, 2); @endphp
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('طلبات حذف موقوفة للمراجعة') }}</h2>
        <div class="a2-hint">
          {{ __('حسابات رفضت المكنسة إتمام حذفها لوجود رصيد محجوز أو نزاع. لا تعود المكنسة إليها تلقائيًا — تحتاج قرارًا بشريًا.') }}
        </div>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
      <div class="a2-alert a2-alert-danger">{{ session('error') }}</div>
    @endif

    <div class="a2-statgrid">
      <div class="a2-stat">
        <div class="a2-stat-label">{{ __('موقوفة') }}</div>
        <div class="a2-stat-value">{{ $rows->count() }}</div>
      </div>
      <div class="a2-stat">
        <div class="a2-stat-label">{{ __('زال سبب الإيقاف') }}</div>
        <div class="a2-stat-value">{{ $resolvedCount }}</div>
        <div class="a2-hint">{{ __('جاهزة لإتمام الحذف') }}</div>
      </div>
    </div>

    <div class="a2-tablewrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>#</th>
            <th>{{ __('الحساب') }}</th>
            <th>{{ __('تاريخ الطلب') }}</th>
            <th>{{ __('سبب الإيقاف') }}</th>
            <th>{{ __('الرصيد') }}</th>
            <th>{{ __('النزاعات') }}</th>
            <th style="width:220px;">{{ __('الإجراءات') }}</th>
          </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
          @php $user = $row['user']; @endphp
          <tr>
            <td>{{ $user->id }}</td>
            <td>
              <div class="a2-fw-700">{{ $user->name ?: '—' }}</div>
              <div class="a2-hint" dir="ltr">{{ $user->email ?: $user->phone ?: '—' }}</div>
            </td>
            <td dir="ltr">
              {{ optional($user->deletion_requested_at)->format('Y-m-d') ?? '—' }}
              <div class="a2-hint" dir="ltr">
                {{ __('الاستحقاق') }}: {{ optional($user->deletion_scheduled_at)->format('Y-m-d') ?? '—' }}
              </div>
            </td>
            <td>
              {{-- The stored reason is the snapshot from the sweep; the badge
                   below says whether it is still true right now. --}}
              <div>{{ $row['stored_reason'] ?: '—' }}</div>
              @if($row['resolved'])
                <span class="a2-badge a2-badge-success">{{ __('زال السبب') }}</span>
              @else
                <span class="a2-badge a2-badge-danger">{{ __('ما زال قائمًا') }}</span>
                @if($row['current_reason'] !== $row['stored_reason'])
                  <div class="a2-hint">{{ $row['current_reason'] }}</div>
                @endif
              @endif
            </td>
            <td dir="ltr">
              {{ $money($row['available_balance']) }}
              <div class="a2-hint" dir="ltr">{{ __('محجوز') }}: {{ $money($row['locked_balance']) }}</div>
            </td>
            <td>{{ $row['open_disputes'] }}</td>
            <td>
              <div style="display:flex;gap:8px;flex-wrap:wrap;">
                {{-- Irreversible: escheats the balance and scrubs the identity. --}}
                <form method="post"
                      action="{{ route('admin.held-deletions.finalize', ['user' => $user->id]) }}"
                      onsubmit="return confirm('{{ __('إتمام الحذف نهائيًا؟ لا يمكن التراجع.') }}');"
                      style="margin:0;">
                  @csrf
                  <button type="submit"
                          class="a2-btn a2-btn-sm a2-btn-danger"
                          @disabled(! $row['resolved'])>
                    {{ __('إتمام الحذف') }}
                  </button>
                </form>

                <form method="post"
                      action="{{ route('admin.held-deletions.restore', ['user' => $user->id]) }}"
                      onsubmit="return confirm('{{ __('استعادة الحساب وإلغاء طلب الحذف؟') }}');"
                      style="margin:0;">
                  @csrf
                  <button type="submit" class="a2-btn a2-btn-sm a2-btn-ghost">{{ __('استعادة') }}</button>
                </form>
              </div>
              @unless($row['resolved'])
                <div class="a2-hint" style="margin-top:6px;">
                  {{ __('عالج سبب الإيقاف أولًا لتفعيل الإتمام.') }}
                </div>
              @endunless
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="a2-empty-cell">{{ __('لا توجد طلبات حذف موقوفة.') }}</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
