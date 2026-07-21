@extends('admin-v2.layouts.master')

@section('title','Fine')
@section('body_class','admin-v2-fines-show')

@php $pendingAppeal = $fine->appeals->firstWhere('status', 'pending'); @endphp

@section('content')
<div class="a2-page">
    @if(session('status'))
        <div class="a2-alert a2-alert-success" style="margin-bottom:12px;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="a2-alert a2-alert-danger" style="margin-bottom:12px;">{{ $errors->first() }}</div>
    @endif

    <div class="a2-card" style="padding:14px;margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
            <div class="a2-title" style="font-size:16px;">{{ __('غرامة') }} #{{ $fine->id }}</div>
            <span class="a2-badge">{{ $fine->statusLabel() }}</span>
        </div>
        <div class="a2-form-grid" style="margin-top:12px;">
            <div><div class="a2-hint">{{ __('المستخدم') }}</div><div style="font-weight:800;">{{ $fine->user?->name }} <span class="a2-hint">#{{ $fine->user_id }} · {{ $fine->user?->phone ?: $fine->user?->email }}</span></div></div>
            <div><div class="a2-hint">{{ __('القيمة') }}</div><div style="font-weight:800;">{{ number_format((float) $fine->amount, 2) }}</div></div>
            <div><div class="a2-hint">{{ __('المجمّد') }}</div><div style="font-weight:800;">{{ number_format((float) $fine->frozen_amount, 2) }}
                @if($fine->shortfall() > 0)<span class="a2-badge a2-badge-danger">{{ __('نقص') }} {{ number_format($fine->shortfall(), 2) }}</span>@endif
            </div></div>
            <div><div class="a2-hint">{{ __('المحصّل') }}</div><div style="font-weight:800;">{{ number_format((float) $fine->collected_amount, 2) }}</div></div>
            <div><div class="a2-hint">{{ __('قابلة للاعتراض') }}</div><div>{{ $fine->is_appealable ? __('نعم') : __('لا') }}</div></div>
            <div><div class="a2-hint">{{ __('نهاية الاعتراض') }}</div><div>{{ optional($fine->appeal_deadline_at)->format('Y-m-d H:i') ?: '—' }}</div></div>
        </div>
        <div style="margin-top:10px;"><div class="a2-hint">{{ __('السبب') }}</div><div>{{ $fine->reason }}</div></div>

        {{-- Fine → ban bridge: act on the account from the fine context. --}}
        <div style="margin-top:12px;border-top:1px solid var(--a2-border,#eee);padding-top:10px;">
            @if($fine->user?->banned_at)
                <span class="a2-badge a2-badge-danger">{{ __('الحساب موقوف') }}</span>
                <form method="POST" action="{{ route('admin.users.unban', $fine->user_id) }}" style="display:inline;margin-inline-start:8px;"
                      onsubmit="return confirm('{{ __('رفع الإيقاف عن الحساب. متابعة؟') }}')">
                    @csrf
                    <button class="a2-btn a2-btn-sm">{{ __('رفع الإيقاف') }}</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.users.ban', $fine->user_id) }}" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;"
                      onsubmit="return confirm('{{ __('إيقاف صاحب الغرامة وإلغاء جلساته. متابعة؟') }}')">
                    @csrf
                    <input type="hidden" name="reason" value="{{ $fine->reason }}">
                    <button class="a2-btn a2-btn-sm a2-btn-danger">{{ __('إيقاف صاحب الغرامة') }}</button>
                    <span class="a2-hint">{{ __('يستخدم سبب الغرامة نفسه.') }}</span>
                </form>
            @endif
        </div>
    </div>

    {{-- Appeals --}}
    <div class="a2-card" style="padding:14px;margin-bottom:12px;">
        <div class="a2-title" style="font-size:15px;margin-bottom:8px;">{{ __('الاعتراضات') }}</div>
        @forelse($fine->appeals->sortByDesc('id') as $appeal)
            <div style="border:1px solid var(--a2-border,#eee);border-radius:8px;padding:10px;margin-bottom:8px;">
                <div style="display:flex;justify-content:space-between;">
                    <span class="a2-badge">{{ $appeal->statusLabel() }}</span>
                    <span class="a2-hint">{{ $appeal->created_at?->format('Y-m-d H:i') }}</span>
                </div>
                <div style="margin-top:6px;">{{ $appeal->statement }}</div>
                @if($appeal->decision_note)
                    <div class="a2-hint" style="margin-top:6px;">{{ __('قرار الأدمن:') }} {{ $appeal->decision_note }}</div>
                @endif
            </div>
        @empty
            <div class="a2-hint">{{ __('لا اعتراضات.') }}</div>
        @endforelse

        @if($pendingAppeal)
            <form method="POST" action="{{ route('admin.fines.appeal-decision', $fine->id) }}" style="margin-top:10px;">
                @csrf
                <div class="a2-form-group">
                    <label class="a2-label">{{ __('ملاحظة القرار (اختياري)') }}</label>
                    <input class="a2-input" name="note" maxlength="1000">
                </div>
                <div style="margin-top:10px;display:flex;gap:8px;">
                    <button class="a2-btn a2-btn-primary" name="decision" value="reject"
                            onclick="return confirm('{{ __('رفض الاعتراض سيخصم قيمة الغرامة. متابعة؟') }}')">{{ __('رفض الاعتراض وخصم') }}</button>
                    <button class="a2-btn a2-btn-ghost" name="decision" value="accept"
                            onclick="return confirm('{{ __('قبول الاعتراض سيلغي الغرامة ويفك التجميد. متابعة؟') }}')">{{ __('قبول الاعتراض وإلغاء') }}</button>
                </div>
            </form>
        @endif
    </div>

    {{-- Cancel (while open) --}}
    @if($fine->isOpen())
        <div class="a2-card" style="padding:14px;">
            <div class="a2-title" style="font-size:15px;margin-bottom:8px;">{{ __('إلغاء الغرامة') }}</div>
            <div class="a2-hint" style="margin-bottom:8px;">{{ __('يُلغي الغرامة قبل تحصيلها ويفك تجميد المبلغ بالكامل.') }}</div>
            <form method="POST" action="{{ route('admin.fines.cancel', $fine->id) }}"
                  onsubmit="return confirm('{{ __('إلغاء الغرامة وفك التجميد. متابعة؟') }}')">
                @csrf
                <div class="a2-form-group">
                    <label class="a2-label">{{ __('سبب الإلغاء (اختياري)') }}</label>
                    <input class="a2-input" name="note" maxlength="1000">
                </div>
                <div style="margin-top:10px;">
                    <button class="a2-btn a2-btn-danger" type="submit">{{ __('إلغاء الغرامة') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection
