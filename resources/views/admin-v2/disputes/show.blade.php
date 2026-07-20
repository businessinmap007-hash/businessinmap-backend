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

        <div style="margin-top:14px;display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
            <div>
                <div class="a2-hint">{{ __('تعاون العميل') }}</div>
                <div style="font-weight:800;">
                    @if($dispute->client_cooperated_at)
                        {{ $dispute->client_cooperated_at->format('Y-m-d H:i') }}
                    @elseif($dispute->client_non_cooperation_flag)
                        <span style="color:#b42318;">{{ __('لم يتعاون') }}</span>
                    @else
                        <span class="a2-hint">{{ __('لم يسجّل بعد') }}</span>
                    @endif
                </div>
            </div>

            <div>
                <div class="a2-hint">{{ __('تعاون النشاط') }}</div>
                <div style="font-weight:800;">
                    @if($dispute->business_cooperated_at)
                        {{ $dispute->business_cooperated_at->format('Y-m-d H:i') }}
                    @elseif($dispute->business_non_cooperation_flag)
                        <span style="color:#b42318;">{{ __('لم يتعاون') }}</span>
                    @else
                        <span class="a2-hint">{{ __('لم يسجّل بعد') }}</span>
                    @endif
                </div>
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
        <div class="a2-title" style="font-size:15px;margin-bottom:10px;">{{ __('جلسة التحكيم') }}</div>

        @if($session && $session->fee_terms_set_at)
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
                <div>
                    <div class="a2-hint">{{ __('رسم التحكيم') }}</div>
                    <div style="font-weight:800;">
                        @if($session->fee_type === \App\Models\ArbitrationSession::FEE_PERCENT)
                            {{ (float) $session->fee_value }}% = {{ number_format((float) $session->fee_amount, 2) }}
                        @else
                            {{ number_format((float) $session->fee_amount, 2) }}
                        @endif
                    </div>
                </div>
                <div>
                    <div class="a2-hint">{{ __('قُبلت في') }}</div>
                    <div style="font-weight:800;">{{ optional($session->accepted_at)->format('Y-m-d H:i') ?: '-' }}</div>
                </div>
                <div>
                    <div class="a2-hint">{{ __('المحكّم') }}</div>
                    <div style="font-weight:800;">{{ $session->arbitrator?->name ?? '-' }}</div>
                </div>
                <div>
                    <div class="a2-hint">{{ __('تحصيل الرسم') }}</div>
                    <div style="font-weight:800;">{{ $session->fee_on ?? __('لم يُحصّل بعد') }}</div>
                </div>
            </div>
        @elseif($canResolve)
            <form method="POST" action="{{ route('admin.disputes.accept-session', $dispute) }}"
                  onsubmit="return confirm('{{ __('تأكيد قبول الجلسة بهذا الرسم؟ لا يمكن تعديله بعد ذلك.') }}');">
                @csrf
                <div class="a2-hint" style="margin-bottom:8px;">
                    {{ __('يُثبَّت الرسم عند قبول الجلسة ويُعلَن للطرفين، ولا يمكن تعديله بعد ذلك.') }}
                </div>

                <div class="a2-hint" style="margin-bottom:8px;">
                    {{ __('رسم الجلسة لهذه الخدمة:') }}
                    <strong>{{ \App\Models\DisputeFee::amountFor((int) $dispute->platform_service_id) }}</strong>
                    — {{ __('يُضبط من شاشة رسوم جلسات التحكيم، ويتحمله الطرف الخاسر وحده.') }}
                </div>

                <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                    <button class="a2-btn a2-btn-primary" type="submit">{{ __('قبول الجلسة') }}</button>
                </div>
            </form>
        @else
            <div class="a2-hint">{{ __('لم تُقبل جلسة تحكيم على هذا النزاع.') }}</div>
        @endif
    </div>

    @if($session && ! $session->isOpen())
        <div class="a2-card" style="padding:14px;margin-top:14px;">
            <div class="a2-title" style="font-size:15px;margin-bottom:10px;">{{ __('التعويض') }}</div>

            @if((float) $session->compensation_amount > 0)
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
                    <div>
                        <div class="a2-hint">{{ __('المبلغ') }}</div>
                        <div style="font-weight:800;">{{ number_format((float) $session->compensation_amount, 2) }}</div>
                    </div>
                    <div>
                        <div class="a2-hint">{{ __('لصالح') }}</div>
                        <div style="font-weight:800;">{{ $session->compensation_to === 'client' ? __('العميل') : __('النشاط') }}</div>
                    </div>
                    <div>
                        <div class="a2-hint">{{ __('السداد') }}</div>
                        <div style="font-weight:800;">
                            @if($session->compensation_paid_at)
                                {{ $session->compensation_paid_at->format('Y-m-d H:i') }}
                            @else
                                <span style="color:#b42318;">{{ __('لم يُسدَّد — رصيد غير كافٍ') }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if($session->compensation_note)
                    <div style="margin-top:8px;">{{ $session->compensation_note }}</div>
                @endif

                @unless($session->compensation_paid_at)
                    <form method="POST" action="{{ route('admin.disputes.compensation.settle', $dispute) }}" style="margin-top:10px;">
                        @csrf
                        <button class="a2-btn a2-btn-primary" type="submit">{{ __('إعادة محاولة التحويل') }}</button>
                        <div class="a2-hint" style="margin-top:6px;">
                            {{ __('عدم السداد سبب صالح لغرامة «عدم الخضوع للحكم».') }}
                        </div>
                    </form>
                @endunless
            @else
                <form method="POST" action="{{ route('admin.disputes.compensation', $dispute) }}"
                      onsubmit="return confirm('{{ __('تأكيد الحكم بالتعويض؟ لا يمكن تعديله بعد ذلك.') }}');">
                    @csrf
                    <div class="a2-hint" style="margin-bottom:8px;">
                        {{ __('المبلغ هو مجموع البنود المختارة من العملية نفسها — لا يُكتب يدويًا، حتى لا يُطالَب أحد بأكثر مما اتُّفق عليه. يُخصم من محفظة الطرف الآخر.') }}
                    </div>

                    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                        <div>
                            <label class="a2-label">{{ __('لصالح') }}</label>
                            <select class="a2-select" name="compensation_to" required>
                                <option value="client">{{ __('العميل') }}</option>
                                <option value="business">{{ __('النشاط') }}</option>
                            </select>
                        </div>
                        <div style="flex:1;min-width:260px;">
                            <label class="a2-label">{{ __('البنود المستحقة') }}</label>
                            @forelse($claimableLines as $line)
                                <label class="a2-check" style="min-height:auto;">
                                    <input type="checkbox" name="compensation_lines[]" value="{{ $line['key'] }}">
                                    <span>{{ $line['label'] }} — {{ number_format($line['amount'], 2) }}</span>
                                </label>
                            @empty
                                <div class="a2-hint">{{ __('لا توجد بنود قابلة للمطالبة في هذه العملية.') }}</div>
                            @endforelse
                        </div>
                        <div style="flex:1;min-width:220px;">
                            <label class="a2-label">{{ __('السبب') }}</label>
                            <input class="a2-input" type="text" name="compensation_note" maxlength="500"
                                   placeholder="{{ __('مثال: رسوم شحن مدفوعة') }}">
                        </div>
                        <button class="a2-btn a2-btn-primary" type="submit">{{ __('الحكم بالتعويض') }}</button>
                    </div>
                </form>
            @endif
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

        <div class="a2-hint" style="margin-top:12px;">
            {{ __('قبول الشروط:') }}
            @foreach($thread->participants->where('role', '!=', 'arbitrator') as $participant)
                <strong>{{ $participant->user?->name ?? '#'.$participant->user_id }}</strong>:
                @if($participant->conduct_declined_at)
                    <span style="color:#b42318;">{{ __('رفض — سقط حقه في المرافعة') }}</span>
                @elseif($participant->conduct_accepted_at && (int) $participant->conduct_version >= \App\Services\ThreadService::CONDUCT_VERSION)
                    {{ __('قبل') }}
                @else
                    <span style="color:#b54708;">{{ __('لم يقبل بعد') }}</span>
                @endif
                @if(! $loop->last) · @endif
            @endforeach
        </div>

        <div style="margin-top:14px;">
            <div class="a2-hint" style="margin-bottom:6px;">{{ __('مخالفات السلوك المسجّلة') }}</div>

            @forelse($violations as $violation)
                <div style="border-right:3px solid #b42318;padding:6px 10px;margin-bottom:6px;">
                    <div style="font-weight:700;">{{ $violation->against?->name ?? '#'.$violation->against_user_id }}</div>
                    <div>{{ $violation->reason }}</div>
                    <div class="a2-hint">
                        {{ $violation->recordedBy?->name }} — {{ optional($violation->created_at)->format('Y-m-d H:i') }}
                        @if($violation->thread_message_id)
                            — {{ __('على الرسالة') }} #{{ $violation->thread_message_id }}
                        @endif
                    </div>
                </div>
            @empty
                <div class="a2-hint">{{ __('لا توجد مخالفات مسجّلة.') }}</div>
            @endforelse

            @if(! $thread->isLocked())
                <form method="POST" action="{{ route('admin.disputes.conduct-violation', $dispute) }}" style="margin-top:8px;">
                    @csrf
                    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                        <div>
                            <label class="a2-label">{{ __('على من') }}</label>
                            <select class="a2-select" name="against_user_id" required>
                                @foreach($thread->participants->where('role', '!=', 'arbitrator') as $participant)
                                    <option value="{{ $participant->user_id }}">
                                        {{ $participant->user?->name ?? '#'.$participant->user_id }} ({{ $participant->role }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="a2-label">{{ __('رقم الرسالة (اختياري)') }}</label>
                            <input class="a2-input" type="number" name="thread_message_id" min="1">
                        </div>
                        <div style="flex:1;min-width:240px;">
                            <label class="a2-label">{{ __('السبب') }}</label>
                            <input class="a2-input" type="text" name="reason" maxlength="2000" required>
                        </div>
                        <button class="a2-btn a2-btn-danger" type="submit">{{ __('تسجيل مخالفة') }}</button>
                    </div>
                    <div class="a2-hint" style="margin-top:6px;">
                        {{ __('التسجيل قرينة أمامك عند الحكم — لا يخصم مالًا ولا يحسم النزاع تلقائيًا.') }}
                    </div>
                </form>
            @endif
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
                    <select class="a2-select" name="platform_fine_on" style="margin-top:6px;">
                        <option value="">{{ __('على من؟') }}</option>
                        <option value="client">{{ __('العميل') }}</option>
                        <option value="business">{{ __('النشاط') }}</option>
                    </select>
                    <select class="a2-select" name="platform_fine_reason" style="margin-top:6px;">
                        <option value="">{{ __('سبب الغرامة') }}</option>
                        <option value="conduct">{{ __('مخالفة سلوك مسجّلة') }}</option>
                        <option value="non_compliance">{{ __('عدم الخضوع للحكم') }}</option>
                    </select>

                    <label class="a2-check" style="margin-top:8px;">
                        <input type="checkbox" name="charge_arbitration_fee" value="1">
                        <span>{{ __('تحصيل رسم الجلسة من الطرف الخاسر') }}</span>
                    </label>

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
                    <select class="a2-select" name="platform_fine_on" style="margin-top:6px;">
                        <option value="">{{ __('على من؟') }}</option>
                        <option value="client">{{ __('العميل') }}</option>
                        <option value="business">{{ __('النشاط') }}</option>
                    </select>
                    <select class="a2-select" name="platform_fine_reason" style="margin-top:6px;">
                        <option value="">{{ __('سبب الغرامة') }}</option>
                        <option value="conduct">{{ __('مخالفة سلوك مسجّلة') }}</option>
                        <option value="non_compliance">{{ __('عدم الخضوع للحكم') }}</option>
                    </select>

                    <label class="a2-check" style="margin-top:8px;">
                        <input type="checkbox" name="charge_arbitration_fee" value="1">
                        <span>{{ __('تحصيل رسم الجلسة من الطرف الخاسر') }}</span>
                    </label>

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
                    <select class="a2-select" name="platform_fine_on" style="margin-top:6px;">
                        <option value="">{{ __('على من؟') }}</option>
                        <option value="client">{{ __('العميل') }}</option>
                        <option value="business">{{ __('النشاط') }}</option>
                    </select>
                    <select class="a2-select" name="platform_fine_reason" style="margin-top:6px;">
                        <option value="">{{ __('سبب الغرامة') }}</option>
                        <option value="conduct">{{ __('مخالفة سلوك مسجّلة') }}</option>
                        <option value="non_compliance">{{ __('عدم الخضوع للحكم') }}</option>
                    </select>

                    <label class="a2-check" style="margin-top:8px;">
                        <input type="checkbox" name="charge_arbitration_fee" value="1">
                        <span>{{ __('تحصيل رسم الجلسة من الطرف الخاسر') }}</span>
                    </label>

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