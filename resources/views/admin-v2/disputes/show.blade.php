@extends('admin-v2.layouts.master')

@section('title','Dispute')
@section('body_class','admin-v2-disputes')

@section('content')
@php
    $isBooking = $disputeable instanceof \App\Models\Booking;
    $canResolve = in_array((string) $dispute->status, ['open', 'under_review', 'mutual_resolution'], true);
@endphp

<div class="a2-page">
    <div class="a2-card" style="padding:14px;">
        <div class="a2-header" style="margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div>
                <div class="a2-title" style="font-size:16px;">{{ __('تفاصيل النزاع #') }}{{ $dispute->id }}</div>
                <div class="a2-hint">Status: {{ $dispute->status }}</div>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.disputes.index') }}">{{ __('رجوع') }}</a>

                @if($isBooking)
                    <a class="a2-btn a2-btn-primary" href="{{ route('admin.bookings.show', $disputeable->id) }}">
                        {{ __('فتح الحجز') }}
                    </a>
                @endif
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
            <div>
                <div class="a2-hint">Platform Service</div>
                <div style="font-weight:800;">{{ $dispute->platformService?->name_ar ?? $dispute->platformService?->name_en ?? '-' }}</div>
            </div>

            <div>
                <div class="a2-hint">Opened By</div>
                <div style="font-weight:800;">{{ $dispute->openedBy?->name ?? ('#'.$dispute->opened_by_user_id) }}</div>
            </div>

            <div>
                <div class="a2-hint">Against</div>
                <div style="font-weight:800;">{{ $dispute->againstUser?->name ?? ($dispute->against_user_id ? '#'.$dispute->against_user_id : '-') }}</div>
            </div>

            <div>
                <div class="a2-hint">Opened At</div>
                <div style="font-weight:800;">{{ optional($dispute->opened_at)->format('Y-m-d H:i') }}</div>
            </div>
        </div>

        <div style="margin-top:14px;">
            <div class="a2-hint">Reason</div>
            <div style="font-weight:700;">
                {{ $dispute->reason_code ?: '-' }}
            </div>
            <div style="margin-top:6px;">
                {{ $dispute->reason_text ?: '-' }}
            </div>
        </div>
    </div>

    @if($isBooking && $disputeable)
        <div class="a2-card" style="padding:14px;margin-top:14px;">
            <div class="a2-title" style="font-size:15px;margin-bottom:10px;">{{ __('بيانات الحجز') }}</div>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
                <div>
                    <div class="a2-hint">Booking</div>
                    <div style="font-weight:800;">#{{ $disputeable->id }}</div>
                </div>

                <div>
                    <div class="a2-hint">Client</div>
                    <div style="font-weight:800;">{{ $disputeable->user?->name ?? '#'.$disputeable->user_id }}</div>
                </div>

                <div>
                    <div class="a2-hint">Business</div>
                    <div style="font-weight:800;">{{ $disputeable->business?->name ?? '#'.$disputeable->business_id }}</div>
                </div>

                <div>
                    <div class="a2-hint">Price</div>
                    <div style="font-weight:800;">{{ number_format((float) $disputeable->price, 2) }}</div>
                </div>
            </div>
        </div>
    @endif

    <div class="a2-card" style="padding:14px;margin-top:14px;">
        <div class="a2-title" style="font-size:15px;margin-bottom:10px;">{{ __('غرفة النزاع') }}</div>

        <div class="a2-hint" style="margin-bottom:10px;">
            {{ __('الأطراف:') }}
            @foreach($thread->participants as $participant)
                <span style="font-weight:700;">{{ $participant->user?->name ?? '#'.$participant->user_id }}</span>
                <span>({{ $participant->role }})</span>@if(! $loop->last), @endif
            @endforeach
        </div>

        <div style="max-height:360px;overflow-y:auto;display:flex;flex-direction:column;gap:8px;padding:8px;background:rgba(0,0,0,.03);border-radius:8px;">
            @forelse($thread->messages->sortBy('id') as $message)
                @if($message->kind === \App\Models\ThreadMessage::KIND_SYSTEM)
                    <div class="a2-hint" style="text-align:center;font-style:italic;">{{ $message->body }}</div>
                @else
                    <div style="background:#fff;border-radius:8px;padding:8px 10px;">
                        <div class="a2-hint">
                            {{ $message->sender?->name ?? ('#'.$message->sender_id) }}
                            — {{ optional($message->created_at)->format('Y-m-d H:i') }}
                        </div>
                        <div style="margin-top:4px;">{{ $message->body }}</div>
                    </div>
                @endif
            @empty
                <div class="a2-hint" style="text-align:center;">{{ __('لا توجد رسائل بعد.') }}</div>
            @endforelse
        </div>

        @if($thread->isLocked())
            <div class="a2-hint" style="margin-top:10px;">{{ __('أُغلقت الغرفة بعد صدور القرار.') }}</div>
        @else
            <form method="POST" action="{{ route('admin.disputes.room.post', $dispute) }}" style="margin-top:10px;">
                @csrf
                <label class="a2-label">{{ __('رسالة كمحكِّم') }}</label>
                <textarea class="a2-input" name="body" rows="3" maxlength="5000" required></textarea>
                <div class="a2-hint" style="margin-top:6px;">
                    {{ __('إرسال رسالة يضمّك إلى الغرفة كمحكِّم ويُعلن ذلك للطرفين.') }}
                </div>
                <button class="a2-btn a2-btn-primary" style="margin-top:8px;" type="submit">{{ __('إرسال') }}</button>
            </form>
        @endif
    </div>

    @if($canResolve)
        <div class="a2-card" style="padding:14px;margin-top:14px;">
            <div class="a2-title" style="font-size:15px;margin-bottom:10px;">{{ __('قرارات النزاع والخصم من الضمان') }}</div>

            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px;">
                <form method="POST" action="{{ route('admin.disputes.resolve.release-business', $dispute) }}" onsubmit="return confirm('تأكيد: سيتم حل النزاع لصالح مقدم الخدمة وتحريك الضمان. هل أنت متأكد؟');">
                    @csrf
                    <div style="font-weight:800;margin-bottom:8px;">{{ __('حل لصالح مقدم الخدمة') }}</div>
                    <label class="a2-label">{{ __('مبلغ عقوبة على العميل') }}</label>
                    <input class="a2-input" type="number" step="0.01" min="0" name="penalty_amount" value="0">
                    
                    <label class="a2-label" style="margin-top:8px;">{{ __('غرامة منصة (نقدًا)') }}</label>
                    <input class="a2-input" type="number" step="0.01" min="0" name="platform_fine_amount" value="0">
                    <select class="a2-input" name="platform_fine_on" style="margin-top:6px;">
                        <option value="">{{ __('على من؟') }}</option>
                        <option value="client">{{ __('العميل') }}</option>
                        <option value="business">{{ __('النشاط') }}</option>
                    </select>

                    <button class="a2-btn a2-btn-primary" style="margin-top:10px;" type="submit">
                        Release Business
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.disputes.resolve.refund-client', $dispute) }}" onsubmit="return confirm('تأكيد: سيتم حل النزاع لصالح العميل واسترجاع الضمان. هل أنت متأكد؟');">
                    @csrf
                    <div style="font-weight:800;margin-bottom:8px;">{{ __('حل لصالح العميل') }}</div>
                    <label class="a2-label">{{ __('مبلغ عقوبة على البزنس') }}</label>
                    <input class="a2-input" type="number" step="0.01" min="0" name="penalty_amount" value="0">
                    
                    <label class="a2-label" style="margin-top:8px;">{{ __('غرامة منصة (نقدًا)') }}</label>
                    <input class="a2-input" type="number" step="0.01" min="0" name="platform_fine_amount" value="0">
                    <select class="a2-input" name="platform_fine_on" style="margin-top:6px;">
                        <option value="">{{ __('على من؟') }}</option>
                        <option value="client">{{ __('العميل') }}</option>
                        <option value="business">{{ __('النشاط') }}</option>
                    </select>

                    <button class="a2-btn a2-btn-danger" style="margin-top:10px;" type="submit">
                        Refund Client
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.disputes.resolve.split', $dispute) }}" onsubmit="return confirm('تأكيد: سيتم تقسيم الضمان بين الطرفين بالنسب المحددة. هل أنت متأكد؟');">
                    @csrf
                    <div style="font-weight:800;margin-bottom:8px;">Split</div>

                    <label class="a2-label">Client %</label>
                    <input class="a2-input" type="number" step="0.01" min="0" max="100" name="client_percent" value="50">

                    <label class="a2-label" style="margin-top:8px;">Business %</label>
                    <input class="a2-input" type="number" step="0.01" min="0" max="100" name="business_percent" value="50">

                    <label class="a2-label" style="margin-top:8px;">{{ __('عقوبة على العميل') }}</label>
                    <input class="a2-input" type="number" step="0.01" min="0" name="client_penalty_amount" value="0">

                    <label class="a2-label" style="margin-top:8px;">{{ __('عقوبة على البزنس') }}</label>
                    <input class="a2-input" type="number" step="0.01" min="0" name="business_penalty_amount" value="0">

                    
                    <label class="a2-label" style="margin-top:8px;">{{ __('غرامة منصة (نقدًا)') }}</label>
                    <input class="a2-input" type="number" step="0.01" min="0" name="platform_fine_amount" value="0">
                    <select class="a2-input" name="platform_fine_on" style="margin-top:6px;">
                        <option value="">{{ __('على من؟') }}</option>
                        <option value="client">{{ __('العميل') }}</option>
                        <option value="business">{{ __('النشاط') }}</option>
                    </select>

                    <button class="a2-btn a2-btn-primary" style="margin-top:10px;" type="submit">
                        Resolve Split
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.disputes.resolve.no-action', $dispute) }}" onsubmit="return confirm('تأكيد: سيتم إغلاق النزاع بدون أي إجراء مالي. هل أنت متأكد؟');">
                    @csrf
                    <div style="font-weight:800;margin-bottom:8px;">{{ __('بدون إجراء مالي') }}</div>
                    <button class="a2-btn a2-btn-ghost" type="submit">
                        No Action
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection