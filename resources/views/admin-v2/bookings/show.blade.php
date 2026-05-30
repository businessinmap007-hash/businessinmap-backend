@extends('admin-v2.layouts.master')

@section('title', 'Booking Operation Center')
@section('body_class', 'admin-v2 admin-v2-booking-show')

@section('content')
@php
    use App\Support\AdminV2\Operations\OperationAction;

    $ui = is_array($operationUi ?? null) ? $operationUi : [];

    $summary = $ui['summary'] ?? [];
    $stage = $ui['stage'] ?? [];
    $workflow = $ui['workflow'] ?? [];
    $nextAction = $ui['next_action'] ?? null;
    $actions = collect($ui['actions'] ?? []);

    $participants = $ui['participants'] ?? [];
    $client = $participants['client'] ?? [];
    $business = $participants['business'] ?? [];

    $service = $ui['service'] ?? [];
    $schedule = $ui['schedule'] ?? [];
    $pricing = $ui['pricing'] ?? [];
    $depositUi = $ui['deposit'] ?? [];
    $fees = $ui['fees'] ?? [];
    $confirmations = $ui['confirmations'] ?? [];
    $disputeUi = $ui['dispute'] ?? [];
    $timeline = collect($ui['timeline'] ?? []);
    $debug = $ui['debug'] ?? [];

    $blockedReasons = collect($ui['blocked_reasons'] ?? []);
    $warnings = collect($ui['warnings'] ?? []);

    $tone = (string) ($summary['tone'] ?? $stage['tone'] ?? 'info');

    $tonePillClass = match ($tone) {
        'success' => 'a2-pill-success',
        'danger' => 'a2-pill-danger',
        'warning' => 'a2-pill-warning',
        'primary', 'info' => 'a2-pill-gray',
        default => 'a2-pill-gray',
    };

    $statusLabel = (string) ($summary['status'] ?? $stage['label_ar'] ?? $booking->status ?? '—');

    $money = function ($value, $currency = 'EGP') {
        return number_format((float) ($value ?? 0), 2) . ' ' . ($currency ?: 'EGP');
    };

    $boolLabel = function ($value) {
        return $value ? 'نعم' : 'لا';
    };

    $actionByKey = $actions->keyBy('key');

    $can = function (string $action) use ($actionByKey) {
        return $actionByKey->has($action);
    };

    $actionToneClass = function (?string $tone) {
        return match ($tone) {
            'success' => 'a2-btn-success',
            'danger' => 'a2-btn-danger',
            'warning' => 'a2-btn-ghost',
            'primary' => 'a2-btn-primary',
            default => 'a2-btn-ghost',
        };
    };

    $depositStatus = (string) ($depositUi['status'] ?? ($deposit->status ?? ''));
@endphp

