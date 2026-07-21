@extends('admin-v2.layouts.master')

@section('title','Fines')
@section('body_class','admin-v2-fines')

@section('content')
<div class="a2-page">
    <div class="a2-card" style="padding:14px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
            <div>
                <div class="a2-title" style="font-size:16px;margin-bottom:4px;">{{ __('غرامات المنصة') }}</div>
                <div class="a2-hint">{{ __('غرامات احتيال/إساءة تُفرض من طرف واحد: يُجمَّد المبلغ، تُفتح نافذة اعتراض، ثم يُخصم بعد إغلاقها. لا يُخصم من هنا — تفعله المهمة المجدولة.') }}</div>
            </div>
            <a class="a2-btn a2-btn-primary" href="{{ route('admin.fines.create') }}">{{ __('فرض غرامة') }}</a>
        </div>

        @if(session('status'))
            <div class="a2-alert a2-alert-success" style="margin-top:12px;">{{ session('status') }}</div>
        @endif

        <form method="GET" style="margin-top:12px;display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
            <div class="a2-form-group" style="min-width:180px;">
                <label class="a2-label">{{ __('الحالة') }}</label>
                <select class="a2-select" name="status" onchange="this.form.submit()">
                    <option value="">{{ __('الكل') }}</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s }}" @selected($status === $s)>{{ \App\Models\Fine::statusLabels()[$s] ?? $s }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        <div style="overflow-x:auto;margin-top:12px;">
            <table class="a2-table" style="width:100%;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('المستخدم') }}</th>
                        <th>{{ __('القيمة') }}</th>
                        <th>{{ __('المجمّد') }}</th>
                        <th>{{ __('الحالة') }}</th>
                        <th>{{ __('نهاية الاعتراض') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fines as $fine)
                        <tr>
                            <td style="font-weight:800;">{{ $fine->id }}</td>
                            <td>
                                {{ $fine->user?->name ?: '—' }}
                                <div class="a2-hint">{{ $fine->user?->phone ?: $fine->user?->email }}</div>
                            </td>
                            <td style="font-weight:800;">{{ number_format((float) $fine->amount, 2) }}</td>
                            <td>
                                {{ number_format((float) $fine->frozen_amount, 2) }}
                                @if($fine->shortfall() > 0)
                                    <span class="a2-badge a2-badge-danger">{{ __('نقص') }} {{ number_format($fine->shortfall(), 2) }}</span>
                                @endif
                            </td>
                            <td><span class="a2-badge">{{ $fine->statusLabel() }}</span></td>
                            <td class="a2-hint">{{ optional($fine->appeal_deadline_at)->format('Y-m-d H:i') ?: '—' }}</td>
                            <td><a class="a2-btn a2-btn-sm" href="{{ route('admin.fines.show', $fine->id) }}">{{ __('عرض') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="a2-hint" style="text-align:center;padding:18px;">{{ __('لا توجد غرامات.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:12px;">{{ $fines->links() }}</div>
    </div>
</div>
@endsection
