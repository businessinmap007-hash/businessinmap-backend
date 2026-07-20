@extends('admin-v2.layouts.master')

@section('title', __('رسوم خدمات القسم الفرعي'))
@section('body_class', 'admin-v2 admin-v2-category-child-service-fees-edit')

@section('content')
@php
    $parentIdInt = (int) ($parentId ?? 0);

    $childName = $categoryChild->name_ar
        ?: ($categoryChild->name_en ?: ('#' . $categoryChild->id));

    $parentName = $parent
        ? ($parent->name_ar ?: ($parent->name_en ?: ('#' . $parent->id)))
        : null;

    $services = collect($services ?? []);
    $feeRows = collect($feeRows ?? []);
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('رسوم خدمات القسم الفرعي') }}</h1>

            <div class="a2-page-subtitle">
                <div>
                    <strong>{{ __('القسم الفرعي:') }}</strong>
                    {{ $childName }}
                    <span class="a2-muted">#{{ $categoryChild->id }}</span>
                </div>

                @if($parentName)
                    <div class="a2-mt-8">
                        <strong>{{ __('القسم الرئيسي:') }}</strong>
                        {{ $parentName }}
                        <span class="a2-muted">#{{ $parentIdInt }}</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="a2-page-actions">
            @if($parentIdInt > 0)
                <a
                    href="{{ route('admin.categories.index', ['root_id' => $parentIdInt]) }}"
                    class="a2-btn a2-btn-ghost"
                >
                    {{ __('رجوع إلى الأقسام') }}
                </a>
            @endif

            <a
                href="{{ route('admin.category-children.edit', $categoryChild->id) }}"
                class="a2-btn a2-btn-ghost"
            >
                {{ __('تعديل القسم الفرعي') }}
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
            <div class="a2-fw-900 a2-mb-8">{{ __('يوجد بعض الأخطاء، راجع البيانات التالية:') }}</div>

            <ul style="margin:0;padding-inline-start:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="a2-card a2-card--soft a2-mb-16">
        <div class="a2-section-title">{{ __('تعريف هذه الصفحة') }}</div>
        <div class="a2-section-subtitle">
            {{ __('من هنا يتم تحديد رسوم كل خدمة متاحة لهذا القسم الفرعي داخل القسم الرئيسي المحدد. هذه الرسوم يتم استخدامها لاحقًا عند دخول الحجز مرحلة التنفيذ') }}
            <span dir="ltr">in_progress</span>.
        </div>

        <div class="a2-kv-grid a2-kv-grid-3 a2-mt-16">
            <div class="a2-kv-box">
                <span>{{ __('رسوم البزنس') }}</span>
                <strong>
                    {{ __('مبلغ ثابت يتم خصمه من صاحب البزنس إذا كانت الرسوم مفعلة وكان لديه موافقة خصم تلقائي.') }}
                </strong>
            </div>

            <div class="a2-kv-box">
                <span>{{ __('رسوم المستخدم') }}</span>
                <strong>
                    {{ __('مبلغ ثابت يتم خصمه من العميل إذا كانت الرسوم مفعلة وكان لديه موافقة خصم تلقائي.') }}
                </strong>
            </div>

            <div class="a2-kv-box">
                <span>{{ __('منع التكرار') }}</span>
                <strong>
                    {{ __('يتم منع تكرار الخصم بواسطة') }}
                    <span dir="ltr">idempotency_key</span>
                    {{ __('داخل معاملات المحفظة.') }}
                </strong>
            </div>
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('admin.category-child-service-fees.update', ['categoryChild' => $categoryChild->id, 'parent_id' => $parentIdInt]) }}"
        class="a2-card"
    >
        @csrf
        @method('PUT')

        @if($services->isEmpty())
            <div class="a2-empty-cell">
                {{ __('لا توجد خدمات مفعلة مرتبطة بهذا القسم الفرعي داخل هذا القسم الرئيسي حاليًا.') }}
            </div>

            <div class="a2-page-actions a2-mt-16">
                @if($parentIdInt > 0)
                    <a
                        href="{{ route('admin.categories.index', ['root_id' => $parentIdInt]) }}"
                        class="a2-btn a2-btn-ghost"
                    >
                        {{ __('رجوع') }}
                    </a>
                @endif
            </div>
        @else
            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                    <tr>
                        <th style="min-width:70px;">#</th>
                        <th style="min-width:240px;">{{ __('الخدمة') }}</th>
                        <th style="min-width:120px;">{{ __('تفعيل الصف') }}</th>

                        <th style="min-width:120px;">{{ __('رسوم البزنس') }}</th>
                        <th style="min-width:140px;">{{ __('قيمة البزنس') }}</th>

                        <th style="min-width:120px;">{{ __('رسوم المستخدم') }}</th>
                        <th style="min-width:140px;">{{ __('قيمة المستخدم') }}</th>

                        <th style="min-width:90px;">{{ __('العملة') }}</th>
                        <th style="min-width:90px;">{{ __('الترتيب') }}</th>
                        <th style="min-width:280px;">{{ __('ملاحظات') }}</th>
                    </tr>
                    </thead>

                    <tbody>
                    @foreach($services as $service)
                        @php
                            $serviceId = (int) $service->id;
                            $fee = $feeRows->get($serviceId);

                            $serviceName = $service->name_ar
                                ?: ($service->name_en ?: ($service->key ?: ('#' . $serviceId)));

                            $rowOld = old('rows.' . $serviceId, []);

                            $isActive = array_key_exists('is_active', $rowOld)
                                ? 1
                                : ((int) ($fee->is_active ?? 0));

                            $businessFeeEnabled = array_key_exists('business_fee_enabled', $rowOld)
                                ? 1
                                : ((int) ($fee->business_fee_enabled ?? 0));

                            $clientFeeEnabled = array_key_exists('client_fee_enabled', $rowOld)
                                ? 1
                                : ((int) ($fee->client_fee_enabled ?? 0));

                            $businessFeeAmount = old(
                                'rows.' . $serviceId . '.business_fee_amount',
                                isset($fee) ? (string) $fee->business_fee_amount : '0.00'
                            );

                            $clientFeeAmount = old(
                                'rows.' . $serviceId . '.client_fee_amount',
                                isset($fee) ? (string) $fee->client_fee_amount : '0.00'
                            );

                            $currency = old(
                                'rows.' . $serviceId . '.currency',
                                isset($fee) && $fee->currency ? $fee->currency : 'EGP'
                            );

                            $sortOrder = old(
                                'rows.' . $serviceId . '.sort_order',
                                isset($fee) ? (int) $fee->sort_order : ($loop->iteration * 10)
                            );

                            $notes = old(
                                'rows.' . $serviceId . '.notes',
                                isset($fee) ? (string) $fee->notes : ''
                            );

                            $hasAnyFee = ((int) $businessFeeEnabled === 1 && (float) $businessFeeAmount > 0)
                                || ((int) $clientFeeEnabled === 1 && (float) $clientFeeAmount > 0);

                            $feesOn = $hasAnyFee && (int) $isActive === 1;
                        @endphp

                        <tr>
                            <td>
                                <div class="a2-fw-900">{{ $loop->iteration }}</div>
                                <div class="a2-muted a2-mt-8">Service: {{ $serviceId }}</div>

                                @if($fee)
                                    <div class="a2-muted a2-mt-8">Fee Row: {{ $fee->id }}</div>
                                @endif
                            </td>

                            <td class="a2-text-right">
                                <div class="a2-fw-900">{{ $serviceName }}</div>

                                <div class="a2-muted a2-mt-8" dir="ltr">
                                    {{ $service->key ?: '—' }}
                                </div>

                                <div class="a2-mt-8 a2-inline-actions">
                                    @if($feesOn)
                                        <span class="a2-pill a2-pill-success">Fees ON</span>
                                    @else
                                        <span class="a2-pill a2-pill-gray">Fees OFF</span>
                                    @endif

                                    @if(isset($service->supports_deposit))
                                    @if($service->supports_deposit)
                                        <span class="a2-pill a2-pill-success">Deposit Supported</span>
                                    @else
                                        <span class="a2-pill a2-pill-gray">Deposit Not Supported</span>
                                    @endif
                                @endif
                                </div>
                            </td>

                            <td>
                                <label class="a2-check" style="justify-content:center;">
                                    <input
                                        type="checkbox"
                                        name="rows[{{ $serviceId }}][is_active]"
                                        value="1"
                                        @checked((int) $isActive === 1)
                                    >
                                    <span>{{ __('مفعل') }}</span>
                                </label>
                            </td>

                            <td>
                                <label class="a2-check" style="justify-content:center;">
                                    <input
                                        type="checkbox"
                                        name="rows[{{ $serviceId }}][business_fee_enabled]"
                                        value="1"
                                        @checked((int) $businessFeeEnabled === 1)
                                    >
                                    <span>{{ __('تشغيل') }}</span>
                                </label>
                            </td>

                            <td>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="a2-input"
                                    name="rows[{{ $serviceId }}][business_fee_amount]"
                                    value="{{ $businessFeeAmount }}"
                                    placeholder="0.00"
                                    dir="ltr"
                                >
                            </td>

                            <td>
                                <label class="a2-check" style="justify-content:center;">
                                    <input
                                        type="checkbox"
                                        name="rows[{{ $serviceId }}][client_fee_enabled]"
                                        value="1"
                                        @checked((int) $clientFeeEnabled === 1)
                                    >
                                    <span>{{ __('تشغيل') }}</span>
                                </label>
                            </td>

                            <td>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="a2-input"
                                    name="rows[{{ $serviceId }}][client_fee_amount]"
                                    value="{{ $clientFeeAmount }}"
                                    placeholder="0.00"
                                    dir="ltr"
                                >
                            </td>

                            <td>
                                <input
                                    type="text"
                                    class="a2-input"
                                    name="rows[{{ $serviceId }}][currency]"
                                    value="{{ $currency }}"
                                    maxlength="3"
                                    placeholder="EGP"
                                    dir="ltr"
                                    style="text-transform:uppercase;"
                                >
                            </td>

                            <td>
                                <input
                                    type="number"
                                    min="0"
                                    class="a2-input"
                                    name="rows[{{ $serviceId }}][sort_order]"
                                    value="{{ $sortOrder }}"
                                    placeholder="0"
                                    dir="ltr"
                                >
                            </td>

                            <td>
                                <textarea
                                    class="a2-textarea"
                                    name="rows[{{ $serviceId }}][notes]"
                                    rows="3"
                                    placeholder="{{ __('ملاحظات اختيارية') }}"
                                    style="min-height:90px;"
                                >{{ $notes }}</textarea>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="a2-card a2-card--soft a2-card--tight a2-mt-16">
                <div class="a2-section-title">{{ __('ملاحظات تشغيلية') }}</div>
                <div class="a2-section-subtitle">
                    {{ __('لو قيمة الرسوم تساوي صفر سيتم تعطيل رسوم هذا الطرف تلقائيًا عند الحفظ. ولو لم يتم تفعيل رسوم البزنس ولا رسوم المستخدم سيتم تعطيل صف الرسوم بالكامل.') }}
                </div>
            </div>

            <div class="a2-page-actions a2-mt-16">
                <button type="submit" class="a2-btn a2-btn-primary">
                    {{ __('حفظ رسوم الخدمات') }}
                </button>

                @if($parentIdInt > 0)
                    <a
                        href="{{ route('admin.categories.index', ['root_id' => $parentIdInt]) }}"
                        class="a2-btn a2-btn-ghost"
                    >
                        {{ __('رجوع') }}
                    </a>
                @endif
            </div>
        @endif
    </form>
</div>
@endsection