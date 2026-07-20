@extends('admin-v2.layouts.master')

@section('title','Arbitrator record')
@section('body_class','admin-v2-arbitrators')

@section('content')
<div class="a2-page">
    <div class="a2-card" style="padding:14px;">
        <div class="a2-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div>
                <div class="a2-title" style="font-size:16px;">{{ $user->name }}</div>
                <div class="a2-hint">{{ $user->email }}</div>
            </div>
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.arbitrators.index') }}">{{ __('رجوع') }}</a>
        </div>

        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:14px;">
            <div>
                <div class="a2-hint">{{ __('عدد الجلسات') }}</div>
                <div style="font-weight:800;">{{ $stats['sessions'] }}</div>
            </div>
            <div>
                <div class="a2-hint">{{ __('رسوم التحكيم المحصّلة') }}</div>
                <div style="font-weight:800;">{{ number_format($stats['fees_earned'], 2) }}</div>
            </div>
            <div>
                <div class="a2-hint">{{ __('الغرامات المحصّلة') }}</div>
                <div style="font-weight:800;">{{ number_format($stats['fines_collected'], 2) }}</div>
            </div>
            <div>
                <div class="a2-hint">{{ __('جلسات قيد النظر') }}</div>
                <div style="font-weight:800;">{{ $stats['open_sessions'] }}</div>
            </div>
            <div>
                <div class="a2-hint">{{ __('حُوّل للعملاء') }}</div>
                <div style="font-weight:800;">{{ number_format($stats['moved_to_clients'], 2) }}</div>
            </div>
            <div>
                <div class="a2-hint">{{ __('حُوّل للأنشطة') }}</div>
                <div style="font-weight:800;">{{ number_format($stats['moved_to_businesses'], 2) }}</div>
            </div>
        </div>
    </div>

    <div class="a2-card" style="padding:14px;margin-top:14px;">
        <div class="a2-title" style="font-size:15px;margin-bottom:10px;">{{ __('الجلسات') }}</div>

        <div style="overflow-x:auto;">
            <table class="a2-table" style="width:100%;">
                <thead>
                    <tr>
                        <th>{{ __('النزاع') }}</th>
                        <th>{{ __('النتيجة') }}</th>
                        <th>{{ __('للعميل') }}</th>
                        <th>{{ __('للنشاط') }}</th>
                        <th>{{ __('غرامة المنصة') }}</th>
                        <th>{{ __('التاريخ') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                        <tr>
                            <td>
                                <a href="{{ route('admin.disputes.show', $session->dispute_id) }}">#{{ $session->dispute_id }}</a>
                                <div class="a2-hint">{{ $session->dispute?->reason_code ?: '-' }}</div>
                            </td>
                            <td style="font-weight:700;">
                                {{ $session->outcome }}
                                @if($session->outcome === 'split')
                                    <div class="a2-hint">
                                        {{ (float) $session->client_percent }}% / {{ (float) $session->business_percent }}%
                                    </div>
                                @endif
                            </td>
                            <td>{{ number_format((float) $session->amount_to_client, 2) }}</td>
                            <td>{{ number_format((float) $session->amount_to_business, 2) }}</td>
                            <td>
                                {{ number_format((float) $session->platform_fine_amount, 2) }}
                                @if($session->platform_fine_on)
                                    <span class="a2-hint">({{ $session->platform_fine_on }})</span>
                                @endif
                            </td>
                            <td class="a2-hint">{{ optional($session->created_at)->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="a2-hint">{{ __('لا توجد جلسات بعد.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:10px;">{{ $sessions->links() }}</div>
    </div>
</div>
@endsection
