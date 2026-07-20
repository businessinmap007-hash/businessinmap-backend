@extends('admin-v2.layouts.master')

@section('title', 'Booking Operation Center')
@section('body_class', 'admin-v2 admin-v2-booking-show admin-v2-booking-show-ux')

@section('content')
@php
    use App\Support\AdminV2\Operations\OperationAction;

    $ui = is_array($operationUi ?? null) ? $operationUi : [];

    $summary = $ui['summary'] ?? [];
    $stage = $ui['stage'] ?? [];
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

    $bookableMeta = is_array(data_get($booking->meta ?? [], 'bookable_item'))
        ? data_get($booking->meta ?? [], 'bookable_item')
        : [];

    $priceCurrency = $pricing['currency'] ?? 'EGP';

    $finalPrice = $pricing['final_price'] ?? $pricing['amount'] ?? $booking->price ?? 0;
    $originalPrice = $pricing['original_price'] ?? $booking->price ?? 0;
    $discountAmount = $pricing['discount_amount'] ?? 0;
    $discountPercent = (int) ($pricing['discount_percent'] ?? 0);

    $clientFee = (float) ($fees['client_amount'] ?? 0);
    $businessFee = (float) ($fees['business_amount'] ?? 0);
    $totalFee = $clientFee + $businessFee;
@endphp

