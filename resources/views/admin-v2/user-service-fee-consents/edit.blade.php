@extends('admin-v2.layouts.master')

@section('title', __('موافقات رسوم الخدمة'))
@section('body_class', 'admin-v2 admin-v2-user-service-fee-consents-edit')

@section('content')
@php
    $userName = $user->name ?: ('User #' . $user->id);

    $userType = $user->type ?: 'client';

    $categoryName = $user->category
        ? ($user->category->name_ar ?: ($user->category->name_en ?: ('#' . $user->category->id)))
        : '—';

    $childName = $user->categoryChild
        ? ($user->categoryChild->name_ar ?: ($user->categoryChild->name_en ?: ('#' . $user->categoryChild->id)))
        : '—';

    $wallet = $user->wallet ?? null;

    $feeEnabled = (bool) old('fee_auto_charge_enabled', (int) ($consent->fee_auto_charge_enabled ?? 0));
    $ratingEnabled = (bool) old('rating_enabled', (int) ($consent->rating_enabled ?? 0));
    $statsEnabled = (bool) old('stats_enabled', (int) ($consent->stats_enabled ?? 0));

    $notesVal = old('notes', $consent->notes ?? '');
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('موافقات رسوم الخدمة') }}</h1>
            <div class="a2-page-subtitle">
                {{ $userName }}
                <span class="a2-muted">#{{ $user->id }}</span>
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.users.show', $user) }}" class="a2-btn a2-btn-ghost">
                {{ __('بيانات المستخدم') }}
            </a>

            <a href="{{ route('admin.wallet-transactions.user', $user) }}" class="a2-btn a2-btn-ghost">
                {{ __('كشف المحفظة') }}
            </a>

            <a href="{{ route('admin.users.index') }}" class="a2-btn a2-btn-ghost">
                {{ __('رجوع للمستخدمين') }}
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="a2-alert a2-alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            <div class="a2-fw-900 a2-mb-8">{{ __('يوجد أخطاء:') }}</div>
            <ul style="margin:0;padding-inline-start:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="a2-card a2-card--soft a2-mb-16">
        <div class="a2-section-title">{{ __('ماذا تعني هذه الموافقات؟') }}</div>
        <div class="a2-section-subtitle">
            {{ __('خصم رسوم التنفيذ تلقائيًا يسمح للنظام بخصم رسوم الخدمة من محفظة العميل أو البزنس عند انتقال الحجز إلى') }}
            <span dir="ltr">in_progress</span>{{ __('. بدون هذه الموافقة لن يتم خصم الرسوم من هذا الطرف.') }}
        </div>
    </div>

    <div class="consent-grid">
        <div class="a2-card consent-card">
            <div class="a2-section-title">{{ __('بيانات المستخدم') }}</div>

            <div class="consent-kv">
                <div>
                    <span>{{ __('الاسم') }}</span>
                    <strong>{{ $userName }}</strong>
                </div>

                <div>
                    <span>{{ __('النوع') }}</span>
                    <strong>{{ $userType }}</strong>
                </div>

                <div>
                    <span>{{ __('القسم الرئيسي') }}</span>
                    <strong>{{ $categoryName }}</strong>
                </div>

                <div>
                    <span>{{ __('القسم الفرعي') }}</span>
                    <strong>{{ $childName }}</strong>
                </div>

                <div>
                    <span>Wallet Balance</span>
                    <strong>{{ number_format((float)($wallet->balance ?? 0), 2) }}</strong>
                </div>

                <div>
                    <span>Locked Balance</span>
                    <strong>{{ number_format((float)($wallet->locked_balance ?? 0), 2) }}</strong>
                </div>

                <div>
                    <span>Wallet Status</span>
                    <strong>{{ $wallet->status ?? '—' }}</strong>
                </div>

                <div>
                    <span>Consent ID</span>
                    <strong>{{ $consent->exists ? $consent->id : '—' }}</strong>
                </div>
            </div>
        </div>

        <form
            method="POST"
            action="{{ route('admin.user-service-fee-consents.update', $user) }}"
            class="a2-card consent-card"
        >
            @csrf
            @method('PUT')

            <div class="a2-section-title">{{ __('إعدادات الموافقة') }}</div>
            <div class="a2-section-subtitle">
                {{ __('هذه الإعدادات تؤثر مباشرة على خصم رسوم التنفيذ، والتقييم، والإحصائيات.') }}
            </div>

            <div class="consent-checks">
                <label class="consent-check-card {{ $feeEnabled ? 'is-on' : '' }}">
                    <input
                        type="checkbox"
                        name="fee_auto_charge_enabled"
                        value="1"
                        @checked($feeEnabled)
                    >
                    <span>
                        <strong>{{ __('تفعيل خصم رسوم التنفيذ تلقائيًا') }}</strong>
                        <small>
                            {{ __('يسمح بخصم') }}
                            <span dir="ltr">platform_fee</span>
                            {{ __('من المحفظة عند تنفيذ الحجز.') }}
                        </small>
                    </span>
                </label>

                <label class="consent-check-card {{ $ratingEnabled ? 'is-on' : '' }}">
                    <input
                        type="checkbox"
                        name="rating_enabled"
                        value="1"
                        @checked($ratingEnabled)
                    >
                    <span>
                        <strong>{{ __('تفعيل التقييم') }}</strong>
                        <small>
                            {{ __('يستخدم لاحقًا لفتح تقييم العميل أو البزنس حسب نجاح العملية.') }}
                        </small>
                    </span>
                </label>

                <label class="consent-check-card {{ $statsEnabled ? 'is-on' : '' }}">
                    <input
                        type="checkbox"
                        name="stats_enabled"
                        value="1"
                        @checked($statsEnabled)
                    >
                    <span>
                        <strong>{{ __('تفعيل الإحصائيات') }}</strong>
                        <small>
                            {{ __('يسمح بإدخال العمليات في إحصائيات الخدمة والنجاح.') }}
                        </small>
                    </span>
                </label>
            </div>

            <div class="a2-mt-16">
                <label class="a2-label">{{ __('ملاحظات') }}</label>
                <textarea
                    class="a2-textarea"
                    name="notes"
                    rows="5"
                    placeholder="{{ __('ملاحظات داخلية اختيارية') }}"
                >{{ $notesVal }}</textarea>
            </div>

            <div class="consent-meta">
                <div>
                    <span>Enabled At</span>
                    <strong>{{ optional($consent->enabled_at)->format('Y-m-d H:i') ?: '—' }}</strong>
                </div>

                <div>
                    <span>Disabled At</span>
                    <strong>{{ optional($consent->disabled_at)->format('Y-m-d H:i') ?: '—' }}</strong>
                </div>
            </div>

            <div class="a2-page-actions a2-mt-16" style="justify-content:flex-end;">
                <button type="submit" class="a2-btn a2-btn-primary">
                    {{ __('حفظ الموافقات') }}
                </button>
            </div>
        </form>
    </div>

    <div class="a2-card a2-mt-16">
        <div class="a2-section-title">{{ __('إجراءات سريعة') }}</div>
        <div class="a2-section-subtitle">
            {{ __('تستخدم لتفعيل أو تعطيل خصم رسوم التنفيذ فقط بدون تعديل باقي الإعدادات.') }}
        </div>

        <div class="a2-page-actions a2-mt-12">
            <form method="POST" action="{{ route('admin.user-service-fee-consents.enable-charging', $user) }}">
                @csrf
                <button type="submit" class="a2-btn a2-btn-primary">
                    {{ __('تفعيل الخصم التلقائي') }}
                </button>
            </form>

            <form method="POST" action="{{ route('admin.user-service-fee-consents.disable-charging', $user) }}">
                @csrf
                <button
                    type="submit"
                    class="a2-btn a2-btn-danger"
                    onclick="return confirm('هل تريد تعطيل الخصم التلقائي لهذا المستخدم؟')"
                >
                    {{ __('تعطيل الخصم التلقائي') }}
                </button>
            </form>
        </div>
    </div>
