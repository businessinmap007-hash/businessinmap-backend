@extends('admin-v2.layouts.master')

@section('title', 'Wallet Recharge')
@section('topbar_title', 'Wallet Recharge')
@section('body_class', 'admin-v2-wallet-recharge')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">شحن المحفظة</h1>
            <div class="a2-page-subtitle">اكتب جزءًا من الاسم أو الهاتف أو البريد، واختر المستخدم من الاقتراحات.</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.wallet-transactions.index') }}" class="a2-btn a2-btn-ghost">Wallet Transactions</a>
            <a href="{{ route('admin.guarantees.index') }}" class="a2-btn a2-btn-ghost">User Guarantees</a>
            <a href="{{ route('admin.guarantee-levels.index') }}" class="a2-btn a2-btn-primary">Guarantee Levels</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-card a2-mb-16">
        <form method="GET" action="{{ route('admin.wallet-ops.recharge.form') }}" class="a2-filterbar">
            <input
                class="a2-input a2-filter-search"
                type="search"
                name="q"
                value="{{ $q ?? '' }}"
                placeholder="اكتب اسم المستخدم / الهاتف / البريد / ID"
                list="walletUsersList"
                autocomplete="off"
                required
            >

            <datalist id="walletUsersList">
                @foreach(($users ?? collect()) as $row)
                    <option value="{{ $row->name ?: $row->phone ?: $row->email ?: $row->id }}">
                        #{{ $row->id }} — {{ $row->name ?: 'بدون اسم' }} — {{ $row->type }} — {{ $row->phone ?: $row->email }}
                    </option>
                @endforeach
            </datalist>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">تحميل بيانات المستخدم</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.wallet-ops.recharge.form') }}">تفريغ</a>
            </div>
        </form>

        @if(($q ?? '') !== '' && ($users ?? collect())->count() > 1)
            <div class="a2-divider"></div>
            <div class="a2-section-subtitle a2-mb-8">لو ظهر أكثر من مستخدم، اختر المطلوب من النتائج السريعة:</div>
            <div class="a2-row-actions">
                @foreach(($users ?? collect())->take(12) as $row)
                    <a class="a2-btn a2-btn-ghost a2-btn-sm" href="{{ route('admin.wallet-ops.recharge.form', ['user_id' => $row->id, 'q' => $q]) }}">
                        #{{ $row->id }} — {{ $row->name ?: 'بدون اسم' }} — {{ $row->type }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    @if($user)
        <div class="a2-stat-grid a2-mb-16">
            <div class="a2-stat-card">
                <div class="a2-stat-label">Available Balance</div>
                <div class="a2-stat-value">{{ number_format((float) optional($wallet)->balance, 2) }}</div>
                <div class="a2-stat-note">الرصيد المتاح</div>
            </div>
            <div class="a2-stat-card">
                <div class="a2-stat-label">Locked Balance</div>
                <div class="a2-stat-value">{{ number_format((float) optional($wallet)->locked_balance, 2) }}</div>
                <div class="a2-stat-note">الرصيد المقفل</div>
            </div>
            <div class="a2-stat-card">
                <div class="a2-stat-label">Wallet Status</div>
                <div class="a2-stat-value">{{ optional($wallet)->status ?: '—' }}</div>
                <div class="a2-stat-note">حالة المحفظة</div>
            </div>
            <div class="a2-stat-card">
                <div class="a2-stat-label">Guarantee</div>
                <div class="a2-stat-value">{{ $activeGuarantee ? ($activeGuarantee->effectiveLevel?->display_name ?: $activeGuarantee->purchasedLevel?->display_name ?: '#' . $activeGuarantee->id) : '—' }}</div>
                <div class="a2-stat-note">{{ $activeGuarantee ? ('Coverage: ' . number_format((float) $activeGuarantee->current_coverage_amount, 2)) : 'لا يوجد ضمان نشط' }}</div>
            </div>
        </div>

        <div class="a2-card a2-mb-16">
            <div class="a2-header">
                <div>
                    <h2 class="a2-section-title a2-mb-0">تفعيل ضمان من الرصيد الحالي</h2>
                    <div class="a2-section-subtitle">لو المستخدم عنده رصيد مثل 5000، اختر مستوى الضمان وسيتم قفل قيمة الضمان من الرصيد بدون شحن جديد.</div>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.wallet-ops.activate-guarantee') }}">
                @csrf
                <input type="hidden" name="user_id" value="{{ $user->id }}">

                <div class="a2-form-grid">
                    <div class="a2-field">
                        <label class="a2-label">المستخدم</label>
                        <input class="a2-input" value="#{{ $user->id }} — {{ $user->name ?: '—' }} — {{ $user->type }}" disabled>
                    </div>

                    <div class="a2-field">
                        <label class="a2-label">مستوى الضمان</label>
                        <select class="a2-select" name="guarantee_level_id" required>
                            <option value="">اختر مستوى الضمان</option>
                            @foreach($levels as $level)
                                <option value="{{ $level->id }}">
                                    {{ $level->display_name }} — Locked: {{ number_format((float) $level->required_locked_amount, 2) }} — Coverage: {{ number_format((float) $level->active_coverage_amount, 2) }}
                                </option>
                            @endforeach
                        </select>
                        <div class="a2-help">سيتم نقل Locked المطلوب من الرصيد المتاح إلى الرصيد المقفل.</div>
                    </div>
                </div>

                <div class="a2-form-actions">
                    <button class="a2-btn a2-btn-primary" type="submit">تفعيل الضمان من الرصيد الحالي</button>
                    <a href="{{ route('admin.guarantees.index', ['q' => $user->id]) }}" class="a2-btn a2-btn-ghost">ضمانات المستخدم</a>
                </div>
            </form>
        </div>

        <div class="a2-card">
            <form method="POST" action="{{ route('admin.wallet-ops.recharge') }}">
                @csrf
                <input type="hidden" name="user_id" value="{{ $user->id }}">
                {{-- Stable per-form nonce: a double-click resubmits the same
                     token so the ledger dedupes it; a fresh page load gets a
                     new token and allows a new recharge. --}}
                <input type="hidden" name="request_token" value="{{ old('request_token', (string) \Illuminate\Support\Str::uuid()) }}">

                <div class="a2-form-grid">
                    <div class="a2-card a2-card--tight">
                        <h2 class="a2-section-title">شحن جديد للمحفظة</h2>

                        <div class="a2-field">
                            <label class="a2-label">المستخدم المختار</label>
                            <input class="a2-input" value="#{{ $user->id }} — {{ $user->name ?: '—' }} — {{ $user->type }}" disabled>
                            <div class="a2-help" dir="ltr">{{ $user->phone ?: '—' }} / {{ $user->email ?: '—' }}</div>
                        </div>

                        <div class="a2-field">
                            <label class="a2-label">المبلغ</label>
                            <input class="a2-input" name="amount" type="number" min="1" step="0.01" value="{{ old('amount') }}" required>
                            <div class="a2-help">استخدم هذا الجزء فقط لو تريد إضافة رصيد جديد.</div>
                        </div>

                        <div class="a2-field">
                            <label class="a2-label">ملاحظة</label>
                            <textarea class="a2-textarea" name="note" rows="5">{{ old('note') }}</textarea>
                        </div>
                    </div>

                    <div class="a2-card a2-card--tight">
                        <h2 class="a2-section-title">إجراء الضمان بعد الشحن</h2>

                        <div class="a2-field">
                            <label class="a2-label">Guarantee Action</label>
                            <select class="a2-select" name="guarantee_action">
                                <option value="auto" {{ old('guarantee_action', 'auto') === 'auto' ? 'selected' : '' }}>Auto Upgrade بعد الشحن</option>
                                <option value="manual" {{ old('guarantee_action') === 'manual' ? 'selected' : '' }}>Manual Guarantee Level</option>
                                <option value="none" {{ old('guarantee_action') === 'none' ? 'selected' : '' }}>No Guarantee Action</option>
                            </select>
                            <div class="a2-help">لو الرصيد موجود بالفعل، استخدم صندوق تفعيل الضمان بالأعلى.</div>
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
                        </div>
                    </div>
                </div>

                <div class="a2-form-actions">
                    <button class="a2-btn a2-btn-primary" type="submit">شحن وتنفيذ الإجراء</button>
                    <a href="{{ route('admin.wallet-transactions.user', $user->id) }}" class="a2-btn a2-btn-ghost">معاملات المحفظة</a>
                    <a href="{{ route('admin.users.show', $user->id) }}" class="a2-btn a2-btn-ghost">ملف المستخدم</a>
                </div>
            </form>
        </div>
    @else
        <div class="a2-card a2-card--soft">
            <div class="a2-section-title">ابحث عن مستخدم أولًا</div>
            <div class="a2-section-subtitle">بعد اختيار المستخدم ستظهر المحفظة، الرصيد المتاح، الرصيد المقفل، ومستويات الضمان المناسبة.</div>
        </div>
    @endif
</div>
@endsection