<div class="a2-page booking-op">

    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">
                {{ $summary['title'] ?? ('Booking #' . $booking->id) }}
            </h1>

            <div class="a2-page-subtitle">
                {{ $summary['subtitle'] ?? 'Operation Center لإدارة الحجز، الديبوزت، التأكيدات، الرسوم، والنزاعات من مكان واحد.' }}
            </div>

            <div class="booking-op-head-pills">
                <span class="a2-pill {{ $tonePillClass }}">
                    {{ $statusLabel }}
                </span>

                <span class="a2-pill a2-pill-gray" dir="ltr">
                    Raw: {{ $summary['raw_status'] ?? $booking->status }}
                </span>

                @if(!empty($debug['reference_type']) && !empty($debug['reference_id']))
                    <span class="a2-pill a2-pill-gray" dir="ltr">
                        {{ $debug['reference_type'] }}:{{ $debug['reference_id'] }}
                    </span>
                @endif
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.bookings.edit', $booking) }}" class="a2-btn a2-btn-primary">
                تعديل
            </a>

            <a href="{{ route('admin.bookings.index') }}" class="a2-btn a2-btn-ghost">
                رجوع
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
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- =========================
        Operation Center Summary
    ========================== --}}
    <div class="booking-op-hero">
        <div class="a2-card booking-op-next">
            <div class="booking-op-card-head">
                <div>
                    <div class="booking-op-eyebrow">Next Action</div>
                    <h2 class="booking-op-main-title">
                        @if($nextAction)
                            {{ $nextAction['label'] ?? $nextAction['key'] }}
                        @elseif($blockedReasons->isNotEmpty())
                            يوجد متطلبات قبل المتابعة
                        @else
                            لا يوجد إجراء مطلوب الآن
                        @endif
                    </h2>
                </div>

                <span class="a2-pill {{ $tonePillClass }}">
                    {{ $statusLabel }}
                </span>
            </div>

            @if($nextAction)
                <div class="booking-op-next-desc">
                    الإجراء المقترح التالي حسب حالة الحجز الحالية.
                </div>
            @elseif($blockedReasons->isNotEmpty())
                <div class="booking-op-next-desc">
                    لا يمكن الانتقال للخطوة التالية قبل معالجة أسباب التعطيل.
                </div>
            @else
                <div class="booking-op-next-desc">
                    الحجز لا يحتاج إجراء فوري من هذه الشاشة.
                </div>
            @endif

            @if($blockedReasons->isNotEmpty())
                <div class="booking-op-reasons">
                    @foreach($blockedReasons as $reason)
                        <div class="booking-op-reason">
                            {{ $reason }}
                        </div>
                    @endforeach
                </div>
            @endif

            @if($warnings->isNotEmpty())
                <div class="booking-op-warnings">
                    @foreach($warnings as $warning)
                        <div class="booking-op-warning">
                            {{ $warning }}
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="a2-card booking-op-mini">
            <div class="booking-op-mini-label">السعر النهائي</div>
            <div class="booking-op-mini-value">
                {{ $money($pricing['final_price'] ?? $pricing['amount'] ?? $booking->price ?? 0, $pricing['currency'] ?? 'EGP') }}
            </div>
            <div class="booking-op-mini-note">
                Quantity: {{ $pricing['quantity'] ?? $booking->quantity ?? 1 }}
            </div>
        </div>

        <div class="a2-card booking-op-mini">
            <div class="booking-op-mini-label">Deposit</div>
            <div class="booking-op-mini-value">
                {{ !empty($depositUi['required']) ? $money($depositUi['amount'] ?? $depositUi['hold'] ?? 0, $depositUi['currency'] ?? 'EGP') : 'غير مطلوب' }}
            </div>
            <div class="booking-op-mini-note">
                {{ !empty($depositUi['exists']) ? ('Status: ' . ($depositUi['status'] ?? '—')) : 'No deposit record' }}
            </div>
        </div>

        <div class="a2-card booking-op-mini">
            <div class="booking-op-mini-label">Execution Fees</div>
            <div class="booking-op-mini-value">
                {{ !empty($fees['charged']) ? 'تم الخصم' : 'لم تخصم' }}
            </div>
            <div class="booking-op-mini-note">
                Client: {{ $money($fees['client_amount'] ?? 0) }} /
                Business: {{ $money($fees['business_amount'] ?? 0) }}
            </div>
        </div>
    </div>

    {{-- =========================
        Timeline
    ========================== --}}
    <div class="a2-card booking-op-section">
        <div class="booking-op-section-head">
            <div>
                <h2 class="a2-section-title">Operation Timeline</h2>
                <div class="a2-section-subtitle">
                    مراحل تشغيل الحجز من الإنشاء حتى الإغلاق.
                </div>
            </div>
        </div>

        <div class="booking-op-timeline">
            @foreach($timeline as $item)
                @php
                    $done = (bool) ($item['done'] ?? false);
                    $itemTone = (string) ($item['tone'] ?? 'muted');
                @endphp

                <div class="booking-op-step {{ $done ? 'is-done' : '' }} tone-{{ $itemTone }}">
                    <div class="booking-op-step-dot"></div>
                    <div class="booking-op-step-body">
                        <strong>{{ $item['label'] ?? $item['key'] ?? '—' }}</strong>
                        <span>{{ $item['time'] ?? '—' }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- =========================
        Actions
    ========================== --}}
    <div class="a2-card booking-op-section">
        <div class="booking-op-section-head">
            <div>
                <h2 class="a2-section-title">Available Actions</h2>
                <div class="a2-section-subtitle">
                    تظهر هنا الإجراءات المتاحة فقط حسب حالة الحجز الحالية.
                </div>
            </div>
        </div>

        <div class="booking-op-actions">

            @if($can(OperationAction::CONFIRM_CLIENT))
                <form method="POST" action="{{ route('admin.bookings.start_confirm.client', $booking) }}">
                    @csrf
                    <button type="submit" class="a2-btn a2-btn-primary a2-btn-block">
                        تأكيد العميل
                    </button>
                </form>
            @endif

            @if($can(OperationAction::CONFIRM_BUSINESS))
                <form method="POST" action="{{ route('admin.bookings.start_confirm.business', $booking) }}">
                    @csrf
                    <button type="submit" class="a2-btn a2-btn-primary a2-btn-block">
                        تأكيد البزنس
                    </button>
                </form>
            @endif

            @if($can(OperationAction::FREEZE_DEPOSIT))
                <form method="POST" action="{{ route('admin.bookings.deposit.freeze', $booking) }}">
                    @csrf
                    <button type="submit" class="a2-btn a2-btn-ghost a2-btn-block">
                        Freeze Deposit
                    </button>
                </form>
            @endif

            @if($can(OperationAction::RELEASE_DEPOSIT))
                <form method="POST" action="{{ route('admin.bookings.deposit.release', $booking) }}">
                    @csrf
                    <button type="submit" class="a2-btn a2-btn-success a2-btn-block">
                        Release Deposit
                    </button>
                </form>
            @endif

            @if($can(OperationAction::REFUND_DEPOSIT))
                <form method="POST" action="{{ route('admin.bookings.deposit.refund', $booking) }}">
                    @csrf
                    <button type="submit" class="a2-btn a2-btn-ghost a2-btn-block">
                        Refund Deposit
                    </button>
                </form>
            @endif

            @if($can(OperationAction::OPEN_DISPUTE))
                <form method="POST" action="{{ route('admin.bookings.deposit.dispute.open', $booking) }}">
                    @csrf
                    <button type="submit" class="a2-btn a2-btn-danger a2-btn-block">
                        Open Dispute
                    </button>
                </form>
            @endif

            @if($deposit)
                <form method="POST" action="{{ route('admin.bookings.deposit.agree.release', $booking) }}">
                    @csrf
                    <button type="submit" class="a2-btn a2-btn-ghost a2-btn-block">
                        Agree Release
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.bookings.deposit.agree.refund', $booking) }}">
                    @csrf
                    <button type="submit" class="a2-btn a2-btn-ghost a2-btn-block">
                        Agree Refund
                    </button>
                </form>
            @endif

            @if($can(OperationAction::START))
                <form method="POST" action="{{ route('admin.bookings.start', $booking) }}">
                    @csrf
                    <button type="submit" class="a2-btn a2-btn-success a2-btn-block">
                        بدء التنفيذ
                    </button>
                </form>
            @endif
            @if($can(OperationAction::COMPLETE))
                <form method="POST" action="{{ route('admin.bookings.complete', $booking) }}">
                    @csrf
                    <button type="submit" class="a2-btn a2-btn-success a2-btn-block">
                        إنهاء الحجز
                    </button>
                </form>
            @endif

            @if($can(OperationAction::CANCEL))
                <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}">
                    @csrf
                    <button type="submit" class="a2-btn a2-btn-danger a2-btn-block"
                            onclick="return confirm('هل تريد إلغاء هذا الحجز؟')">
                        إلغاء الحجز
                    </button>
                </form>
            @endif

            @if($actions->isEmpty())
                <div class="booking-op-empty">
                    لا توجد إجراءات متاحة حاليًا.
                </div>
            @endif
        </div>
    </div>

    {{-- =========================
        Main Details Grid
    ========================== --}}
    <div class="booking-op-grid">

        <div class="a2-card booking-op-section">
            <h2 class="a2-section-title">الأطراف</h2>

            <div class="booking-op-kv-grid">
                <div class="booking-op-kv">
                    <span>العميل</span>
                    <strong>{{ $client['name'] ?? ($booking->user->name ?? '—') }}</strong>
                    <small>ID: {{ $client['id'] ?? $booking->user_id }}</small>
                </div>

                <div class="booking-op-kv">
                    <span>كود العميل</span>
                    <strong>{{ $client['code'] ?? '—' }}</strong>
                    <small>{{ $client['phone'] ?? '' }}</small>
                </div>

                <div class="booking-op-kv">
                    <span>البزنس</span>
                    <strong>{{ $business['name'] ?? ($booking->business->name ?? '—') }}</strong>
                    <small>ID: {{ $business['id'] ?? $booking->business_id }}</small>
                </div>

                <div class="booking-op-kv">
                    <span>Root / Child</span>
                    <strong dir="ltr">
                        {{ $business['category_id'] ?? $debug['category_id'] ?? '—' }}
                        /
                        {{ $business['child_id'] ?? $debug['child_id'] ?? '—' }}
                    </strong>
                    <small>category_id / child_id</small>
                </div>
            </div>
        </div>

        <div class="a2-card booking-op-section">
            <h2 class="a2-section-title">الخدمة والجدولة</h2>

            <div class="booking-op-kv-grid">
                <div class="booking-op-kv">
                    <span>الخدمة</span>
                    <strong>{{ $service['name'] ?? ($booking->service->name_ar ?? $booking->service->name_en ?? '—') }}</strong>
                    <small dir="ltr">{{ $service['key'] ?? ($booking->service->key ?? '') }}</small>
                </div>

                <div class="booking-op-kv">
                    <span>Service ID</span>
                    <strong>{{ $service['id'] ?? $booking->service_id }}</strong>
                    <small>platform_service_id</small>
                </div>

                <div class="booking-op-kv">
                    <span>Starts At</span>
                    <strong>{{ $schedule['starts_at'] ?? optional($booking->starts_at)->format('Y-m-d H:i') ?? '—' }}</strong>
                    <small>{{ $schedule['timezone'] ?? $booking->timezone ?? 'Africa/Cairo' }}</small>
                </div>

                <div class="booking-op-kv">
                    <span>Ends At</span>
                    <strong>{{ $schedule['ends_at'] ?? optional($booking->ends_at)->format('Y-m-d H:i') ?? '—' }}</strong>
                    <small>
                        {{ $schedule['duration_value'] ?? $booking->duration_value ?? '—' }}
                        {{ $schedule['duration_unit'] ?? $booking->duration_unit ?? '' }}
                    </small>
                </div>

                <div class="booking-op-kv">
                    <span>الكمية</span>
                    <strong>{{ $pricing['quantity'] ?? $booking->quantity ?? 1 }}</strong>
                    <small>party size: {{ $booking->party_size ?: '—' }}</small>
                </div>

                <div class="booking-op-kv">
                    <span>ملاحظات</span>
                    <strong>{{ $booking->notes ?: '—' }}</strong>
                    <small>booking notes</small>
                </div>
            </div>
        </div>

        <div class="a2-card booking-op-section">
            <h2 class="a2-section-title">التسعير</h2>

            <div class="booking-op-kv-grid">
                <div class="booking-op-kv">
                    <span>السعر الأصلي</span>
                    <strong>{{ $money($pricing['original_price'] ?? $booking->price ?? 0, $pricing['currency'] ?? 'EGP') }}</strong>
                    <small>original price</small>
                </div>

                <div class="booking-op-kv">
                    <span>السعر النهائي</span>
                    <strong>{{ $money($pricing['final_price'] ?? $booking->price ?? 0, $pricing['currency'] ?? 'EGP') }}</strong>
                    <small>final price</small>
                </div>

                <div class="booking-op-kv">
                    <span>الخصم</span>
                    <strong>
                        {{ !empty($pricing['discount_enabled']) ? 'مفعل' : 'غير مفعل' }}
                    </strong>
                    <small>
                        {{ (int)($pricing['discount_percent'] ?? 0) }}%
                        /
                        {{ $money($pricing['discount_amount'] ?? 0, $pricing['currency'] ?? 'EGP') }}
                    </small>
                </div>

                <div class="booking-op-kv">
                    <span>مصدر السعر</span>
                    <strong>{{ $pricing['source'] ?? '—' }}</strong>
                    <small>business_service_price_id: {{ $pricing['business_service_price_id'] ?? '—' }}</small>
                </div>
            </div>
        </div>

        <div class="a2-card booking-op-section">
            <h2 class="a2-section-title">Deposit</h2>

            <div class="booking-op-kv-grid">
                <div class="booking-op-kv">
                    <span>مطلوب؟</span>
                    <strong>{{ $boolLabel(!empty($depositUi['required'])) }}</strong>
                    <small>{{ !empty($depositUi['exists']) ? 'record exists' : 'no record' }}</small>
                </div>

                <div class="booking-op-kv">
                    <span>القيمة</span>
                    <strong>{{ $money($depositUi['amount'] ?? $depositUi['hold'] ?? 0, $depositUi['currency'] ?? 'EGP') }}</strong>
                    <small>hold: {{ $money($depositUi['hold'] ?? 0, $depositUi['currency'] ?? 'EGP') }}</small>
                </div>

                <div class="booking-op-kv">
                    <span>الحالة</span>
                    <strong>{{ $depositUi['status'] ?? '—' }}</strong>
                    <small>frozen: {{ $boolLabel(!empty($depositUi['is_frozen'])) }}</small>
                </div>

                <div class="booking-op-kv">
                    <span>التأكيدات</span>
                    <strong>
                        Client: {{ $boolLabel(!empty($depositUi['client_confirmed'])) }}
                        /
                        Business: {{ $boolLabel(!empty($depositUi['business_confirmed'])) }}
                    </strong>
                    <small>deposit confirmations</small>
                </div>
            </div>
        </div>

        <div class="a2-card booking-op-section">
            <h2 class="a2-section-title">التأكيدات</h2>

            <div class="booking-op-kv-grid">
                <div class="booking-op-kv">
                    <span>تأكيد العميل</span>
                    <strong>{{ !empty($confirmations['client_confirmed']) ? 'تم' : 'لم يتم' }}</strong>
                    <small>{{ $confirmations['source'] ?? '—' }}</small>
                </div>

                <div class="booking-op-kv">
                    <span>تأكيد البزنس</span>
                    <strong>{{ !empty($confirmations['business_confirmed']) ? 'تم' : 'لم يتم' }}</strong>
                    <small>{{ $confirmations['source'] ?? '—' }}</small>
                </div>

                <div class="booking-op-kv">
                    <span>النزاع</span>
                    <strong>{{ !empty($disputeUi['has_dispute']) ? 'يوجد نزاع' : 'لا يوجد' }}</strong>
                    <small>
                        ID: {{ $disputeUi['id'] ?? '—' }}
                        /
                        {{ $disputeUi['status'] ?? '—' }}
                    </small>
                </div>

                <div class="booking-op-kv">
                    <span>Stage</span>
                    <strong>{{ $stage['label_ar'] ?? $statusLabel }}</strong>
                    <small dir="ltr">{{ $stage['key'] ?? '—' }}</small>
                </div>
            </div>
        </div>

        <div class="a2-card booking-op-section">
            <h2 class="a2-section-title">رسوم التنفيذ</h2>

            <div class="booking-op-kv-grid">
                <div class="booking-op-kv">
                    <span>تم الخصم؟</span>
                    <strong>{{ !empty($fees['charged']) ? 'نعم' : 'لا' }}</strong>
                    <small>{{ $fees['charged_at'] ?? '—' }}</small>
                </div>

                <div class="booking-op-kv">
                    <span>رسوم العميل</span>
                    <strong>{{ $money($fees['client_amount'] ?? 0) }}</strong>
                    <small>client fee</small>
                </div>

                <div class="booking-op-kv">
                    <span>رسوم البزنس</span>
                    <strong>{{ $money($fees['business_amount'] ?? 0) }}</strong>
                    <small>business fee</small>
                </div>

                <div class="booking-op-kv">
                    <span>Fee Row</span>
                    <strong dir="ltr">{{ $fees['fee_row_id'] ?? $debug['fee_row_id'] ?? '—' }}</strong>
                    <small>category_child_service_fee_id</small>
                </div>
            </div>
        </div>
    </div>

    {{-- =========================
        Bookable / Deposit Record / Debug
    ========================== --}}
    @if(!empty($booking->bookable) || !empty(data_get($booking->meta ?? [], 'bookable_item')))
        @php
            $bookableMeta = is_array(data_get($booking->meta ?? [], 'bookable_item')) ? data_get($booking->meta ?? [], 'bookable_item') : [];
        @endphp

        <div class="a2-card booking-op-section">
            <h2 class="a2-section-title">العنصر القابل للحجز</h2>

            <div class="booking-op-kv-grid booking-op-kv-grid-4">
                <div class="booking-op-kv">
                    <span>ID</span>
                    <strong>{{ $bookableMeta['id'] ?? $booking->bookable_id ?? '—' }}</strong>
                    <small>bookable_id</small>
                </div>

                <div class="booking-op-kv">
                    <span>العنوان</span>
                    <strong>{{ $bookableMeta['title'] ?? $booking->bookable?->title ?? '—' }}</strong>
                    <small>{{ $bookableMeta['code'] ?? $booking->bookable?->code ?? '' }}</small>
                </div>

                <div class="booking-op-kv">
                    <span>النوع</span>
                    <strong>{{ $bookableMeta['item_type'] ?? $booking->bookable?->item_type ?? '—' }}</strong>
                    <small>item_type</small>
                </div>

                <div class="booking-op-kv">
                    <span>السعر</span>
                    <strong>{{ $money($bookableMeta['price'] ?? $booking->bookable?->price ?? 0) }}</strong>
                    <small>bookable price</small>
                </div>
            </div>
        </div>
    @endif

    @if($deposit)
        <div class="a2-card booking-op-section">
            <h2 class="a2-section-title">سجل Deposit</h2>

            <div class="booking-op-kv-grid booking-op-kv-grid-4">
                <div class="booking-op-kv">
                    <span>ID</span>
                    <strong>{{ $deposit->id }}</strong>
                    <small>deposit_id</small>
                </div>

                <div class="booking-op-kv">
                    <span>الحالة</span>
                    <strong>{{ $deposit->status }}</strong>
                    <small>status</small>
                </div>

                <div class="booking-op-kv">
                    <span>إجمالي القيمة</span>
                    <strong>{{ $money($deposit->total_amount ?? 0) }}</strong>
                    <small>total_amount</small>
                </div>

                <div class="booking-op-kv">
                    <span>Client / Business</span>
                    <strong>
                        {{ $money($deposit->client_amount ?? 0) }}
                        /
                        {{ $money($deposit->business_amount ?? 0) }}
                    </strong>
                    <small>split</small>
                </div>

                <div class="booking-op-kv">
                    <span>Client Confirmed</span>
                    <strong>{{ $boolLabel((int)($deposit->client_confirmed ?? 0) === 1) }}</strong>
                    <small>deposit</small>
                </div>

                <div class="booking-op-kv">
                    <span>Business Confirmed</span>
                    <strong>{{ $boolLabel((int)($deposit->business_confirmed ?? 0) === 1) }}</strong>
                    <small>deposit</small>
                </div>

                <div class="booking-op-kv">
                    <span>Agree Release</span>
                    <strong>
                        C: {{ $boolLabel((int)($deposit->release_agreed_client ?? 0) === 1) }}
                        /
                        B: {{ $boolLabel((int)($deposit->release_agreed_business ?? 0) === 1) }}
                    </strong>
                    <small>release agreement</small>
                </div>

                <div class="booking-op-kv">
                    <span>Agree Refund</span>
                    <strong>
                        C: {{ $boolLabel((int)($deposit->refund_agreed_client ?? 0) === 1) }}
                        /
                        B: {{ $boolLabel((int)($deposit->refund_agreed_business ?? 0) === 1) }}
                    </strong>
                    <small>refund agreement</small>
                </div>
            </div>
        </div>
    @endif

    <div class="a2-card booking-op-section booking-op-debug">
        <div class="booking-op-section-head">
            <div>
                <h2 class="a2-section-title">Debug Context</h2>
                <div class="a2-section-subtitle">
                    مؤقتًا للتأكد أن Operation Core يقرأ root/child/service/fee بشكل صحيح.
                </div>
            </div>
        </div>

        <div class="booking-op-kv-grid booking-op-kv-grid-4">
            <div class="booking-op-kv">
                <span>reference</span>
                <strong dir="ltr">
                    {{ $debug['reference_type'] ?? 'booking' }}:{{ $debug['reference_id'] ?? $booking->id }}
                </strong>
                <small>wallet reference</small>
            </div>

            <div class="booking-op-kv">
                <span>category_id</span>
                <strong dir="ltr">{{ $debug['category_id'] ?? '—' }}</strong>
                <small>root</small>
            </div>

            <div class="booking-op-kv">
                <span>child_id</span>
                <strong dir="ltr">{{ $debug['child_id'] ?? '—' }}</strong>
                <small>category child</small>
            </div>

            <div class="booking-op-kv">
                <span>platform_service_id</span>
                <strong dir="ltr">{{ $debug['platform_service_id'] ?? $booking->service_id }}</strong>
                <small>service</small>
            </div>

            <div class="booking-op-kv">
                <span>fee_row_id</span>
                <strong dir="ltr">{{ $debug['fee_row_id'] ?? '—' }}</strong>
                <small>fee row</small>
            </div>

            <div class="booking-op-kv">
                <span>ccsf_id</span>
                <strong dir="ltr">{{ $debug['category_child_service_fee_id'] ?? '—' }}</strong>
                <small>category_child_service_fee_id</small>
            </div>

            <div class="booking-op-kv">
                <span>charged_at</span>
                <strong dir="ltr">{{ $fees['charged_at'] ?? '—' }}</strong>
                <small>execution fee</small>
            </div>

            <div class="booking-op-kv">
                <span>transactions</span>
                <strong>{{ count($fees['transactions'] ?? []) }}</strong>
                <small>wallet transactions</small>
            </div>
        </div>
    </div>
</div>
@endsection