</div>

<style>
.consent-grid{
    display:grid;
    grid-template-columns:minmax(320px,.8fr) minmax(0,1.2fr);
    gap:16px;
}
.consent-card{
    padding:18px;
}
.consent-kv,
.consent-meta{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
    margin-top:14px;
}
.consent-kv > div,
.consent-meta > div{
    background:#f8fafc;
    border:1px solid #e5e7eb;
    border-radius:14px;
    padding:12px 14px;
    min-width:0;
}
.consent-kv span,
.consent-meta span{
    display:block;
    color:#6b7280;
    font-size:12px;
    margin-bottom:6px;
}
.consent-kv strong,
.consent-meta strong{
    display:block;
    font-size:14px;
    font-weight:800;
    line-height:1.5;
    word-break:break-word;
}
.consent-checks{
    display:grid;
    grid-template-columns:1fr;
    gap:12px;
    margin-top:16px;
}
.consent-check-card{
    display:flex;
    gap:12px;
    align-items:flex-start;
    padding:14px;
    border:1px solid #e5e7eb;
    border-radius:16px;
    background:#fff;
    cursor:pointer;
}
.consent-check-card.is-on{
    border-color:#22c55e;
    background:#f0fdf4;
}
.consent-check-card input{
    margin-top:4px;
}
.consent-check-card strong{
    display:block;
    font-weight:900;
    margin-bottom:4px;
}
.consent-check-card small{
    display:block;
    color:#6b7280;
    line-height:1.6;
}
@media(max-width:1000px){
    .consent-grid{
        grid-template-columns:1fr;
    }
}
@media(max-width:700px){
    .consent-kv,
    .consent-meta{
        grid-template-columns:1fr;
    }
}
</style>
@endsection