<div class="a2-page booking-show-ux">

    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">
                {{ $summary['title'] ?? ('Booking #' . $booking->id) }}
            </h1>

            <div class="a2-page-subtitle">
                {{ $summary['subtitle'] ?? 'مركز تشغيل الحجز وإدارة الديبوزت والرسوم والتأكيدات.' }}
            </div>

            <div class="booking-show-pills">
                <span class="a2-pill {{ $tonePillClass }}">
                    {{ $statusLabel }}
                </span>

                <span class="a2-pill a2-pill-gray" dir="ltr">
                    Raw: {{ $summary['raw_status'] ?? $booking->status }}
                </span>

                <span class="a2-pill a2-pill-gray" dir="ltr">
                    booking:{{ $booking->id }}
                </span>
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.bookings.edit', $booking) }}" class="a2-btn a2-btn-primary">
                {{ __('تعديل') }}
            </a>

            <a href="{{ route('admin.bookings.index') }}" class="a2-btn a2-btn-ghost">
                {{ __('رجوع') }}
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

    <div class="booking-show-layout">

        <main class="booking-show-main">

            <div class="booking-show-hero-grid">
                <div class="a2-card booking-show-next-card">
                    <div class="booking-show-card-head">
                        <div>
                            <div class="booking-show-eyebrow">Next Action</div>
                            <h2 class="booking-show-next-title">
                                @if($nextAction)
                                    {{ $nextAction['label'] ?? $nextAction['key'] }}
                                @elseif($blockedReasons->isNotEmpty())
                                    {{ __('يوجد متطلبات قبل المتابعة') }}
                                @else
                                    {{ __('لا يوجد إجراء مطلوب الآن') }}
                                @endif
                            </h2>
                        </div>

                        <span class="a2-pill {{ $tonePillClass }}">
                            {{ $statusLabel }}
                        </span>
                    </div>

                    @if($nextAction)
                        <div class="booking-show-desc">
                            {{ __('الإجراء المقترح التالي حسب حالة الحجز الحالية.') }}
                        </div>
                    @elseif($blockedReasons->isNotEmpty())
                        <div class="booking-show-desc">
                            {{ __('لا يمكن الانتقال للخطوة التالية قبل معالجة أسباب التعطيل.') }}
                        </div>
                    @else
                        <div class="booking-show-desc">
                            {{ __('الحجز لا يحتاج إجراء فوري من هذه الشاشة.') }}
                        </div>
                    @endif

                    @if($blockedReasons->isNotEmpty())
                        <div class="booking-show-alert-list">
                            @foreach($blockedReasons as $reason)
                                <div class="booking-show-alert booking-show-alert-danger">
                                    {{ $reason }}
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($warnings->isNotEmpty())
                        <div class="booking-show-alert-list">
                            @foreach($warnings as $warning)
                                <div class="booking-show-alert booking-show-alert-warning">
                                    {{ $warning }}
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="a2-card booking-show-mini-card">
                    <span>{{ __('السعر النهائي') }}</span>
                    <strong>{{ $money($finalPrice, $priceCurrency) }}</strong>
                    <small>Quantity: {{ $pricing['quantity'] ?? $booking->quantity ?? 1 }}</small>
                </div>

                <div class="a2-card booking-show-mini-card">
                    <span>Deposit</span>
                    <strong>
                        {{ !empty($depositUi['required']) ? $money($depositUi['amount'] ?? $depositUi['hold'] ?? 0, $depositUi['currency'] ?? 'EGP') : 'غير مطلوب' }}
                    </strong>
                    <small>
                        {{ !empty($depositUi['exists']) ? ('Status: ' . ($depositUi['status'] ?? '—')) : 'No deposit record' }}
                    </small>
                </div>

                <div class="a2-card booking-show-mini-card">
                    <span>{{ __('رسوم التنفيذ') }}</span>
                    <strong>{{ !empty($fees['charged']) ? 'تم الخصم' : 'لم تخصم' }}</strong>
                    <small>{{ $money($totalFee) }}</small>
                </div>
            </div>

            <div class="a2-card booking-show-section">
                <div class="booking-show-section-head">
                    <div>
                        <h2 class="a2-section-title">Operation Timeline</h2>
                        <div class="a2-section-subtitle">
                            {{ __('مراحل تشغيل الحجز من الإنشاء حتى الإغلاق.') }}
                        </div>
                    </div>
                </div>

                <div class="booking-show-timeline">
                    @foreach($timeline as $item)
                        @php
                            $done = (bool) ($item['done'] ?? false);
                            $itemTone = (string) ($item['tone'] ?? 'muted');
                        @endphp

                        <div class="booking-show-step {{ $done ? 'is-done' : '' }} tone-{{ $itemTone }}">
                            <div class="booking-show-step-dot"></div>
                            <div class="booking-show-step-body">
                                <strong>{{ $item['label'] ?? $item['key'] ?? '—' }}</strong>
                                <span>{{ $item['time'] ?? '—' }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="booking-show-grid">

                <div class="a2-card booking-show-section">
                    <h2 class="a2-section-title">{{ __('الأطراف') }}</h2>

                    <div class="booking-show-kv-grid">
                        <div class="booking-show-kv">
                            <span>{{ __('طالب الحجز') }}</span>
                            <strong>{{ $client['name'] ?? ($booking->user->name ?? '—') }}</strong>
                            <small>ID: {{ $client['id'] ?? $booking->user_id }}</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('هاتف / كود طالب الحجز') }}</span>
                            <strong>{{ $client['phone'] ?? '—' }}</strong>
                            <small>{{ $client['code'] ?? '' }}</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('مقدم الخدمة') }}</span>
                            <strong>{{ $business['name'] ?? ($booking->business->name ?? '—') }}</strong>
                            <small>ID: {{ $business['id'] ?? $booking->business_id }}</small>
                        </div>

                        <div class="booking-show-kv">
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

                <div class="a2-card booking-show-section">
                    <h2 class="a2-section-title">{{ __('الخدمة والموعد') }}</h2>

                    <div class="booking-show-kv-grid">
                        <div class="booking-show-kv">
                            <span>{{ __('الخدمة') }}</span>
                            <strong>{{ $service['name'] ?? ($booking->service->name_ar ?? $booking->service->name_en ?? '—') }}</strong>
                            <small dir="ltr">{{ $service['key'] ?? ($booking->service->key ?? '') }}</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>Service ID</span>
                            <strong>{{ $service['id'] ?? $booking->service_id }}</strong>
                            <small>platform_service_id</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>Starts At</span>
                            <strong>{{ $schedule['starts_at'] ?? optional($booking->starts_at)->format('Y-m-d H:i') ?? '—' }}</strong>
                            <small>{{ $schedule['timezone'] ?? $booking->timezone ?? 'Africa/Cairo' }}</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>Ends At</span>
                            <strong>{{ $schedule['ends_at'] ?? optional($booking->ends_at)->format('Y-m-d H:i') ?? '—' }}</strong>
                            <small>
                                {{ $schedule['duration_value'] ?? $booking->duration_value ?? '—' }}
                                {{ $schedule['duration_unit'] ?? $booking->duration_unit ?? '' }}
                            </small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('الكمية') }}</span>
                            <strong>{{ $pricing['quantity'] ?? $booking->quantity ?? 1 }}</strong>
                            <small>party size: {{ $booking->party_size ?: '—' }}</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('ملاحظات') }}</span>
                            <strong>{{ $booking->notes ?: '—' }}</strong>
                            <small>booking notes</small>
                        </div>
                    </div>
                </div>

                <div class="a2-card booking-show-section">
                    <h2 class="a2-section-title">{{ __('التسعير') }}</h2>

                    <div class="booking-show-kv-grid">
                        <div class="booking-show-kv">
                            <span>{{ __('السعر الأصلي') }}</span>
                            <strong>{{ $money($originalPrice, $priceCurrency) }}</strong>
                            <small>original price</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('السعر النهائي') }}</span>
                            <strong>{{ $money($finalPrice, $priceCurrency) }}</strong>
                            <small>final price</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('الخصم') }}</span>
                            <strong>{{ !empty($pricing['discount_enabled']) ? 'مفعل' : 'غير مفعل' }}</strong>
                            <small>
                                {{ $discountPercent }}%
                                /
                                {{ $money($discountAmount, $priceCurrency) }}
                            </small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('مصدر السعر') }}</span>
                            <strong>{{ $pricing['source'] ?? '—' }}</strong>
                            <small>business_service_price_id: {{ $pricing['business_service_price_id'] ?? '—' }}</small>
                        </div>
                    </div>
                </div>

                <div class="a2-card booking-show-section">
                    <h2 class="a2-section-title">Deposit</h2>

                    <div class="booking-show-kv-grid">
                        <div class="booking-show-kv">
                            <span>{{ __('مطلوب؟') }}</span>
                            <strong>{{ $boolLabel(!empty($depositUi['required'])) }}</strong>
                            <small>{{ !empty($depositUi['exists']) ? 'record exists' : 'no record' }}</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('القيمة') }}</span>
                            <strong>{{ $money($depositUi['amount'] ?? $depositUi['hold'] ?? 0, $depositUi['currency'] ?? 'EGP') }}</strong>
                            <small>hold: {{ $money($depositUi['hold'] ?? 0, $depositUi['currency'] ?? 'EGP') }}</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('الحالة') }}</span>
                            <strong>{{ $depositUi['status'] ?? '—' }}</strong>
                            <small>frozen: {{ $boolLabel(!empty($depositUi['is_frozen'])) }}</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('التأكيدات') }}</span>
                            <strong>
                                Client: {{ $boolLabel(!empty($depositUi['client_confirmed'])) }}
                                /
                                Business: {{ $boolLabel(!empty($depositUi['business_confirmed'])) }}
                            </strong>
                            <small>deposit confirmations</small>
                        </div>
                    </div>
                </div>

                <div class="a2-card booking-show-section">
                    <h2 class="a2-section-title">{{ __('رسوم التنفيذ') }}</h2>

                    <div class="booking-show-kv-grid">
                        <div class="booking-show-kv">
                            <span>{{ __('تم الخصم؟') }}</span>
                            <strong>{{ !empty($fees['charged']) ? 'نعم' : 'لا' }}</strong>
                            <small>{{ $fees['charged_at'] ?? '—' }}</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('رسوم العميل') }}</span>
                            <strong>{{ $money($fees['client_amount'] ?? 0) }}</strong>
                            <small>client fee</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('رسوم البزنس') }}</span>
                            <strong>{{ $money($fees['business_amount'] ?? 0) }}</strong>
                            <small>business fee</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>Fee Row</span>
                            <strong dir="ltr">{{ $fees['fee_row_id'] ?? $debug['fee_row_id'] ?? '—' }}</strong>
                            <small>category_child_service_fee_id</small>
                        </div>
                    </div>
                </div>

                <div class="a2-card booking-show-section">
                    <h2 class="a2-section-title">{{ __('التأكيدات والنزاع') }}</h2>

                    <div class="booking-show-kv-grid">
                        <div class="booking-show-kv">
                            <span>{{ __('تأكيد العميل') }}</span>
                            <strong>{{ !empty($confirmations['client_confirmed']) ? 'تم' : 'لم يتم' }}</strong>
                            <small>{{ $confirmations['source'] ?? '—' }}</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('تأكيد البزنس') }}</span>
                            <strong>{{ !empty($confirmations['business_confirmed']) ? 'تم' : 'لم يتم' }}</strong>
                            <small>{{ $confirmations['source'] ?? '—' }}</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('النزاع') }}</span>
                            <strong>{{ !empty($disputeUi['has_dispute']) ? 'يوجد نزاع' : 'لا يوجد' }}</strong>
                            <small>
                                ID: {{ $disputeUi['id'] ?? '—' }}
                                /
                                {{ $disputeUi['status'] ?? '—' }}
                            </small>
                        </div>

                        <div class="booking-show-kv">
                            <span>Stage</span>
                            <strong>{{ $stage['label_ar'] ?? $statusLabel }}</strong>
                            <small dir="ltr">{{ $stage['key'] ?? '—' }}</small>
                        </div>
                    </div>
                </div>
            </div>

            @if(!empty($booking->bookable) || !empty($bookableMeta))
                <div class="a2-card booking-show-section">
                    <h2 class="a2-section-title">{{ __('العنصر القابل للحجز') }}</h2>

                    <div class="booking-show-kv-grid booking-show-kv-grid-4">
                        <div class="booking-show-kv">
                            <span>ID</span>
                            <strong>{{ $bookableMeta['id'] ?? $booking->bookable_id ?? '—' }}</strong>
                            <small>bookable_id</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('العنوان') }}</span>
                            <strong>{{ $bookableMeta['title'] ?? $booking->bookable?->title ?? '—' }}</strong>
                            <small>{{ $bookableMeta['code'] ?? $booking->bookable?->code ?? '' }}</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('النوع') }}</span>
                            <strong>{{ $bookableMeta['item_type'] ?? $booking->bookable?->item_type ?? '—' }}</strong>
                            <small>item_type</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('السعر') }}</span>
                            <strong>{{ $money($bookableMeta['price'] ?? 0) }}</strong>
                            <small>bookable price</small>
                        </div>
                    </div>
                </div>
            @endif

            @if($deposit)
                <div class="a2-card booking-show-section">
                    <h2 class="a2-section-title">{{ __('سجل Deposit') }}</h2>

                    <div class="booking-show-kv-grid booking-show-kv-grid-4">
                        <div class="booking-show-kv">
                            <span>ID</span>
                            <strong>{{ $deposit->id }}</strong>
                            <small>deposit_id</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('الحالة') }}</span>
                            <strong>{{ $deposit->status }}</strong>
                            <small>status</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>{{ __('إجمالي القيمة') }}</span>
                            <strong>{{ $money($deposit->total_amount ?? 0) }}</strong>
                            <small>total_amount</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>Client / Business</span>
                            <strong>
                                {{ $money($deposit->client_amount ?? 0) }}
                                /
                                {{ $money($deposit->business_amount ?? 0) }}
                            </strong>
                            <small>split</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>Client Confirmed</span>
                            <strong>{{ $boolLabel((int)($deposit->client_confirmed ?? 0) === 1) }}</strong>
                            <small>deposit</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>Business Confirmed</span>
                            <strong>{{ $boolLabel((int)($deposit->business_confirmed ?? 0) === 1) }}</strong>
                            <small>deposit</small>
                        </div>

                        <div class="booking-show-kv">
                            <span>Agree Release</span>
                            <strong>
                                C: {{ $boolLabel((int)($deposit->release_agreed_client ?? 0) === 1) }}
                                /
                                B: {{ $boolLabel((int)($deposit->release_agreed_business ?? 0) === 1) }}
                            </strong>
                            <small>release agreement</small>
                        </div>

                        <div class="booking-show-kv">
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

            <details class="a2-card booking-show-section booking-show-debug">
                <summary>Debug Context</summary>

                <div class="booking-show-kv-grid booking-show-kv-grid-4">
                    <div class="booking-show-kv">
                        <span>reference</span>
                        <strong dir="ltr">
                            {{ $debug['reference_type'] ?? 'booking' }}:{{ $debug['reference_id'] ?? $booking->id }}
                        </strong>
                        <small>wallet reference</small>
                    </div>

                    <div class="booking-show-kv">
                        <span>category_id</span>
                        <strong dir="ltr">{{ $debug['category_id'] ?? '—' }}</strong>
                        <small>root</small>
                    </div>

                    <div class="booking-show-kv">
                        <span>child_id</span>
                        <strong dir="ltr">{{ $debug['child_id'] ?? '—' }}</strong>
                        <small>category child</small>
                    </div>

                    <div class="booking-show-kv">
                        <span>platform_service_id</span>
                        <strong dir="ltr">{{ $debug['platform_service_id'] ?? $booking->service_id }}</strong>
                        <small>service</small>
                    </div>

                    <div class="booking-show-kv">
                        <span>fee_row_id</span>
                        <strong dir="ltr">{{ $debug['fee_row_id'] ?? '—' }}</strong>
                        <small>fee row</small>
                    </div>

                    <div class="booking-show-kv">
                        <span>ccsf_id</span>
                        <strong dir="ltr">{{ $debug['category_child_service_fee_id'] ?? '—' }}</strong>
                        <small>category_child_service_fee_id</small>
                    </div>

                    <div class="booking-show-kv">
                        <span>charged_at</span>
                        <strong dir="ltr">{{ $fees['charged_at'] ?? '—' }}</strong>
                        <small>execution fee</small>
                    </div>

                    <div class="booking-show-kv">
                        <span>transactions</span>
                        <strong>{{ count($fees['transactions'] ?? []) }}</strong>
                        <small>wallet transactions</small>
                    </div>
                </div>
            </details>
        </main>

        <aside class="booking-show-side">
            <div class="a2-card booking-show-actions-card">
                <div class="booking-show-section-head">
                    <div>
                        <h2 class="a2-section-title">{{ __('الإجراءات') }}</h2>
                        <div class="a2-section-subtitle">
                            {{ __('الإجراءات المتاحة حسب حالة الحجز.') }}
                        </div>
                    </div>
                </div>

                <div class="booking-show-actions">

                    @if($can(OperationAction::CONFIRM_CLIENT))
                        <form method="POST" action="{{ route('admin.bookings.start_confirm.client', $booking) }}">
                            @csrf
                            <button type="submit" class="a2-btn a2-btn-primary a2-btn-block">
                                {{ __('تأكيد العميل') }}
                            </button>
                        </form>
                    @endif

                    @if($can(OperationAction::CONFIRM_BUSINESS))
                        <form method="POST" action="{{ route('admin.bookings.start_confirm.business', $booking) }}">
                            @csrf
                            <button type="submit" class="a2-btn a2-btn-primary a2-btn-block">
                                {{ __('تأكيد البزنس') }}
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

                    @if($can(OperationAction::START))
                        <form method="POST" action="{{ route('admin.bookings.start', $booking) }}">
                            @csrf
                            <button type="submit" class="a2-btn a2-btn-success a2-btn-block">
                                {{ __('بدء التنفيذ') }}
                            </button>
                        </form>
                    @endif

                    @if($can(OperationAction::COMPLETE))
                        <form method="POST" action="{{ route('admin.bookings.complete', $booking) }}">
                            @csrf
                            <button type="submit" class="a2-btn a2-btn-success a2-btn-block">
                                {{ __('إنهاء الحجز') }}
                            </button>
                        </form>
                    @endif

                    @php
                            $depositStatus = (string) data_get($depositUi, 'status', '');

                            if ($deposit && $depositStatus === '') {
                                $rawDepositStatus = $deposit->status ?? null;

                                $depositStatus = $rawDepositStatus instanceof \BackedEnum
                                    ? $rawDepositStatus->value
                                    : (string) ($rawDepositStatus ?? '');
                            }

                            $depositIsFrozen = $depositStatus === 'frozen';
                            $depositIsReleased = $depositStatus === 'released';
                            $depositIsRefunded = $depositStatus === 'refunded';
                            $depositIsFinal = $depositIsReleased || $depositIsRefunded;
                        @endphp

                        @if($can(OperationAction::RELEASE_DEPOSIT) && $depositIsFrozen)
                            <form method="POST" action="{{ route('admin.bookings.deposit.release', $booking) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="a2-btn a2-btn-success a2-btn-block"
                                    onclick="return confirm('هل تريد عمل Release لهذا الديبوزت؟')"
                                >
                                    Release Deposit
                                </button>
                            </form>
                        @endif

                        @if($can(OperationAction::REFUND_DEPOSIT) && $depositIsFrozen)
                            <form method="POST" action="{{ route('admin.bookings.deposit.refund', $booking) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="a2-btn a2-btn-ghost a2-btn-block"
                                    onclick="return confirm('هل تريد عمل Refund لهذا الديبوزت؟')"
                                >
                                    Refund Deposit
                                </button>
                            </form>
                        @endif

                        @if($can(OperationAction::OPEN_DISPUTE) && $depositIsFrozen)
                            <form method="POST" action="{{ route('admin.bookings.deposit.dispute.open', $booking) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="a2-btn a2-btn-danger a2-btn-block"
                                    onclick="return confirm('هل تريد فتح نزاع على هذا الديبوزت؟')"
                                >
                                    Open Dispute
                                </button>
                            </form>
                        @endif

                        @if($deposit && $depositIsFrozen)
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

                        @if($deposit && $depositIsReleased)
                            <div class="a2-alert a2-alert-success">
                                {{ __('تم عمل Release للديبوزت. لا يمكن تنفيذ Refund بعد Release.') }}
                            </div>
                        @endif

                        @if($deposit && $depositIsRefunded)
                            <div class="a2-alert a2-alert-info">
                                {{ __('تم عمل Refund للديبوزت. لا يمكن تنفيذ Release بعد Refund.') }}
                            </div>
                        @endif

                    @if($can(OperationAction::CANCEL))
                        <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}">
                            @csrf
                            <button
                                type="submit"
                                class="a2-btn a2-btn-danger a2-btn-block"
                                onclick="return confirm('هل تريد إلغاء هذا الحجز؟')"
                            >
                                {{ __('إلغاء الحجز') }}
                            </button>
                        </form>
                    @endif

                    @if($actions->isEmpty())
                        <div class="booking-show-empty">
                            {{ __('لا توجد إجراءات متاحة حاليًا.') }}
                        </div>
                    @endif
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection