@extends('admin-v2.layouts.master')

@section('title', 'Guarantee Details')
@section('topbar_title', 'Guarantee Details')
@section('body_class', 'admin-v2-guarantees')

@section('content')
@php
    $statusClasses = [
        'active' => 'a2-pill-success',
        'pending_operations' => 'a2-pill-warning',
        'underfunded' => 'a2-pill-warning',
        'suspended' => 'a2-pill-danger',
        'cancelled' => 'a2-pill-danger',
        'downgraded' => 'a2-pill-gray',
    ];

    $gStatus = (string) $guarantee->status;
    $user = $guarantee->user;
    $wallet = $user ? $user->wallet : null;
    $meta = is_array($guarantee->meta) ? $guarantee->meta : [];
    $expiresAt = $meta['guarantee_expires_at'] ?? $meta['expires_at'] ?? $meta['valid_until'] ?? $meta['subscription_expires_at'] ?? null;
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">ضمان #{{ $guarantee->id }}</h1>
            <div class="a2-page-subtitle">تفاصيل مستوى الضمان، التغطية، العمليات، المهلة، وسجل معاملات الضمان.</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.guarantees.index') }}" class="a2-btn a2-btn-ghost">رجوع للضمانات</a>
            @if($user)
                <a href="{{ route('admin.users.show', $user->id) }}" class="a2-btn a2-btn-primary">ملف المستخدم</a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if(session('info'))
        <div class="a2-alert a2-alert-info">{{ session('info') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card">
            <div class="a2-stat-label">الحالة</div>
            <div class="a2-stat-value"><span class="a2-pill {{ $statusClasses[$gStatus] ?? 'a2-pill-gray' }}">{{ $gStatus ?: '—' }}</span></div>
            <div class="a2-stat-note">Target: {{ $guarantee->target_type }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">الرصيد المجمد</div>
            <div class="a2-stat-value">{{ number_format((float) $guarantee->locked_amount, 2) }}</div>
            <div class="a2-stat-note">Wallet locked: {{ number_format((float) optional($wallet)->locked_balance, 2) }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">التغطية الحالية</div>
            <div class="a2-stat-value">{{ number_format((float) $guarantee->current_coverage_amount, 2) }}</div>
            <div class="a2-stat-note">Used: {{ number_format((float) $guarantee->used_coverage_amount, 2) }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">Trust Score</div>
            <div class="a2-stat-value">{{ number_format((float) $guarantee->trust_score, 2) }}</div>
            <div class="a2-stat-note">Completed: {{ (int) $guarantee->completed_operations_count }}</div>
        </div>
    </div>

    <div class="a2-card a2-card--tight a2-mb-16">
        <div class="a2-header">
            <div>
                <h2 class="a2-section-title a2-mb-0">إجراءات إدارة الضمان</h2>
                <div class="a2-section-subtitle">تشغيل محركات الضمان يدويًا أو تعليق/إعادة تفعيل الضمان عند الحاجة.</div>
            </div>
        </div>

        <div class="a2-actionsbar">
            <form method="POST" action="{{ route('admin.guarantees.sync', $guarantee->id) }}">
                @csrf
                <button type="submit" class="a2-btn a2-btn-sm a2-btn-primary">Sync Coverage</button>
            </form>

            <form method="POST" action="{{ route('admin.guarantees.auto-upgrade', $guarantee->id) }}">
                @csrf
                <button type="submit" class="a2-btn a2-btn-sm a2-btn-success">Auto Upgrade</button>
            </form>

            <form method="POST" action="{{ route('admin.guarantees.auto-downgrade', $guarantee->id) }}">
                @csrf
                <button type="submit" class="a2-btn a2-btn-sm a2-btn-ghost">Auto Downgrade</button>
            </form>

            <form method="POST" action="{{ route('admin.guarantees.process-grace', $guarantee->id) }}">
                @csrf
                <button type="submit" class="a2-btn a2-btn-sm a2-btn-ghost" onclick="return confirm('معالجة Grace Period الآن؟')">Process Grace</button>
            </form>

            <form method="POST" action="{{ route('admin.guarantees.expire-if-due', $guarantee->id) }}">
                @csrf
                <button type="submit" class="a2-btn a2-btn-sm a2-btn-ghost">Expire If Due</button>
            </form>

            <form method="POST" action="{{ route('admin.guarantees.reactivate', $guarantee->id) }}">
                @csrf
                <button type="submit" class="a2-btn a2-btn-sm a2-btn-success" onclick="return confirm('إعادة تفعيل الضمان ومحاولة مزامنته؟')">Reactivate</button>
            </form>

            <form method="POST" action="{{ route('admin.guarantees.unlock-to-balance', $guarantee->id) }}">
                @csrf
                <button type="submit" class="a2-btn a2-btn-sm a2-btn-primary" onclick="return confirm('فكّ الضمان وتحويل قيمته إلى رصيد المحفظة؟ (يُرفض إن كان جزء منه محجوزًا لعملية)')">فكّ وتحويل لرصيد</button>
            </form>

            <form method="POST" action="{{ route('admin.guarantees.suspend', $guarantee->id) }}">
                @csrf
                <button type="submit" class="a2-btn a2-btn-sm a2-btn-danger" onclick="return confirm('تعليق الضمان يدويًا؟')">Suspend</button>
            </form>

            <form method="POST" action="{{ route('admin.guarantees.expire-now', $guarantee->id) }}">
                @csrf
                <button type="submit" class="a2-btn a2-btn-sm a2-btn-danger" onclick="return confirm('إنهاء الضمان الآن وتعليق التغطية؟')">Expire Now</button>
            </form>
        </div>
    </div>

    <div class="a2-form-grid a2-mb-16">
        <div class="a2-card">
            <h2 class="a2-section-title">بيانات المستخدم</h2>
            <div class="a2-kv">
                <div class="a2-kv-row"><div class="a2-kv-key">الاسم</div><div class="a2-kv-val">{{ optional($user)->name ?: '—' }}</div></div>
                <div class="a2-kv-row"><div class="a2-kv-key">النوع</div><div class="a2-kv-val">{{ optional($user)->type ?: '—' }}</div></div>
                <div class="a2-kv-row"><div class="a2-kv-key">الهاتف</div><div class="a2-kv-val">{{ optional($user)->phone ?: '—' }}</div></div>
                <div class="a2-kv-row"><div class="a2-kv-key">البريد</div><div class="a2-kv-val">{{ optional($user)->email ?: '—' }}</div></div>
                <div class="a2-kv-row"><div class="a2-kv-key">Wallet Balance</div><div class="a2-kv-val">{{ number_format((float) optional($wallet)->balance, 2) }}</div></div>
            </div>
        </div>

        <div class="a2-card">
            <h2 class="a2-section-title">بيانات الضمان</h2>
            <div class="a2-kv">
                <div class="a2-kv-row"><div class="a2-kv-key">Purchased Level</div><div class="a2-kv-val">{{ optional($guarantee->purchasedLevel)->display_name ?: '—' }}</div></div>
                <div class="a2-kv-row"><div class="a2-kv-key">Effective Level</div><div class="a2-kv-val">{{ optional($guarantee->effectiveLevel)->display_name ?: '—' }}</div></div>
                <div class="a2-kv-row"><div class="a2-kv-key">Pending Coverage</div><div class="a2-kv-val">{{ number_format((float) $guarantee->pending_coverage_amount, 2) }}</div></div>
                <div class="a2-kv-row"><div class="a2-kv-key">Active Coverage</div><div class="a2-kv-val">{{ number_format((float) $guarantee->active_coverage_amount, 2) }}</div></div>
                <div class="a2-kv-row"><div class="a2-kv-key">Grace Until</div><div class="a2-kv-val">{{ $guarantee->grace_until ? $guarantee->grace_until->format('Y-m-d H:i') : '—' }}</div></div>
                <div class="a2-kv-row"><div class="a2-kv-key">Expires At</div><div class="a2-kv-val">{{ $expiresAt ?: '—' }}</div></div>
            </div>
        </div>
    </div>

    <div class="a2-card a2-card--tight a2-mb-16">
        <h2 class="a2-section-title">مؤشرات التأهيل</h2>
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>المستوى</th>
                        <th>Target</th>
                        <th>Priority</th>
                        <th>Required Locked</th>
                        <th>Required Ops</th>
                        <th>Required Score</th>
                        <th>Max Lost Disputes</th>
                        <th>Max Late Cancel</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($levels as $level)
                        <tr>
                            <td>{{ $level->display_name }}</td>
                            <td>{{ $level->target_type }}</td>
                            <td>{{ (int) $level->priority }}</td>
                            <td>{{ number_format((float) $level->required_locked_amount, 2) }}</td>
                            <td>{{ (int) $level->required_completed_operations }}</td>
                            <td>{{ number_format((float) $level->required_trust_score, 2) }}</td>
                            <td>{{ $level->max_lost_disputes === null ? '—' : (int) $level->max_lost_disputes }}</td>
                            <td>{{ $level->max_late_cancellations === null ? '—' : (int) $level->max_late_cancellations }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="a2-empty-cell">لا توجد مستويات لهذا النوع.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="a2-card a2-card--tight">
        <h2 class="a2-section-title">سجل معاملات الضمان</h2>
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Coverage</th>
                        <th>Locked Before</th>
                        <th>Locked After</th>
                        <th>Reference</th>
                        <th>Reason</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $tx)
                        <tr>
                            <td>{{ $tx->id }}</td>
                            <td><span class="a2-pill a2-pill-gray">{{ $tx->type }}</span></td>
                            <td>{{ number_format((float) $tx->amount, 2) }}</td>
                            <td>{{ number_format((float) $tx->coverage_amount, 2) }}</td>
                            <td>{{ $tx->locked_before === null ? '—' : number_format((float) $tx->locked_before, 2) }}</td>
                            <td>{{ $tx->locked_after === null ? '—' : number_format((float) $tx->locked_after, 2) }}</td>
                            <td>{{ $tx->reference_type ?: '—' }} @if($tx->reference_id)#{{ $tx->reference_id }}@endif</td>
                            <td class="a2-text-right">{{ $tx->reason ?: '—' }}</td>
                            <td>{{ $tx->created_at ? $tx->created_at->format('Y-m-d H:i') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="a2-empty-cell">لا توجد معاملات ضمان.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
