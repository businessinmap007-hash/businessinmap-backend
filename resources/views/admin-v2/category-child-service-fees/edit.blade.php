@extends('admin-v2.layouts.master')

@section('title', 'رسوم خدمات القسم الفرعي')
@section('body_class', 'admin-v2-category-child-service-fees')

@section('content')
@php
   
    $parentIdInt = (int) ($parentId ?? 0);

    $childName = $categoryChild->name_ar
        ?: ($categoryChild->name_en ?: ('#' . $categoryChild->id));

    $parentName = $parent
        ? ($parent->name_ar ?: ($parent->name_en ?: ('#' . $parent->id)))
        : null;
@endphp

@if($parentName)
    <div class="a2-mt-8"><strong>القسم الرئيسي:</strong> {{ $parentName }}</div>
@endif


<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">رسوم خدمات القسم الفرعي</h1>
            <div class="a2-page-subtitle">
                <div><strong>القسم الفرعي:</strong> {{ $childName }}</div>

                @if($parentName)
    <div class="a2-mt-8"><strong>القسم الرئيسي:</strong> {{ $parentName }}</div>
@endif
            </div>
        </div>

        <div class="a2-page-actions">
            @if($parentIdInt > 0)
                <a
                    href="{{ route('admin.categories.index', ['root_id' => $parentIdInt]) }}"
                    class="a2-btn a2-btn-ghost"
                >
                    رجوع إلى الأقسام
                </a>
            @endif

            <a
                href="{{ route('admin.category-children.edit', $categoryChild->id) }}"
                class="a2-btn a2-btn-ghost"
            >
                تعديل القسم الفرعي
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            <div class="a2-fw-900 a2-mb-8">يوجد بعض الأخطاء، راجع البيانات التالية:</div>
            <ul style="margin:0;padding-inline-start:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="a2-card a2-card--soft a2-mb-16">
        <div class="a2-section-title">ملاحظات مهمة</div>
        <div class="a2-section-subtitle">
            يتم هنا تحديد رسوم كل خدمة متاحة لهذا القسم الفرعي.
        </div>

        <div class="a2-kv">
            <div class="a2-kv-row">
                <div class="a2-kv-key">رسوم البزنس</div>
                <div class="a2-kv-val">المبلغ الذي يخصم من صاحب البزنس عند التنفيذ إذا كان التفعيل والموافقة موجودين.</div>
            </div>

            <div class="a2-kv-row">
                <div class="a2-kv-key">رسوم المستخدم</div>
                <div class="a2-kv-val">المبلغ الذي يخصم من العميل عند التنفيذ إذا كان التفعيل والموافقة موجودين.</div>
            </div>

            <div class="a2-kv-row">
                <div class="a2-kv-key">إلغاء بعد التنفيذ</div>
                <div class="a2-kv-val">إذا دخل الطلب حالة <span dir="ltr">in_progress</span> ثم أُلغي لاحقًا فلا يتم رد رسوم الخدمة.</div>
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

        @if(($services ?? collect())->isEmpty())
            <div class="a2-empty-cell">
                لا توجد خدمات مرتبطة بهذا القسم الفرعي حاليًا.
            </div>
        @else
            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                        <tr>
                            <th style="min-width:70px;">#</th>
                            <th style="min-width:180px;">الخدمة</th>
                            <th style="min-width:110px;">مفعلة</th>

                            <th style="min-width:120px;">رسوم البزنس</th>
                            <th style="min-width:130px;">قيمة رسوم البزنس</th>

                            <th style="min-width:120px;">رسوم المستخدم</th>
                            <th style="min-width:130px;">قيمة رسوم المستخدم</th>

                            <th style="min-width:90px;">العملة</th>
                            <th style="min-width:90px;">الترتيب</th>
                            <th style="min-width:240px;">ملاحظات</th>
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
                                    : ((int) ($fee->is_active ?? 1));

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
                            @endphp

                            <tr>
                                <td>
                                    <div class="a2-fw-900">{{ $loop->iteration }}</div>
                                    <div class="a2-muted a2-mt-8">ID: {{ $serviceId }}</div>
                                </td>

                                <td class="a2-text-right">
                                    <div class="a2-fw-900">{{ $serviceName }}</div>

                                    <div class="a2-muted a2-mt-8" dir="ltr">
                                        {{ $service->key ?: '—' }}
                                    </div>

                                    @if(isset($service->supports_deposit))
                                        <div class="a2-mt-8">
                                            @if($service->supports_deposit)
                                                <span class="a2-pill a2-pill-success">
                                                    Deposit ON
                                                </span>
                                                @if(isset($service->max_deposit_percent))
                                                    <span class="a2-pill a2-pill-gray">
                                                        Max: {{ (int) $service->max_deposit_percent }}%
                                                    </span>
                                                @endif
                                            @else
                                                <span class="a2-pill a2-pill-gray">
                                                    Deposit OFF
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    <label class="a2-check" style="justify-content:center;">
                                        <input
                                            type="checkbox"
                                            name="rows[{{ $serviceId }}][is_active]"
                                            value="1"
                                            @checked((int) $isActive === 1)
                                        >
                                        <span>مفعل</span>
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
                                        <span>تشغيل</span>
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
                                        <span>تشغيل</span>
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
                                    >
                                </td>

                                <td>
                                    <textarea
                                        class="a2-textarea"
                                        name="rows[{{ $serviceId }}][notes]"
                                        rows="3"
                                        placeholder="ملاحظات اختيارية"
                                        style="min-height:90px;"
                                    >{{ $notes }}</textarea>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="a2-page-actions a2-mt-16">
                <button type="submit" class="a2-btn a2-btn-primary">
                    حفظ رسوم الخدمات
                </button>

                @if($parentIdInt > 0)
                    <a
                        href="{{ route('admin.categories.index', ['root_id' => $parentIdInt]) }}"
                        class="a2-btn a2-btn-ghost"
                    >
                        رجوع
                    </a>
                @endif
            </div>
        @endif
    </form>
</div>
@endsection