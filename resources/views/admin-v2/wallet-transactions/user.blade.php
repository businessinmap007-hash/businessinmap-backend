@extends('admin-v2.layouts.master')

@section('title','معاملات المستخدم')
@section('body_class','admin-v2 admin-v2-wallet-transactions-user')

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

    $perPageVal = (int)($perPage ?? 10);
    $sortNow = (string)($sort ?? 'id');
    $dirNow = (string)($dir ?? 'desc');

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

    $sortUrl = function(string $col) use ($qsKeep, $sortNow, $dirNow, $user) {
        $nextDir = ($sortNow === $col && $dirNow === 'asc') ? 'desc' : 'asc';

        return route('admin.wallet-transactions.user', ['user' => $user->id] + array_merge($qsKeep, [
            'sort' => $col,
            'dir' => $nextDir,
        ]));
    };

    $arrow = function(string $col) use ($sortNow, $dirNow) {
        if ($sortNow !== $col) {
            return '';
        }

        return $dirNow === 'asc' ? ' ▲' : ' ▼';
    };

    $sumIn = (float)($totals['sum_in'] ?? 0);
    $sumOut = (float)($totals['sum_out'] ?? 0);
    $net = (float)($totals['net'] ?? 0);
    $countAll = (int)($totals['count'] ?? 0);
    $platformFees = (float)($totals['platform_fees'] ?? 0);

    $balanceNow = (float)($balance ?? 0);
    $lockedNow = (float)($locked ?? 0);
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">معاملات المستخدم</h1>
            <div class="a2-page-subtitle">
                {{ $user->name ?: ('User #' . $user->id) }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.wallet-transactions.index') }}">
                رجوع للمعاملات
            </a>

            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.users.show', $user) }}">
                بيانات المستخدم
            </a>
        </div>
    </div>

    <div class="a2-stat-grid">
        <div class="a2-stat-card">
            <div class="a2-stat-label">عدد الحركات</div>
            <div class="a2-stat-value">{{ number_format($countAll) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">IN</div>
            <div class="a2-stat-value">{{ number_format($sumIn, 2) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">OUT</div>
            <div class="a2-stat-value">{{ number_format($sumOut, 2) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">NET</div>
            <div class="a2-stat-value">{{ number_format($net, 2) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">الرصيد الحالي</div>
            <div class="a2-stat-value">{{ number_format($balanceNow, 2) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">Locked</div>
            <div class="a2-stat-value">{{ number_format($lockedNow, 2) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">Platform Fees</div>
            <div class="a2-stat-value">{{ number_format($platformFees, 2) }}</div>
        </div>
    </div>

    <div class="a2-card a2-mt-16">
        <form method="GET" action="{{ route('admin.wallet-transactions.user', ['user' => $user->id]) }}" class="a2-filterbar">
            <div class="a2-filter-search">
                <label class="a2-label">بحث</label>
                <input
                    class="a2-input"
                    name="q"
                    value="{{ $qVal }}"
                    placeholder="ID / wallet_id / type / note / fee_code"
                >
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

            <div class="a2-filter-md">
                <label class="a2-label">فلتر</label>
                <select class="a2-select" name="filter">
                    @foreach($filterOptions as $k => $label)
                        <option value="{{ $k }}" @selected((string)$filterVal === (string)$k)>
                            {{ $label }}
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
                        <option value="{{ $n }}" @selected((int)$perPageVal === (int)$n)>
                            {{ $n }}
                        </option>
                    @endforeach
                </select>
            </div>

            <input type="hidden" name="sort" value="{{ $sortNow }}">
            <input type="hidden" name="dir" value="{{ $dirNow }}">

            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.wallet-transactions.user', ['user' => $user->id]) }}">
                    تفريغ
                </a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-mt-16">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                <tr>
                    <th style="min-width:80px;">
                        <a class="a2-link" href="{{ $sortUrl('id') }}">ID{!! $arrow('id') !!}</a>
                    </th>
                    <th style="min-width:120px;">Type</th>
                    <th style="min-width:120px;" class="a2-text-center">IN</th>
                    <th style="min-width:120px;" class="a2-text-center">OUT</th>
                    <th style="min-width:120px;">Status</th>
                    <th style="min-width:180px;">Booking/Fee</th>
                    <th style="min-width:180px;">Meta</th>
                    <th style="min-width:240px;">Notes</th>
                    <th style="min-width:160px;">
                        <a class="a2-link" href="{{ $sortUrl('created_at') }}">Created{!! $arrow('created_at') !!}</a>
                    </th>
                    <th style="min-width:100px;">Actions</th>
                </tr>
                </thead>

                <tbody>
                @forelse($items as $tx)
                    @php
                        $inVal = ($tx->direction === 'in') ? (float)$tx->amount : 0.0;
                        $outVal = ($tx->direction === 'out') ? (float)$tx->amount : 0.0;
                        $noteTxt = (string)($tx->note ?? '');

                        $showUrl = route('admin.wallet-transactions.show', ['walletTransaction' => $tx->id] + $qsKeep);

                        $payerTxt = method_exists($tx, 'payer') ? ($tx->payer() ?: '—') : data_get($tx->meta, 'payer', '—');
                        $feeCodeTxt = method_exists($tx, 'feeCode') ? ($tx->feeCode() ?: '—') : data_get($tx->meta, 'fee_code', '—');
                        $bookingIdTxt = method_exists($tx, 'bookingId') ? ($tx->bookingId() ?: null) : data_get($tx->meta, 'booking_id');
                        $feeRowIdTxt = method_exists($tx, 'categoryChildServiceFeeId') ? ($tx->categoryChildServiceFeeId() ?: null) : data_get($tx->meta, 'category_child_service_fee_id');
                    @endphp

                    <tr>
                        <td>
                            <a class="a2-link a2-fw-900" href="{{ $showUrl }}">{{ $tx->id }}</a>
                        </td>

                        <td>
                            <span class="a2-pill a2-pill-gray">{{ $tx->type }}</span>
                        </td>

                        <td class="a2-text-center a2-fw-900">
                            {{ number_format($inVal, 2) }}
                        </td>

                        <td class="a2-text-center a2-fw-900">
                            {{ number_format($outVal, 2) }}
                        </td>

                        <td>
                            <span class="a2-pill {{ $tx->status === 'completed' ? 'a2-pill-success' : 'a2-pill-gray' }}">
                                {{ $tx->status }}
                            </span>
                        </td>

                        <td>
                            @if($bookingIdTxt)
                                <a class="a2-link" href="{{ route('admin.bookings.show', $bookingIdTxt) }}">
                                    Booking #{{ $bookingIdTxt }}
                                </a>
                            @else
                                <span class="a2-muted">Booking: —</span>
                            @endif

                            <div class="a2-muted a2-mt-8" dir="ltr">
                                {{ $feeCodeTxt }}
                            </div>

                            @if($feeRowIdTxt)
                                <div class="a2-muted">Fee Row: {{ $feeRowIdTxt }}</div>
                            @endif
                        </td>

                        <td>
                            <span class="a2-pill a2-pill-gray">{{ $payerTxt }}</span>

                            <div class="a2-muted a2-mt-8">
                                Ref: {{ $tx->reference_type ?: '—' }} / {{ $tx->reference_id ?: '—' }}
                            </div>
                        </td>

                        <td class="a2-text-right" title="{{ $noteTxt }}">
                            @if($tx->noteTemplate)
                                <div class="a2-fw-900">{{ $tx->noteTemplate->title }}</div>
                            @endif

                            <div class="a2-muted a2-mt-8">
                                {{ $noteTxt !== '' ? \Illuminate\Support\Str::limit($noteTxt, 70) : '—' }}
                            </div>
                        </td>

                        <td dir="ltr">
                            {{ $tx->created_at ? \Carbon\Carbon::parse($tx->created_at)->format('Y-m-d H:i') : '—' }}
                        </td>

                        <td>
                            <a class="a2-btn a2-btn-sm a2-btn-ghost" href="{{ $showUrl }}">
                                عرض
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="a2-empty-cell">لا يوجد بيانات</td>
                    </tr>
                @endforelse
                </tbody>

                <tfoot>
                <tr>
                    <td class="a2-fw-900">{{ $countAll }}</td>
                    <td class="a2-fw-900">Totals</td>
                    <td class="a2-text-center a2-fw-900">{{ number_format($sumIn, 2) }}</td>
                    <td class="a2-text-center a2-fw-900">{{ number_format($sumOut, 2) }}</td>
                    <td class="a2-fw-900">NET: {{ number_format($net, 2) }}</td>
                    <td class="a2-fw-900">Platform Fees: {{ number_format($platformFees, 2) }}</td>
                    <td class="a2-fw-900">Balance: {{ number_format($balanceNow, 2) }}</td>
                    <td class="a2-fw-900">Locked: {{ number_format($lockedNow, 2) }}</td>
                    <td colspan="2"></td>
                </tr>
                </tfoot>
            </table>
        </div>

        @if(method_exists($items, 'links'))
            <div class="a2-paginate">{{ $items->links() }}</div>
        @endif
    </div>
</div>
@endsection