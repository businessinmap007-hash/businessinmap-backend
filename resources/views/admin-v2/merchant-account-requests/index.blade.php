@extends('admin-v2.layouts.master')

@section('title','Merchant Account Requests')
@section('body_class','admin-v2-merchant-account-requests')

@section('content')
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('طلبات حسابات التجّار (Fawry)') }}</h2>
        <div class="a2-hint">{{ __('تجّار يطلبون حساب merchant فرعي. الاعتماد يُفعّل الحساب فورًا بالأكواد المُدخَلة.') }}</div>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <form class="a2-filterbar" method="get">
      <select class="a2-input a2-select a2-filter-sm" name="status" onchange="this.form.submit()">
        @foreach(['pending' => 'قيد المراجعة','approved' => 'معتمَد','rejected' => 'مرفوض'] as $val => $label)
          <option value="{{ $val }}" @selected($status === $val)>{{ $label }}</option>
        @endforeach
      </select>
    </form>

    <div class="a2-tablewrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>#</th>
            <th>{{ __('التاجر') }}</th>
            <th>{{ __('ملاحظة التاجر') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('التاريخ') }}</th>
            <th>{{ __('إجراء') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $r)
            <tr>
              <td>{{ $r->id }}</td>
              <td>{{ optional($r->business)->name ?? ('#'.$r->business_id) }} <span class="a2-hint" dir="ltr">#{{ $r->business_id }}</span></td>
              <td>{{ $r->note ?: '—' }}</td>
              <td><span class="a2-badge a2-badge-{{ $r->status === 'approved' ? 'success' : ($r->status === 'rejected' ? 'danger' : 'muted') }}">{{ $r->status }}</span></td>
              <td dir="ltr">{{ optional($r->created_at)->format('Y-m-d H:i') }}</td>
              <td>
                @if($r->isPending())
                  <details>
                    <summary class="a2-btn a2-btn-primary" style="cursor:pointer;display:inline-block">{{ __('اعتماد + تفعيل') }}</summary>
                    <form method="post" action="{{ route('admin.merchant-account-requests.approve', $r->id) }}" style="margin-top:10px;max-width:360px">
                      @csrf
                      <input class="a2-input" name="merchant_code" type="text" dir="ltr" placeholder="Merchant Code" required style="margin-bottom:8px">
                      <input class="a2-input" name="security_key" type="password" dir="ltr" autocomplete="new-password" placeholder="Security Key" required style="margin-bottom:8px">
                      <button class="a2-btn a2-btn-primary" type="submit">{{ __('اعتماد') }}</button>
                    </form>
                  </details>
                  <form method="post" action="{{ route('admin.merchant-account-requests.reject', $r->id) }}" style="margin-top:8px">
                    @csrf
                    <button class="a2-btn a2-btn-ghost" type="submit">{{ __('رفض') }}</button>
                  </form>
                @else
                  <span class="a2-hint">{{ $r->decision_note ?: '—' }}</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="a2-empty">{{ __('لا توجد طلبات.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="a2-pager">{{ $rows->links() }}</div>
  </div>
</div>
@endsection
