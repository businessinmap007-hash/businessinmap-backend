@extends('admin-v2.layouts.master')

@section('title','المعاملات المالية')
@section('body_class','admin-v2 admin-v2-wallet-transactions-index')

@section('content')
@php
    $qVal = (string)($q ?? '');
    $filterVal = (string)($filter ?? '');
    $noteIdVal = (int)($noteId ?? 0);

    $payerVal = (string)($payer ?? '');
    $feeCodeVal = (string)($feeCode ?? '');
    $referenceTypeVal = (string)($referenceType ?? '');
    $referenceIdVal = (string)($referenceId ?? '');
    $bookingIdVal = (int)($bookingId ?? 0);

    $perPageVal = (int)($perPage ?? 50);
    $sortNow = (string)($sort ?? 'id');
    $dirNow  = (string)($dir ?? 'desc');

    $filterOptions = [
        '' => 'الحالة أو النوع',
        'st:pending' => 'Pending',
        'st:completed' => 'Completed',
        'st:failed' => 'Failed',
        'st:reversed' => 'Reversed',
        'dir:in' => 'In',
        'dir:out' => 'Out',
        'tp:deposit' => 'Deposit',
        'tp:withdraw' => 'Withdraw',
        'tp:hold' => 'Hold',
        'tp:release' => 'Release',
        'tp:refund' => 'Refund',
        'tp:platform_fee' => 'Platform Fee',
        'combo:deposit_in' => 'Deposit IN',
        'combo:withdraw_out' => 'Withdraw OUT',
        'combo:booking_fees' => 'Booking Fees',
        'combo:platform_fee_out' => 'Platform Fee OUT',
    ];

    $perPageOptions = [10,20,50,100];

    $qsKeep = [
        'q' => $qVal,
        'filter' => $filterVal,
        'note_id' => $noteIdVal,
        'payer' => $payerVal,
        'fee_code' => $feeCodeVal,
        'reference_type' => $referenceTypeVal,
        'reference_id' => $referenceIdVal,
        'booking_id' => $bookingIdVal,
        'per_page' => $perPageVal,
        'sort' => $sortNow,
        'dir' => $dirNow,
    ];
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">المعاملات المالية</h1>
            <div class="a2-page-subtitle">
                متابعة كل حركات المحافظ، وخصوصًا رسوم تنفيذ الحجوزات المسجلة كـ
                <span dir="ltr">platform_fee</span>.
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="a2-alert a2-alert-danger">{{ session('error') }}</div>
    @endif

    <div class="a2-stat-grid">
        <div class="a2-stat-card">
            <div class="a2-stat-label">عدد النتائج</div>
            <div class="a2-stat-value">{{ number_format((int)($totals['count'] ?? 0)) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">إجمالي الداخل</div>
            <div class="a2-stat-value">{{ number_format((float)($totals['sum_in'] ?? 0), 2) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">إجمالي الخارج</div>
            <div class="a2-stat-value">{{ number_format((float)($totals['sum_out'] ?? 0), 2) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">صافي الحركة</div>
            <div class="a2-stat-value">{{ number_format((float)($totals['net'] ?? 0), 2) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">Platform Fees</div>
            <div class="a2-stat-value">{{ number_format((float)($totals['platform_fees'] ?? 0), 2) }}</div>
        </div>
    </div>

    <div class="a2-card a2-mt-16">
        <form method="GET" action="{{ route('admin.wallet-transactions.index') }}" class="a2-filterbar">
            <div class="a2-filter-search">
                <label class="a2-label">بحث</label>
                <input
                    class="a2-input"
                    name="q"
                    value="{{ $qVal }}"
                    placeholder="ID / user / wallet / note / fee_code / payer"
                >
            </div>

            <div class="a2-filter-md">
                <label class="a2-label">فلتر</label>
                <select class="a2-select" name="filter">
                    @foreach($filterOptions as $k => $label)
                        <option value="{{ $k }}" @selected($filterVal === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="a2-filter-md">
                <label class="a2-label">Note Template</label>
                <select class="a2-select" name="note_id">
                    <option value="0">الكل</option>
                    @foreach($notesOptions as $opt)
                        <option value="{{ $opt->id }}" @selected($noteIdVal === (int)$opt->id)>
                            {{ $opt->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">Payer</label>
                <select class="a2-select" name="payer">
                    <option value="">الكل</option>
                    <option value="client" @selected($payerVal === 'client')>Client</option>
                    <option value="business" @selected($payerVal === 'business')>Business</option>
                </select>
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">Fee Code</label>
                <input
                    class="a2-input"
                    name="fee_code"
                    value="{{ $feeCodeVal }}"
                    placeholder="booking_execution"
                    dir="ltr"
                >
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">Booking ID</label>
                <input
                    class="a2-input"
                    name="booking_id"
                    value="{{ $bookingIdVal ?: '' }}"
                    placeholder="Booking ID"
                    dir="ltr"
                >
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">Reference Type</label>
                <input
                    class="a2-input"
                    name="reference_type"
                    value="{{ $referenceTypeVal }}"
                    placeholder="booking"
                    dir="ltr"
                >
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">Reference ID</label>
                <input
                    class="a2-input"
                    name="reference_id"
                    value="{{ $referenceIdVal }}"
                    placeholder="ID"
                    dir="ltr"
                >
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">Per Page</label>
                <select class="a2-select" name="per_page">
                    @foreach($perPageOptions as $n)
                        <option value="{{ $n }}" @selected($perPageVal === (int)$n)>{{ $n }}</option>
                    @endforeach
                </select>
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">Sort</label>
                <select class="a2-select" name="sort">
                    @foreach(['id','wallet_id','type','direction','amount','status','created_at'] as $col)
                        <option value="{{ $col }}" @selected($sortNow === $col)>{{ $col }}</option>
                    @endforeach
                </select>
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">Dir</label>
                <select class="a2-select" name="dir">
                    <option value="desc" @selected($dirNow === 'desc')>DESC</option>
                    <option value="asc" @selected($dirNow === 'asc')>ASC</option>
                </select>
            </div>

            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.wallet-transactions.index') }}">تفريغ</a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-mt-16">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                <tr>
                    <th style="min-width:80px;">ID</th>
                    <th style="min-width:180px;">User</th>
                    <th style="min-width:120px;">Type</th>
                    <th style="min-width:80px;">Dir</th>
                    <th style="min-width:120px;" class="a2-text-center">Amount</th>
                    <th style="min-width:110px;">Status</th>
                    <th style="min-width:180px;">Booking/Fee</th>
                    <th style="min-width:180px;">Meta</th>
                    <th style="min-width:220px;">Note</th>
                    <th style="min-width:150px;">Created</th>
                    <th style="min-width:110px;">Actions</th>
                </tr>
                </thead>

                <tbody>
                @forelse($items as $tx)
                    @php
                        $userName = (string)($tx->user->name ?? '');
                        $userLabel = $userName !== '' ? $userName : ('#' . $tx->user_id);

                        $userTxUrl = route('admin.wallet-transactions.user', ['user' => $tx->user_id] + $qsKeep);

                        $noteTxt = (string)($tx->note ?? '');
                        $payerTxt = method_exists($tx, 'payer') ? ($tx->payer() ?: '—') : data_get($tx->meta, 'payer', '—');
                        $feeCodeTxt = method_exists($tx, 'feeCode') ? ($tx->feeCode() ?: '—') : data_get($tx->meta, 'fee_code', '—');
                        $bookingIdTxt = method_exists($tx, 'bookingId') ? ($tx->bookingId() ?: null) : data_get($tx->meta, 'booking_id');
                        $feeRowIdTxt = method_exists($tx, 'categoryChildServiceFeeId') ? ($tx->categoryChildServiceFeeId() ?: null) : data_get($tx->meta, 'category_child_service_fee_id');
                    @endphp

                    <tr>
                        <td>
                            <div class="a2-fw-900">{{ $tx->id }}</div>
                            <div class="a2-muted a2-mt-8">W: {{ $tx->wallet_id }}</div>
                        </td>

                        <td>
                            @if($tx->user_id)
                                <a class="a2-link" href="{{ $userTxUrl }}" title="{{ $userLabel }}">
                                    {{ $userLabel }}
                                </a>
                                <div class="a2-muted a2-mt-8">ID: {{ $tx->user_id }}</div>
                            @else
                                —
                            @endif
                        </td>

                        <td>
                            <span class="a2-pill a2-pill-gray">{{ $tx->type }}</span>
                        </td>

                        <td>
                            @if($tx->direction === 'in')
                                <span class="a2-pill a2-pill-success">IN</span>
                            @else
                                <span class="a2-pill a2-pill-danger">OUT</span>
                            @endif
                        </td>

                        <td class="a2-text-center a2-fw-900">
                            {{ number_format((float)$tx->amount, 2) }}
                        </td>

                        <td>
                            <span class="a2-pill {{ $tx->status === 'completed' ? 'a2-pill-success' : 'a2-pill-gray' }}">
                                {{ $tx->status }}
                            </span>
                        </td>

                        <td>
                            @if($bookingIdTxt)
                                <div>
                                    <a class="a2-link" href="{{ route('admin.bookings.show', $bookingIdTxt) }}">
                                        Booking #{{ $bookingIdTxt }}
                                    </a>
                                </div>
                            @else
                                <div class="a2-muted">Booking: —</div>
                            @endif

                            <div class="a2-muted a2-mt-8" dir="ltr">
                                {{ $feeCodeTxt }}
                            </div>

                            @if($feeRowIdTxt)
                                <div class="a2-muted">Fee Row: {{ $feeRowIdTxt }}</div>
                            @endif
                        </td>

                        <td>
                            <div>
                                <span class="a2-pill a2-pill-gray">
                                    {{ $payerTxt }}
                                </span>
                            </div>

                            <div class="a2-muted a2-mt-8">
                                Ref: {{ $tx->reference_type ?: '—' }} / {{ $tx->reference_id ?: '—' }}
                            </div>

                            @if(!empty($tx->idempotency_key))
                                <div class="a2-muted a2-mt-8" dir="ltr" title="{{ $tx->idempotency_key }}">
                                    {{ \Illuminate\Support\Str::limit($tx->idempotency_key, 28) }}
                                </div>
                            @endif
                        </td>

                        <td class="a2-text-right">
                            @if($tx->noteTemplate)
                                <div class="a2-fw-900">{{ $tx->noteTemplate->title }}</div>
                            @endif

                            <div class="a2-muted a2-mt-8" title="{{ $noteTxt }}">
                                {{ $noteTxt !== '' ? \Illuminate\Support\Str::limit($noteTxt, 60) : '—' }}
                            </div>
                        </td>

                        <td dir="ltr">
                            {{ $tx->created_at ? $tx->created_at->format('Y-m-d H:i') : '—' }}
                        </td>

                        <td>
                            <a href="{{ route('admin.wallet-transactions.show', $tx) }}" class="a2-btn a2-btn-sm a2-btn-ghost">
                                عرض
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="a2-empty-cell">لا يوجد بيانات</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($items, 'links'))
            <div class="a2-paginate">{{ $items->links() }}</div>
        @endif
    </div>
</div>
@endsection