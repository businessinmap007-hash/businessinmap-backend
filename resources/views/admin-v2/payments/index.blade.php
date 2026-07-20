@extends('admin-v2.layouts.master')

@section('title', 'Payments')
@section('topbar_title', 'Payments')
@section('body_class', 'admin-v2-payments')

@section('content')
@php
    $statusClass = function ($payment) {
        return $payment && $payment->paid_at ? 'a2-pill-success' : 'a2-pill-warning';
    };
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('المدفوعات') }}</h1>
            <div class="a2-page-subtitle">{{ __('متابعة عمليات الدفع والشحن والاشتراكات وتأكيد العمليات المدفوعة.') }}</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.wallet-ops.recharge.form') }}" class="a2-btn a2-btn-primary">{{ __('شحن محفظة') }}</a>
            <a href="{{ route('admin.wallet-transactions.index') }}" class="a2-btn a2-btn-ghost">Wallet Transactions</a>
        </div>
    </div>

    <div class="a2-card a2-card--tight">
        <form method="GET" action="{{ route('admin.payments.index') }}" class="a2-filterbar">
            <input
                class="a2-input a2-filter-search"
                type="search"
                name="q"
                value="{{ $q ?? '' }}"
                placeholder="{{ __('بحث برقم الدفع / المستخدم / رقم العملية / نوع الدفع / نوع العملية') }}"
            >

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">{{ __('بحث') }}</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.payments.index') }}">{{ __('إعادة ضبط') }}</a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-card--tight">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('المستخدم') }}</th>
                        <th>{{ __('السعر') }}</th>
                        <th>{{ __('نوع الدفع') }}</th>
                        <th>{{ __('رقم العملية') }}</th>
                        <th>{{ __('نوع العملية') }}</th>
                        <th>Operation ID</th>
                        <th>{{ __('الحالة') }}</th>
                        <th>Paid At</th>
                        <th>Created</th>
                        <th>{{ __('إجراءات') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $payment)
                        @php
                            $user = $payment->user;
                            $isPaid = (bool) $payment->paid_at;
                        @endphp
                        <tr>
                            <td>{{ $payment->id }}</td>
                            <td class="a2-text-right">
                                <div class="a2-fw-900 a2-clip a2-clip--name">{{ optional($user)->name ?: '—' }}</div>
                                <div class="a2-muted">#{{ $payment->user_id }}</div>
                            </td>
                            <td>{{ number_format((float) $payment->price, 2) }}</td>
                            <td>{{ $payment->payment_type ?: '—' }}</td>
                            <td>{{ $payment->payment_no ?: '—' }}</td>
                            <td><span class="a2-pill a2-pill-gray">{{ $payment->operation_type ?: '—' }}</span></td>
                            <td>{{ $payment->operation_id ?: '—' }}</td>
                            <td>
                                <span class="a2-pill {{ $statusClass($payment) }}">
                                    {{ $isPaid ? 'مدفوع' : 'غير مدفوع' }}
                                </span>
                            </td>
                            <td>{{ $payment->paid_at ? $payment->paid_at->format('Y-m-d H:i') : '—' }}</td>
                            <td>{{ $payment->created_at ? $payment->created_at->format('Y-m-d H:i') : '—' }}</td>
                            <td>
                                <div class="a2-actions">
                                    @if($user)
                                        <a href="{{ route('admin.users.show', $user->id) }}" class="a2-btn a2-btn-sm a2-btn-ghost">{{ __('المستخدم') }}</a>
                                    @endif

                                    @if($isPaid)
                                        <form method="POST" action="{{ route('admin.payments.confirm', $payment->id) }}">
                                            @csrf
                                            <button class="a2-btn a2-btn-sm a2-btn-success" type="submit" onclick="return confirm('تأكيد الدفع وتنفيذ أثر العملية؟')">{{ __('تأكيد') }}</button>
                                        </form>
                                    @else
                                        <span class="a2-pill a2-pill-gray">{{ __('بانتظار الدفع') }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="a2-empty-cell">{{ __('لا توجد مدفوعات مطابقة.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
