@extends('admin-v2.layouts.master')

@section('title', 'Wallet Recharge')
@section('topbar_title', 'Wallet Recharge')
@section('body_class', 'admin-v2-wallet-recharge')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">شحن المحفظة</h1>
            <div class="a2-page-subtitle">شحن رصيد المستخدم مع اختيار إجراء الضمان: تلقائي، مستوى يدوي، أو بدون إجراء.</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.wallet-transactions.index') }}" class="a2-btn a2-btn-ghost">Wallet Transactions</a>
            <a href="{{ route('admin.guarantee-levels.index') }}" class="a2-btn a2-btn-primary">Guarantee Levels</a>
        </div>
    </div>

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-card">
        <form method="POST" action="{{ route('admin.wallet-ops.recharge') }}">
            @csrf

            <input type="hidden" name="user_id" value="{{ optional($user)->id }}">

            <div class="a2-form-grid">
                <div class="a2-card a2-card--tight">
                    <h2 class="a2-section-title">بيانات الشحن</h2>

                    <div class="a2-field">
                        <label class="a2-label">المستخدم</label>
                        <input class="a2-input" value="{{ optional($user)->name ?: '—' }}" disabled>
                        @if($user)
                            <div class="a2-help">#{{ $user->id }} — {{ $user->type }}</div>
                        @endif
                    </div>

                    <div class="a2-field">
                        <label class="a2-label">المبلغ</label>
                        <input class="a2-input" name="amount" type="number" min="1" step="0.01" value="{{ old('amount') }}" required>
                    </div>

                    <div class="a2-field">
                        <label class="a2-label">ملاحظة</label>
                        <textarea class="a2-textarea" name="note" rows="5">{{ old('note') }}</textarea>
                    </div>
                </div>

                <div class="a2-card a2-card--tight">
                    <h2 class="a2-section-title">إجراء الضمان</h2>

                    <div class="a2-field">
                        <label class="a2-label">Guarantee Action</label>
                        <select class="a2-select" name="guarantee_action">
                            <option value="auto" {{ old('guarantee_action', 'auto') === 'auto' ? 'selected' : '' }}>Auto Upgrade بعد الشحن</option>
                            <option value="manual" {{ old('guarantee_action') === 'manual' ? 'selected' : '' }}>Manual Guarantee Level</option>
                            <option value="none" {{ old('guarantee_action') === 'none' ? 'selected' : '' }}>No Guarantee Action</option>
                        </select>
                        <div class="a2-help">Auto Upgrade سيختار أعلى مستوى مناسب حسب الرصيد المتاح.</div>
                    </div>

                    <div class="a2-field">
                        <label class="a2-label">Manual Guarantee Level</label>
                        <select class="a2-select" name="guarantee_level_id">
                            <option value="">اختر مستوى عند استخدام Manual</option>
                            @foreach($levels as $level)
                                <option value="{{ $level->id }}" {{ (int) old('guarantee_level_id') === (int) $level->id ? 'selected' : '' }}>
                                    {{ $level->display_name }} — Locked: {{ number_format((float) $level->required_locked_amount, 2) }} — Coverage: {{ number_format((float) $level->active_coverage_amount, 2) }}
                                </option>
                            @endforeach
                        </select>
                        @if($levels->isEmpty())
                            <div class="a2-help">لا توجد مستويات ضمان مفعلة مناسبة لنوع هذا المستخدم.</div>
                        @else
                            <div class="a2-help">سيتم خصم المبلغ المطلوب من الرصيد الحر وتحويله إلى locked balance.</div>
                        @endif
                    </div>

                    <div class="a2-kv">
                        <div class="a2-kv-row">
                            <div class="a2-kv-key">نوع المستخدم</div>
                            <div class="a2-kv-val">{{ optional($user)->type ?: '—' }}</div>
                        </div>
                        <div class="a2-kv-row">
                            <div class="a2-kv-key">عدد المستويات المتاحة</div>
                            <div class="a2-kv-val">{{ $levels->count() }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="a2-form-actions">
                <button class="a2-btn a2-btn-primary" type="submit">شحن وتنفيذ الإجراء</button>
                @if($user)
                    <a href="{{ route('admin.users.show', $user->id) }}" class="a2-btn a2-btn-ghost">ملف المستخدم</a>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection
