@extends('admin-v2.layouts.master')

@section('title','Fraud review')
@section('body_class','admin-v2-fraud-flags')

@php
    $reasonLabels = ['disputed_ratio' => 'نسبة نزاعات مرتفعة', 'cancelled_ratio' => 'نسبة إلغاء مرتفعة'];
@endphp

@section('content')
<div class="a2-page">
    <div class="a2-card" style="padding:14px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
            <div>
                <div class="a2-title" style="font-size:16px;margin-bottom:4px;">{{ __('مراجعة الاشتباه بالاحتيال') }}</div>
                <div class="a2-hint">{{ __('حسابات نسبة نزاعاتها أو إلغائها مرتفعة في سجل العمليات. اقتراح فقط — تُقرِّر أنت: فرض غرامة أو إيقاف، أو صرف البلاغ.') }}</div>
            </div>
            <form method="POST" action="{{ route('admin.fraud-flags.scan') }}">
                @csrf
                <button class="a2-btn a2-btn-primary" type="submit">{{ __('تشغيل الفحص الآن') }}</button>
            </form>
        </div>

        @if(session('status'))
            <div class="a2-alert a2-alert-success" style="margin-top:12px;">{{ session('status') }}</div>
        @endif

        <form method="GET" style="margin-top:12px;">
            <select class="a2-select" name="status" onchange="this.form.submit()" style="max-width:220px;">
                <option value="open" @selected($status === 'open')>{{ __('مفتوحة') }}</option>
                <option value="dismissed" @selected($status === 'dismissed')>{{ __('مصروفة') }}</option>
                <option value="all" @selected($status === 'all')>{{ __('الكل') }}</option>
            </select>
        </form>

        <div style="overflow-x:auto;margin-top:12px;">
            <table class="a2-table" style="width:100%;">
                <thead>
                    <tr>
                        <th>{{ __('المستخدم') }}</th>
                        <th>{{ __('الخطورة') }}</th>
                        <th>{{ __('نزاعات') }}</th>
                        <th>{{ __('إلغاء') }}</th>
                        <th>{{ __('العمليات') }}</th>
                        <th>{{ __('الأسباب') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($flags as $flag)
                        <tr>
                            <td>
                                <a href="{{ route('admin.users.show', $flag->user_id) }}" style="font-weight:800;">
                                    {{ $flag->user?->name ?: ('#'.$flag->user_id) }}
                                </a>
                                @if($flag->user?->banned_at)<span class="a2-badge a2-badge-danger">{{ __('موقوف') }}</span>@endif
                                <div class="a2-hint">{{ $flag->user?->phone ?: $flag->user?->email }}</div>
                            </td>
                            <td style="font-weight:800;">{{ number_format((float) $flag->score * 100, 0) }}%</td>
                            <td>{{ number_format((float) $flag->disputed_ratio * 100, 0) }}%</td>
                            <td>{{ number_format((float) $flag->cancelled_ratio * 100, 0) }}%</td>
                            <td>{{ $flag->total_operations }}</td>
                            <td>
                                @foreach((array) $flag->reasons as $r)
                                    <span class="a2-badge">{{ $reasonLabels[$r] ?? $r }}</span>
                                @endforeach
                            </td>
                            <td style="white-space:nowrap;">
                                <a class="a2-btn a2-btn-sm" href="{{ route('admin.fines.create', ['user_id' => $flag->user_id]) }}">{{ __('غرامة') }}</a>
                                @if($flag->status === 'open')
                                    <form method="POST" action="{{ route('admin.fraud-flags.dismiss', $flag->id) }}" style="display:inline;"
                                          onsubmit="return confirm('{{ __('صرف البلاغ كإيجابية كاذبة؟') }}')">
                                        @csrf
                                        <button class="a2-btn a2-btn-sm a2-btn-ghost">{{ __('صرف') }}</button>
                                    </form>
                                @else
                                    <span class="a2-badge a2-badge-muted">{{ __('مصروف') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="a2-hint" style="text-align:center;padding:18px;">{{ __('لا بلاغات.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:12px;">{{ $flags->links() }}</div>
    </div>
</div>
@endsection
